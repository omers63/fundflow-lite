<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MemberImportService
{
    /**
     * Import members from a UTF-8 CSV file with a header row.
     *
     * Required columns: name, email
     * Optional: password, phone, joined_at, status, monthly_contribution_amount, parent_member_number,
     * cash_balance (SAR >= 0), fund_balance (SAR; may be negative — paired debit/credit with master fund).
     *
     * If the email already exists: updates balances only when cash_balance > 0 or fund_balance ≠ 0;
     * requires an existing Member and Update:Member permission. Other columns are ignored for that row.
     *
     * Per-row password overrides the default when present and at least 8 characters (new members only).
     *
     * @return array{created: int, updated: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function import(string $absolutePath, string $defaultPassword): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        if (strlen($defaultPassword) < 8) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['Default password must be at least 8 characters.'],
            ];
        }

        $rows = $this->parseAssociativeCsv($absolutePath);

        if ($rows === []) {
            return [
                'created' => 0,
                'updated' => 0,
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
                } elseif ($result === 'updated') {
                    $updated++;
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
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @return 'created'|'updated'|'skipped'
     */
    private function importRow(array $row, string $defaultPassword): string
    {
        $name = $this->cell($row, 'name');
        $email = strtolower($this->cell($row, 'email'));

        if ($email === '') {
            throw new \InvalidArgumentException('email is required.');
        }

        $v = Validator::make(['email' => $email], ['email' => 'required|email']);
        if ($v->fails()) {
            throw new \InvalidArgumentException('Invalid email address.');
        }

        $cashBalance = $this->parseCashBalance($this->cell($row, 'cash_balance'), 'cash_balance');
        $fundBalance = $this->parseFundBalance($this->cell($row, 'fund_balance'), 'fund_balance');

        $existingUser = User::where('email', $email)->first();

        if ($existingUser !== null) {
            $member = $existingUser->member;
            if ($member === null) {
                throw new \InvalidArgumentException("User exists but has no member record: {$email}");
            }

            Gate::authorize('update', $member);

            if ($cashBalance <= 0 && abs($fundBalance) < 0.00001) {
                return 'skipped';
            }

            DB::transaction(function () use ($member, $cashBalance, $fundBalance) {
                app(AccountingService::class)->applyImportedBalanceAdjustments($member, $cashBalance, $fundBalance);
            });

            return 'updated';
        }

        if ($name === '') {
            throw new \InvalidArgumentException('name is required for new members.');
        }

        Gate::authorize('create', Member::class);

        $password = $this->cell($row, 'password');
        $plain = (strlen($password) >= 8) ? $password : $defaultPassword;

        $phone = $this->nullableCell($row, 'phone');
        $joinedAt = $this->parseDate($this->cell($row, 'joined_at')) ?? now()->toDateString();
        $status = $this->normalizeStatus($this->cell($row, 'status'));
        $contribution = $this->parseContribution($this->cell($row, 'monthly_contribution_amount'));

        $parentId = null;
        $parentNumber = $this->nullableCell($row, 'parent_member_number');
        if ($parentNumber !== null && $parentNumber !== '') {
            $parent = Member::where('member_number', $parentNumber)->first();
            if (!$parent) {
                throw new \InvalidArgumentException("Parent member number not found: {$parentNumber}");
            }
            if ($parent->parent_id !== null) {
                throw new \InvalidArgumentException("Parent {$parentNumber} is not an independent member (has a sponsor).");
            }
            $parentId = $parent->id;
        }

        DB::transaction(function () use ($name, $email, $phone, $plain, $joinedAt, $status, $contribution, $parentId, $cashBalance, $fundBalance) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($plain),
                'role' => 'member',
                'status' => 'approved',
            ]);

            $memberNumber = app(MemberNumberService::class)->generate();

            $member = Member::create([
                'user_id' => $user->id,
                'member_number' => $memberNumber,
                'joined_at' => $joinedAt,
                'status' => $status,
                'monthly_contribution_amount' => $contribution,
                'parent_id' => $parentId,
            ]);

            app(AccountingService::class)->ensureMemberAccounts($member);

            app(AccountingService::class)->applyImportedBalanceAdjustments($member, $cashBalance, $fundBalance);
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
        $lines = array_values(array_filter($lines, fn($l) => trim((string) $l) !== ''));

        if (count($lines) < 2) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv((string) $headerLine);
        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $headers);

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

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid date: {$value}");
        }
    }

    private function normalizeStatus(string $value): string
    {
        $v = strtolower($value);

        if ($v === '') {
            return 'active';
        }

        if (in_array($v, ['active', 'suspended', 'delinquent', 'terminated'], true)) {
            return $v;
        }

        throw new \InvalidArgumentException("status must be active, suspended, delinquent, or terminated (got: {$value})");
    }

    private function parseContribution(string $value): int
    {
        if ($value === '') {
            return 500;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("monthly_contribution_amount must be numeric (got: {$value})");
        }

        $int = (int) $value;

        if (!Member::isValidContributionAmount($int)) {
            throw new \InvalidArgumentException(
                'monthly_contribution_amount must be 500–3000 in steps of 500 (got: ' . $int . ')'
            );
        }

        return $int;
    }

    private function parseCashBalance(string $value, string $column): float
    {
        if ($value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$column} must be numeric (got: {$value})");
        }

        $amount = round((float) $value, 2);

        if ($amount < 0) {
            throw new \InvalidArgumentException("{$column} cannot be negative (got: {$value})");
        }

        return $amount;
    }

    private function parseFundBalance(string $value, string $column): float
    {
        if ($value === '') {
            return 0.0;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$column} must be numeric (got: {$value})");
        }

        return round((float) $value, 2);
    }
}
