<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\ImportIdempotencyLedger;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ContributionImportService
{
    /**
     * Import contributions from a UTF-8 CSV file with a header row.
     *
     * One of member_id, member_number, national_id, or member_name (or name) is required per row.
     * Required: month, year, amount
     * Optional: paid_at (defaults to now), reference_number, notes, is_late (0/1 yes/no), late_fee_amount (SAR),
     * payment_method (empty = admin entry; otherwise a key from Finance contribution sources)
     *
     * @return array{created: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function import(string $absolutePath): array
    {
        Gate::authorize('create', Contribution::class);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

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
        $fileFingerprint = is_readable($absolutePath) ? (sha1_file($absolutePath) ?: sha1($absolutePath)) : sha1($absolutePath);

        foreach ($rows as $index => $row) {
            $lineNumber = $lineBase + $index;

            if ($this->isRowEmpty($row)) {
                $skipped++;

                continue;
            }

            try {
                $result = $this->importRow($row, [
                    'lineNumber' => $lineNumber,
                    'fileFingerprint' => $fileFingerprint,
                ]);
                if ($result === 'created') {
                    $created++;
                } else {
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
     * @param  array<string, string>  $row
     * @param  array{lineNumber:int, fileFingerprint:string}  $importMeta
     */
    private function importRow(array $row, array $importMeta): string
    {
        if (Setting::autoAllocateLoanRepaymentImportEnabled()) {
            return $this->importRowWithAutoAllocation($row, $importMeta);
        }

        $member = $this->resolveMember($row);
        $month = $this->parseMonth($this->cell($row, 'month'));
        $year = $this->parseYear($this->cell($row, 'year'));
        $amount = $this->parseAmount($this->cell($row, 'amount'));

        if ($amount <= 0.00001) {
            return 'skipped';
        }

        if (Contribution::activePeriodExists((int) $member->id, $month, $year)) {
            throw new \InvalidArgumentException(
                Contribution::duplicateCycleMessage($month, $year)
            );
        }

        $paidAt = $this->parsePaidAt($this->cell($row, 'paid_at'));

        $isLate = $this->parseIsLate($this->cell($row, 'is_late'));
        $lateFeeCell = $this->cell($row, 'late_fee_amount');
        $lateFeeAmount = null;
        if ($lateFeeCell !== '') {
            $lateFeeAmount = $this->parseAmount($lateFeeCell);
        } elseif ($isLate) {
            $fee = app(ContributionCycleService::class)->lateFeeForContributionPeriod($month, $year, $paidAt);
            $lateFeeAmount = $fee > 0 ? $fee : null;
        }

        Contribution::create([
            'member_id' => $member->id,
            'month' => $month,
            'year' => $year,
            'amount' => $amount,
            'paid_at' => $paidAt,
            'payment_method' => $this->parsePaymentMethod($this->cell($row, 'payment_method')),
            'reference_number' => $this->nullableString($this->cell($row, 'reference_number')),
            'notes' => $this->nullableString($this->cell($row, 'notes')),
            'is_late' => $isLate,
            'late_fee_amount' => $lateFeeAmount,
        ]);

        return 'created';
    }

    /**
     * Auto-allocation mode (feature flag):
     * 1) repayment due in target cycle (including late fee at paid_at)
     * 2) contribution for target cycle
     * 3) optional unapplied credit to member cash
     *
     * @param  array<string, string>  $row
     * @param  array{lineNumber:int, fileFingerprint:string}  $importMeta
     */
    private function importRowWithAutoAllocation(array $row, array $importMeta): string
    {
        $member = $this->resolveMember($row);
        $month = $this->parseMonth($this->cell($row, 'month'));
        $year = $this->parseYear($this->cell($row, 'year'));
        $paidAt = $this->parsePaidAt($this->cell($row, 'paid_at'));
        $totalPaid = $this->parseAmount($this->cell($row, 'amount'));

        if ($totalPaid <= 0.00001) {
            return 'skipped';
        }

        $strictMode = $this->parseBoolWithDefault(
            $this->cell($row, 'strict_mode'),
            (bool) Setting::get('feature.auto_allocate_loan_repayment.strict_mode_default', false)
        );
        $allowUnappliedCredit = (bool) Setting::get('feature.auto_allocate_loan_repayment.allow_unapplied_credit', true);
        $idempotencyScope = (string) Setting::get(
            'feature.auto_allocate_loan_repayment.idempotency_scope',
            'file_line_member_paid_at_total_paid'
        );
        $remaining = round($totalPaid, 2);
        $didPost = false;
        $ledger = null;

        if ($idempotencyScope !== '' && strtolower($idempotencyScope) !== 'none') {
            $ledger = $this->claimIdempotency(
                scope: $idempotencyScope,
                member: $member,
                paidAt: $paidAt,
                totalPaid: $totalPaid,
                importMeta: $importMeta
            );
            if ($ledger === null) {
                return 'skipped';
            }
        }

        try {
            DB::transaction(function () use ($row, $member, $month, $year, $paidAt, $totalPaid, $strictMode, $allowUnappliedCredit, &$remaining, &$didPost): void {
                $loan = Loan::active()->where('member_id', $member->id)->first();
                if ($loan !== null) {
                    $loanRepaymentService = app(LoanRepaymentService::class);
                    $lateFeeService = app(LateFeeService::class);
                    $installment = $loanRepaymentService->installmentForPeriod($loan, $month, $year);

                    if ($installment !== null && !$installment->isPaid()) {
                        $deadline = $loanRepaymentService->deadline($month, $year);
                        $days = $lateFeeService->daysPastDue($deadline, Carbon::instance($paidAt));
                        $lateFee = $lateFeeService->repaymentLateFeeForDays($days);
                        $repaymentDue = round((float) $installment->amount + $lateFee, 2);

                        if ($repaymentDue > 0.00001) {
                            if ($remaining + 0.00001 < $repaymentDue) {
                                if ($strictMode) {
                                    throw new \InvalidArgumentException(
                                        "Insufficient payment to cover repayment due for {$month}/{$year}. Required: {$repaymentDue}, received: {$remaining}."
                                    );
                                }
                            } else {
                                app(AccountingService::class)->creditMemberCashFromImportReceipt(
                                    $member,
                                    $repaymentDue,
                                    "Repayment import receipt — {$month}/{$year}",
                                    $paidAt
                                );

                                $outcome = $loanRepaymentService->applyImportedInstallmentPayment($installment, $paidAt, $lateFee);
                                if ($outcome !== 'applied') {
                                    throw new \RuntimeException('Repayment posting could not be completed.');
                                }

                                $remaining = round($remaining - $repaymentDue, 2);
                                $didPost = true;
                            }
                        }
                    }
                }

                if ($remaining > 0.00001) {
                    if (!Contribution::activePeriodExists((int) $member->id, $month, $year)) {
                        $isLate = $this->parseIsLate($this->cell($row, 'is_late'));
                        $lateFeeCell = $this->cell($row, 'late_fee_amount');
                        $lateFeeAmount = null;
                        if ($lateFeeCell !== '') {
                            $lateFeeAmount = $this->parseAmount($lateFeeCell);
                        } elseif ($isLate) {
                            $fee = app(ContributionCycleService::class)->lateFeeForContributionPeriod($month, $year, $paidAt);
                            $lateFeeAmount = $fee > 0 ? $fee : null;
                        }

                        Contribution::create([
                            'member_id' => $member->id,
                            'month' => $month,
                            'year' => $year,
                            'amount' => $remaining,
                            'paid_at' => $paidAt,
                            'payment_method' => Contribution::PAYMENT_METHOD_IMPORT_CSV,
                            'reference_number' => $this->nullableString($this->cell($row, 'reference_number')),
                            'notes' => $this->appendAutoAllocationNote($this->nullableString($this->cell($row, 'notes')), $totalPaid),
                            'is_late' => $isLate,
                            'late_fee_amount' => $lateFeeAmount,
                        ]);
                        $didPost = true;
                        $remaining = 0.0;
                    }
                }

                if ($remaining > 0.00001) {
                    if (!$allowUnappliedCredit || $strictMode) {
                        throw new \InvalidArgumentException(
                            "Unapplied amount {$remaining} remains for {$month}/{$year} and unapplied credits are disabled."
                        );
                    }

                    app(AccountingService::class)->creditMemberCashFromImportReceipt(
                        $member,
                        $remaining,
                        "Unapplied import credit — {$month}/{$year}",
                        $paidAt
                    );
                    $didPost = true;
                    $remaining = 0.0;
                }
            });
        } catch (Throwable $e) {
            if ($ledger instanceof ImportIdempotencyLedger) {
                $ledger->delete();
            }
            throw $e;
        }

        if ($ledger instanceof ImportIdempotencyLedger) {
            $ledger->update(['processed_at' => now()]);
        }

        return $didPost ? 'created' : 'skipped';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveMember(array $row): Member
    {
        $idRaw = $this->cell($row, 'member_id');
        if ($idRaw !== '') {
            if (!ctype_digit($idRaw)) {
                throw new \InvalidArgumentException('member_id must be a positive integer.');
            }

            $member = Member::query()->find((int) $idRaw);
            if ($member === null) {
                throw new \InvalidArgumentException("No member with id {$idRaw}.");
            }

            return $member;
        }

        $numRaw = $this->cell($row, 'member_number');
        if ($numRaw !== '') {
            $member = Member::query()->where('member_number', $numRaw)->first();
            if ($member === null) {
                throw new \InvalidArgumentException("No member with member_number {$numRaw}.");
            }

            return $member;
        }

        $nationalIdRaw = $this->cell($row, 'national_id');
        if ($nationalIdRaw !== '') {
            $members = Member::query()
                ->whereHas('membershipApplications', fn($q) => $q->where('national_id', $nationalIdRaw))
                ->get();

            if ($members->isEmpty()) {
                throw new \InvalidArgumentException("No member with national_id {$nationalIdRaw}.");
            }

            if ($members->count() > 1) {
                throw new \InvalidArgumentException(
                    "Multiple members match national_id {$nationalIdRaw}. Use member_id or member_number instead."
                );
            }

            return $members->first();
        }

        $nameRaw = $this->cell($row, 'member_name');
        if ($nameRaw === '') {
            $nameRaw = $this->cell($row, 'name');
        }

        if ($nameRaw !== '') {
            $members = Member::query()
                ->whereHas('user', fn($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($nameRaw)]))
                ->with('user')
                ->get();

            if ($members->isEmpty()) {
                throw new \InvalidArgumentException("No member with name {$nameRaw}.");
            }

            if ($members->count() > 1) {
                throw new \InvalidArgumentException(
                    "Multiple members match name {$nameRaw}. Use member_id, member_number, or national_id instead."
                );
            }

            return $members->first();
        }

        throw new \InvalidArgumentException('member_id, member_number, national_id, or member_name is required.');
    }

    private function parseMonth(string $value): int
    {
        if ($value === '') {
            throw new \InvalidArgumentException('month is required.');
        }

        if (ctype_digit($value)) {
            $m = (int) $value;
            if ($m >= 1 && $m <= 12) {
                return $m;
            }

            throw new \InvalidArgumentException("month must be 1–12 (got: {$value})");
        }

        $v = strtolower(trim($value));

        for ($m = 1; $m <= 12; $m++) {
            $full = strtolower(date('F', mktime(0, 0, 0, $m, 1)));
            $short = strtolower(date('M', mktime(0, 0, 0, $m, 1)));
            if ($v === $full || $v === $short) {
                return $m;
            }
        }

        throw new \InvalidArgumentException("Invalid month: {$value}");
    }

    private function parseYear(string $value): int
    {
        if ($value === '' || !ctype_digit($value)) {
            throw new \InvalidArgumentException('year must be a four-digit integer.');
        }

        $y = (int) $value;

        if ($y < 2000 || $y > 2100) {
            throw new \InvalidArgumentException("year must be between 2000 and 2100 (got: {$y})");
        }

        return $y;
    }

    private function parseAmount(string $value): float
    {
        if ($value === '' || !is_numeric($value)) {
            throw new \InvalidArgumentException('amount is required and must be numeric.');
        }

        $amount = round((float) $value, 2);

        if ($amount < 0) {
            throw new \InvalidArgumentException('amount cannot be negative.');
        }

        return $amount;
    }

    private function parsePaidAt(string $value): Carbon
    {
        if ($value === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid paid_at: {$value}");
        }
    }

    private function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function parseIsLate(string $value): bool
    {
        $v = strtolower(trim($value));

        if ($v === '' || $v === '0' || $v === 'no' || $v === 'n' || $v === 'false') {
            return false;
        }

        if ($v === '1' || $v === 'yes' || $v === 'y' || $v === 'true') {
            return true;
        }

        throw new \InvalidArgumentException('is_late must be empty, 0/1, yes/no, or true/false.');
    }

    private function parseBoolWithDefault(string $value, bool $default): bool
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['1', 'yes', 'y', 'true'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'no', 'n', 'false'], true)) {
            return false;
        }

        throw new \InvalidArgumentException('strict_mode must be empty, 0/1, yes/no, or true/false.');
    }

    private function appendAutoAllocationNote(?string $existing, float $totalPaid): string
    {
        $base = trim((string) ($existing ?? ''));
        $suffix = "Auto-allocated from import total payment SAR " . number_format($totalPaid, 2);

        return $base === '' ? $suffix : "{$base}\n{$suffix}";
    }

    /**
     * @param  array{lineNumber:int, fileFingerprint:string}  $importMeta
     */
    private function claimIdempotency(
        string $scope,
        Member $member,
        Carbon $paidAt,
        float $totalPaid,
        array $importMeta,
    ): ?ImportIdempotencyLedger {
        $scope = trim($scope);
        if ($scope === '' || strtolower($scope) === 'none') {
            return null;
        }

        $key = match ($scope) {
            'member_paid_at_total_paid' => sha1(implode('|', [
                'member_paid_at_total_paid',
                (string) $member->id,
                $paidAt->copy()->utc()->format('Y-m-d H:i:s'),
                number_format($totalPaid, 2, '.', ''),
            ])),
            default => sha1(implode('|', [
                'file_line_member_paid_at_total_paid',
                $importMeta['fileFingerprint'],
                (string) $importMeta['lineNumber'],
                (string) $member->id,
                $paidAt->copy()->utc()->format('Y-m-d H:i:s'),
                number_format($totalPaid, 2, '.', ''),
            ])),
        };

        try {
            return ImportIdempotencyLedger::create([
                'scope' => $scope,
                'idempotency_key' => $key,
                'file_fingerprint' => $importMeta['fileFingerprint'],
                'member_id' => $member->id,
                'line_number' => $importMeta['lineNumber'],
                'context' => [
                    'paid_at' => $paidAt->toDateTimeString(),
                    'total_paid' => number_format($totalPaid, 2, '.', ''),
                ],
            ]);
        } catch (QueryException $e) {
            $message = strtolower($e->getMessage());
            $isDuplicate = str_contains($message, 'unique') || str_contains($message, 'duplicate');
            if ($isDuplicate) {
                return null;
            }
            throw $e;
        }
    }

    private function parsePaymentMethod(string $value): string
    {
        $raw = strtolower(trim($value));

        if ($raw === '') {
            return Contribution::PAYMENT_METHOD_IMPORT_CSV;
        }

        $options = Contribution::paymentMethodOptions();

        if (isset($options[$raw])) {
            return $raw;
        }

        throw new \InvalidArgumentException(
            'payment_method must be one of: ' . implode(', ', array_keys($options)) . " (got: {$value})"
        );
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
}
