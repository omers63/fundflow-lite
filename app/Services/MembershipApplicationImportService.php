<?php

namespace App\Services;

use App\Models\MembershipApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MembershipApplicationImportService
{
    /**
     * Import pending membership applications from a UTF-8 CSV file with a header row.
     *
     * Required: name, email, mobile_phone, iban
     * Optional: national_id, date_of_birth, city, address, bank_account_number, next_of_kin_name, next_of_kin_phone,
     * password (≥8 chars overrides default on first row only), application_type, gender, marital_status, membership_date,
     * home_phone, work_phone, work_place, residency_place, occupation, employer, monthly_income
     *
     * Family grouping (parent → dependents):
     * 1) Optional column **household_email** (aliases: parent_email, family_email, guardian_email): all rows sharing
     *    the same normalized value link to the **first** user created for that household key (submitted_by_user_id).
     *    Use this when each applicant has a unique **email** but shares one household login email.
     * 2) If household_email is empty on a row, the row's own **email** is the grouping key — same as before: duplicate
     *    emails across rows mean the first row is parent and later rows are dependents.
     * Put the parent row before dependents when they only share household_email and the parent's login email differs.
     *
     * @return array{created: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function import(string $absolutePath, string $defaultPassword): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        if (strlen((string) $defaultPassword) < 8) {
            return [
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['Default password must be at least 8 characters.'],
            ];
        }

        $this->authorizeCsvImport();

        $rows = $this->parseAssociativeCsv($absolutePath);

        if ($rows === []) {
            return [
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['The file is empty or has no data rows after the header.'],
            ];
        }

        $lineBase = 2;

        /** @var list<array{line: int, row: array<string, string>}> */
        $rowsWithLines = [];
        foreach ($rows as $index => $row) {
            $rowsWithLines[] = ['line' => $lineBase + $index, 'row' => $row];
        }

        // Parents / standalone applicants (no household_email) import before dependents that reference it.
        usort($rowsWithLines, function (array $a, array $b): int {
            $aHousehold = $this->nullableCell($a['row'], 'household_email') !== null && $this->nullableCell($a['row'], 'household_email') !== '';
            $bHousehold = $this->nullableCell($b['row'], 'household_email') !== null && $this->nullableCell($b['row'], 'household_email') !== '';
            $c = ($aHousehold ? 1 : 0) <=> ($bHousehold ? 1 : 0);

            return $c !== 0 ? $c : $a['line'] <=> $b['line'];
        });

        /** @var array<string, int> user id of the first applicant created for this household grouping key */
        $familyParentUserIdByHouseholdKey = [];

        foreach ($rowsWithLines as $item) {
            $lineNumber = $item['line'];
            $row = $item['row'];

            try {
                $this->importRow($row, $defaultPassword, $familyParentUserIdByHouseholdKey);
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Row {$lineNumber}: {$e->getMessage()}";
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function importRow(array $row, string $defaultPassword, array &$familyParentUserIdByHouseholdKey): void
    {
        $name = trim((string) $this->cell($row, 'name'));
        $email = strtolower(trim($this->cell($row, 'email')));

        if ($email === '') {
            throw new \InvalidArgumentException('email is required.');
        }

        $v = Validator::make(['email' => $email], ['email' => 'required|email']);
        if ($v->fails()) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('name is required.');
        }

        $attrs = $this->buildMembershipApplicationAttributes($row);

        $password = $this->cell($row, 'password');
        $plain = (strlen($password) >= 8) ? $password : $defaultPassword;

        $mobile = (string) $attrs['mobile_phone'];

        $householdKey = $this->householdGroupingKey($row, $email);
        $parentUserId = $familyParentUserIdByHouseholdKey[$householdKey] ?? null;

        DB::transaction(function () use ($name, $email, $mobile, $plain, $attrs, $parentUserId, &$familyParentUserIdByHouseholdKey, $householdKey, $row): void {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $mobile,
                'role' => 'member',
                'status' => 'pending',
                'password' => Hash::make($plain),
            ]);

            MembershipApplication::create(array_merge($attrs, [
                'user_id' => $user->id,
                'submitted_by_user_id' => $parentUserId,
            ]));

            $declaredHousehold = $this->optionalHouseholdEmailFromRow($row);
            $anchorsHouseholdBucket = $declaredHousehold === null || $declaredHousehold === $email;
            if ($anchorsHouseholdBucket && !isset($familyParentUserIdByHouseholdKey[$householdKey])) {
                $familyParentUserIdByHouseholdKey[$householdKey] = (int) $user->id;
            }
        });
    }

    /**
     * Key used to chain dependents to the first imported user in the same household.
     */
    private function householdGroupingKey(array $row, string $loginEmail): string
    {
        $shared = $this->optionalHouseholdEmailFromRow($row);
        if ($shared !== null) {
            return $shared;
        }

        return $loginEmail;
    }

    /**
     * Optional CSV column(s) that identify the household / parent login email (normalized lowercase).
     */
    private function optionalHouseholdEmailFromRow(array $row): ?string
    {
        $raw = $this->nullableCell($row, 'household_email');
        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = strtolower(trim($raw));
        $v = Validator::make(['email' => $normalized], ['email' => 'required|email']);
        if ($v->fails()) {
            throw new \InvalidArgumentException('household_email (or parent/family email column) must be a valid email address.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function buildMembershipApplicationAttributes(array $row): array
    {
        $nationalId = $this->nullableCell($row, 'national_id');

        $dobRaw = $this->cell($row, 'date_of_birth');
        $dateOfBirth = null;
        if ($dobRaw !== '') {
            $dateOfBirth = $this->parseFlexibleDateToDateString($dobRaw, 'date_of_birth');
            if ($dateOfBirth > now()->toDateString()) {
                throw new \InvalidArgumentException('date_of_birth cannot be in the future.');
            }
        }

        $city = $this->nullableCell($row, 'city');

        $addressRaw = trim((string) ($this->cell($row, 'address')));
        $address = $addressRaw === '' ? null : $addressRaw;

        $mobile = $this->cell($row, 'mobile_phone');
        if ($mobile === '') {
            $mobile = $this->cell($row, 'work_phone');
        }
        if ($mobile === '') {
            $mobile = $this->cell($row, 'home_phone');
        }
        if ($mobile === '') {
            throw new \InvalidArgumentException('mobile_phone is required.');
        }

        $bankAccountNumber = $this->nullableCell($row, 'bank_account_number');

        $ibanRaw = $this->cell($row, 'iban');
        $iban = $ibanRaw === '' ? null : $this->normalizeIban($ibanRaw);

        $nokName = $this->nullableCell($row, 'next_of_kin_name');
        $nokPhone = $this->nullableCell($row, 'next_of_kin_phone');

        $applicationType = $this->normalizeApplicationType($this->cell($row, 'application_type'));
        $gender = $this->normalizeGender($this->nullableCell($row, 'gender'));
        $maritalStatus = $this->normalizeMaritalStatus($this->nullableCell($row, 'marital_status'));
        $membershipDate = $this->parseMembershipDateOptional($this->cell($row, 'membership_date'));
        $monthlyIncome = $this->parseMonthlyIncome($this->cell($row, 'monthly_income'));

        $optionalString = static fn(?string $v): ?string => ($v !== null && $v !== '') ? $v : null;

        $occupation = $optionalString($this->cell($row, 'occupation'));
        $employer = $optionalString($this->cell($row, 'employer'));

        return [
            'application_type' => $applicationType,
            'gender' => $gender,
            'marital_status' => $maritalStatus,
            'national_id' => $nationalId,
            'date_of_birth' => $dateOfBirth,
            'address' => $address,
            'city' => $city,
            'home_phone' => $optionalString($this->nullableCell($row, 'home_phone')),
            'work_phone' => $optionalString($this->nullableCell($row, 'work_phone')),
            'mobile_phone' => $mobile,
            'occupation' => $occupation,
            'employer' => $employer,
            'work_place' => $optionalString($this->nullableCell($row, 'work_place')),
            'residency_place' => $optionalString($this->nullableCell($row, 'residency_place')),
            'monthly_income' => $monthlyIncome,
            'bank_account_number' => $bankAccountNumber,
            'iban' => $iban,
            'membership_date' => $membershipDate,
            'next_of_kin_name' => $nokName,
            'next_of_kin_phone' => $nokPhone,
            'application_form_path' => null,
            'status' => 'pending',
        ];
    }

    /**
     * Matches Filament "Import Applications" visibility: create or update applications.
     *
     * @throws AuthorizationException
     */
    private function authorizeCsvImport(): void
    {
        $user = auth()->user();
        if ($user === null) {
            throw new AuthorizationException(__('You must be signed in to import applications.'));
        }

        if ($user->can('Create:MembershipApplication') || $user->can('Update:MembershipApplication')) {
            return;
        }

        throw new AuthorizationException(__('You do not have permission to import membership applications.'));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseAssociativeCsv(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);

        if ($content === false) {
            return [];
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (str_starts_with($content, "\xFF\xFE")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16BE');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn($l) => trim((string) $l) !== ''));

        if (count($lines) < 2) {
            return [];
        }

        $headerLine = array_shift($lines);
        $delimiter = $this->detectCsvDelimiter((string) $headerLine);
        $headers = str_getcsv((string) $headerLine, $delimiter);
        $headers = array_map(fn(string $h): string => $this->normalizeCsvHeaderKey($h), $headers);

        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv((string) $line, $delimiter);
            $assoc = [];
            foreach ($headers as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * Map human-readable / spreadsheet headers to importer keys (e.g. "Mobile Phone" → mobile_phone).
     */
    private function normalizeCsvHeaderKey(string $header): string
    {
        $h = trim($header);
        if (str_starts_with($h, "\xEF\xBB\xBF")) {
            $h = substr($h, 3);
        }

        $h = strtolower(str_replace("\xc2\xa0", ' ', $h));
        $h = str_replace(["\u{00AD}", '-'], '_', $h);
        $h = preg_replace('/\s+/', '_', $h);
        $h = preg_replace('/_+/', '_', $h);
        $h = trim($h, '_');

        return match ($h) {
            'e_mail', 'email_address' => 'email',
            'household_e_mail', 'parent_email', 'parent_e_mail', 'family_email', 'family_e_mail',
            'guardian_email', 'guardian_e_mail', 'shared_email', 'shared_login_email', 'household_login' => 'household_email',
            'full_name', 'applicant_name', 'member_name', 'display_name', 'contact_name' => 'name',
            'mobile', 'cell', 'mobile_number', 'cell_phone', 'whatsapp_number', 'gsm', 'whatsapp' => 'mobile_phone',
            'national_id_number', 'nid', 'iqama', 'iqama_number', 'national_identification' => 'national_id',
            'dob', 'birth_date', 'birthdate' => 'date_of_birth',
            'bank_account', 'account_number', 'bank_acc' => 'bank_account_number',
            'kin_name', 'emergency_contact_name', 'nok_name' => 'next_of_kin_name',
            'kin_phone', 'emergency_contact_phone', 'nok_phone' => 'next_of_kin_phone',
            default => $h,
        };
    }

    /**
     * Excel in many locales exports CSV with ';' instead of ',' — comma parsing yields one column and every row fails validation.
     */
    private function detectCsvDelimiter(string $headerLine): string
    {
        $comma = substr_count($headerLine, ',');
        $semi = substr_count($headerLine, ';');
        $tab = substr_count($headerLine, "\t");

        if ($tab >= $comma && $tab >= $semi && $tab > 0) {
            return "\t";
        }

        if ($semi > $comma) {
            return ';';
        }

        return ',';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function cell(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    /**
     * @param  array<string, string>  $row
     */
    private function nullableCell(array $row, string $key): ?string
    {
        $v = $this->cell($row, $key);

        return $v === '' ? null : $v;
    }

    /**
     * Supports ISO (Y-m-d), Excel-style d/m/Y and m/d/Y (tries d/m first when ambiguous), d-m-Y, etc.
     */
    private function parseFlexibleDateToDateString(string $value, string $fieldLabel): string
    {
        $v = trim($value);
        if ($v === '') {
            throw new \InvalidArgumentException("Invalid {$fieldLabel}: {$value}");
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            try {
                return Carbon::createFromFormat('!Y-m-d', $v)->toDateString();
            } catch (Throwable) {
                throw new \InvalidArgumentException("Invalid {$fieldLabel}: {$value}");
            }
        }

        $formats = [
            'd/m/Y',
            'd/m/y',
            'd-m-Y',
            'd-m-y',
            'd.m.Y',
            'd.m.y',
            'm/d/Y',
            'm/d/y',
            'm-d-Y',
            'm-d-y',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $v)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($v)->toDateString();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid {$fieldLabel}: {$value}");
        }
    }

    private function parseMembershipDateOptional(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->parseFlexibleDateToDateString($value, 'membership_date');
    }

    private function normalizeApplicationType(string $value): string
    {
        $t = trim($value);

        if ($t === '') {
            return 'new';
        }

        $arabicMap = [
            'جديد' => 'new',
            'تجديد' => 'renew',
            'تمديد' => 'renew',
            'استئناف' => 'resume',
            'استمرار' => 'resume',
        ];
        if (isset($arabicMap[$t])) {
            return $arabicMap[$t];
        }

        $v = strtolower($t);

        $allowed = array_keys(MembershipApplication::applicationTypeOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('application_type must be one of: ' . implode(', ', $allowed) . " (got: {$value})");
    }

    private function normalizeGender(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $t = trim($value);
        $map = [
            'ذكر' => 'male',
            'أنثى' => 'female',
            'انثى' => 'female',
            'أنثي' => 'female',
            'انثي' => 'female',
            'أخرى' => 'other',
            'أخر' => 'other',
            'آخر' => 'other',
            'آخرى' => 'other',
        ];
        if (isset($map[$t])) {
            return $map[$t];
        }

        $v = strtolower($t);
        $allowed = array_keys(MembershipApplication::genderOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('gender must be one of: ' . implode(', ', $allowed) . " (got: {$value})");
    }

    private function normalizeMaritalStatus(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $t = preg_replace('/\s+/u', ' ', trim($value));
        $map = [
            'أعزب' => 'single',
            'عزباء' => 'single',
            'عازب' => 'single',
            'عازبة' => 'single',
            'غير متزوج' => 'single',
            'غير متزوجة' => 'single',
            'غير متزوجه' => 'single',
            'غيرالمتزوج' => 'single',
            'غير المتزوج' => 'single',
            'غير المتزوجة' => 'single',
            'متزوج' => 'married',
            'متزوجة' => 'married',
            'متزج' => 'married',
            'مطلق' => 'divorced',
            'مطلقة' => 'divorced',
            'أرمل' => 'widowed',
            'أرملة' => 'widowed',
            'أخرى' => 'other',
            'أخر' => 'other',
            'آخر' => 'other',
            'آخرى' => 'other',
        ];
        $candidates = array_unique(array_filter([
            $t,
            preg_replace('/\s+/u', ' ', trim($this->stripArabicCombiningMarks($t))),
        ]));
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset($map[$candidate])) {
                return $map[$candidate];
            }
        }

        $v = strtolower($t);
        $allowed = array_keys(MembershipApplication::maritalStatusOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('marital_status must be one of: ' . implode(', ', $allowed) . " (got: {$value})");
    }

    /** Remove Arabic harakat / combining marks so spreadsheet variants still match. */
    private function stripArabicCombiningMarks(string $value): string
    {
        return preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value);
    }

    private function parseMonthlyIncome(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("monthly_income must be numeric (got: {$value})");
        }

        $n = (float) $value;

        if ($n < 0) {
            throw new \InvalidArgumentException('monthly_income cannot be negative.');
        }

        return number_format($n, 2, '.', '');
    }

    private function normalizeIban(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtoupper($value);
    }
}
