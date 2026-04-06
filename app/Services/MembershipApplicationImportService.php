<?php

namespace App\Services;

use App\Models\MembershipApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MembershipApplicationImportService
{
    /**
     * Import pending membership applications from a UTF-8 CSV file with a header row.
     *
     * Required: name, email, national_id, date_of_birth, city, address, mobile_phone, next_of_kin_name, next_of_kin_phone
     * Optional: password (≥8 chars overrides default), application_type, gender, marital_status, membership_date,
     * home_phone, work_phone, work_place, residency_place, occupation, employer, monthly_income, bank_account_number, iban
     *
     * Existing email with a membership application: skipped. Existing member (user has member record): error row.
     *
     * @return array{created: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function import(string $absolutePath, string $defaultPassword): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        if (strlen($defaultPassword) < 8) {
            return [
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['Default password must be at least 8 characters.'],
            ];
        }

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

        foreach ($rows as $index => $row) {
            $lineNumber = $lineBase + $index;

            try {
                $result = $this->importRow($row, $defaultPassword);

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
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

    /**
     * @return 'created'|'skipped'
     */
    private function importRow(array $row, string $defaultPassword): string
    {
        $name = trim((string) $this->cell($row, 'name'));
        $email = strtolower($this->cell($row, 'email'));

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

        $nationalId = $this->cell($row, 'national_id');
        if ($nationalId === '') {
            throw new \InvalidArgumentException('national_id is required.');
        }

        $dobRaw = $this->cell($row, 'date_of_birth');
        if ($dobRaw === '') {
            throw new \InvalidArgumentException('date_of_birth is required.');
        }
        $dateOfBirth = $this->parseDateRequired($dobRaw, 'date_of_birth');
        if ($dateOfBirth > now()->toDateString()) {
            throw new \InvalidArgumentException('date_of_birth cannot be in the future.');
        }

        $city = $this->cell($row, 'city');
        if ($city === '') {
            throw new \InvalidArgumentException('city is required.');
        }

        $address = trim((string) ($this->cell($row, 'address')));
        if ($address === '') {
            throw new \InvalidArgumentException('address is required.');
        }

        $mobile = $this->cell($row, 'mobile_phone');
        if ($mobile === '') {
            throw new \InvalidArgumentException('mobile_phone is required.');
        }

        $nokName = $this->cell($row, 'next_of_kin_name');
        if ($nokName === '') {
            throw new \InvalidArgumentException('next_of_kin_name is required.');
        }

        $nokPhone = $this->cell($row, 'next_of_kin_phone');
        if ($nokPhone === '') {
            throw new \InvalidArgumentException('next_of_kin_phone is required.');
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            if ($existingUser->member !== null) {
                throw new \InvalidArgumentException("User is already a member: {$email}");
            }

            if ($existingUser->membershipApplication !== null) {
                return 'skipped';
            }

            throw new \InvalidArgumentException("Email already registered without an application: {$email}");
        }

        Gate::authorize('create', MembershipApplication::class);

        $password = $this->cell($row, 'password');
        $plain = (strlen($password) >= 8) ? $password : $defaultPassword;

        $applicationType = $this->normalizeApplicationType($this->cell($row, 'application_type'));
        $gender = $this->normalizeGender($this->nullableCell($row, 'gender'));
        $maritalStatus = $this->normalizeMaritalStatus($this->nullableCell($row, 'marital_status'));
        $membershipDate = $this->parseDateOptional($this->cell($row, 'membership_date'));
        $monthlyIncome = $this->parseMonthlyIncome($this->cell($row, 'monthly_income'));

        $optionalString = static fn (?string $v): ?string => ($v !== null && $v !== '') ? $v : null;

        $occupation = $optionalString($this->cell($row, 'occupation'));
        $employer = $optionalString($this->cell($row, 'employer'));

        DB::transaction(function () use ($name, $email, $mobile, $plain, $applicationType, $gender, $maritalStatus, $nationalId, $dateOfBirth, $address, $city, $optionalString, $row, $membershipDate, $nokName, $nokPhone, $monthlyIncome, $occupation, $employer) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $mobile,
                'role' => 'member',
                'status' => 'pending',
                'password' => Hash::make($plain),
            ]);

            MembershipApplication::create([
                'user_id' => $user->id,
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
                'bank_account_number' => $optionalString($this->nullableCell($row, 'bank_account_number')),
                'iban' => $this->normalizeIban($this->nullableCell($row, 'iban')),
                'membership_date' => $membershipDate,
                'next_of_kin_name' => $nokName,
                'next_of_kin_phone' => $nokPhone,
                'application_form_path' => null,
                'status' => 'pending',
            ]);
        });

        return 'created';
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

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) $l) !== ''));

        if (count($lines) < 2) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv((string) $headerLine);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        $rows = [];

        foreach ($lines as $line) {
            $cells = str_getcsv((string) $line);
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

    private function parseDateRequired(string $value, string $column): string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid {$column}: {$value}");
        }
    }

    private function parseDateOptional(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid membership_date: {$value}");
        }
    }

    private function normalizeApplicationType(string $value): string
    {
        $v = strtolower(trim($value));

        if ($v === '') {
            return 'new';
        }

        $allowed = array_keys(MembershipApplication::applicationTypeOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('application_type must be one of: '.implode(', ', $allowed)." (got: {$value})");
    }

    private function normalizeGender(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = strtolower(trim($value));
        $allowed = array_keys(MembershipApplication::genderOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('gender must be one of: '.implode(', ', $allowed)." (got: {$value})");
    }

    private function normalizeMaritalStatus(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = strtolower(trim($value));
        $allowed = array_keys(MembershipApplication::maritalStatusOptions());
        if (in_array($v, $allowed, true)) {
            return $v;
        }

        throw new \InvalidArgumentException('marital_status must be one of: '.implode(', ', $allowed)." (got: {$value})");
    }

    private function parseMonthlyIncome(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
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
