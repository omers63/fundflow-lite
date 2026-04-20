<?php

namespace App\Services;

use App\Models\FundTier;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class LoanImportService
{
    /**
     * Import loans from a UTF-8 CSV with a header row.
     *
     * Member lookup: provide one of member_email, member_number, or national_id.
     *
     * Column loan_status: pending | approved | active (default if omitted) | completed | early_settled
     *
     * Pending: no ledger. amount_requested required (or use amount_approved as the requested amount). fund_tier / queue left empty.
     * Approved: no ledger. amount_approved required; tiers and queue like an approved (not yet disbursed) loan.
     * Active: disbursed — member_portion + master_portion = amount_approved; ledger disbursement + optional bulk repayments for paid_installments_count.
     * Completed / early_settled: same ledger as active, but all installments are created as paid; bulk repayment uses total_amount_repaid if set, else installments_count × min_monthly_installment; status and settled_at updated at the end.
     *
     * @return array{created: int, failed: int, errors: array<int, string>}
     */
    public function import(string $absolutePath): array
    {
        $created = 0;
        $failed = 0;
        $errors = [];

        $rows = $this->parseAssociativeCsv($absolutePath);

        if ($rows === []) {
            return [
                'created' => 0,
                'failed' => 0,
                'errors' => ['The file is empty or has no data rows after the header.'],
            ];
        }

        $lineBase = 2;

        foreach ($rows as $index => $row) {
            $lineNumber = $lineBase + $index;

            try {
                $this->importRow($row);
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Row {$lineNumber}: {$e->getMessage()}";
            }
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row): void
    {
        Gate::authorize('create', Loan::class);

        $member = $this->resolveMember($row);
        $loanStatus = $this->parseLoanStatus($this->cell($row, 'loan_status'));

        match ($loanStatus) {
            'pending' => $this->importPending($row, $member),
            'approved' => $this->importApproved($row, $member),
            'active' => $this->importDisbursed($row, $member, 'active', false),
            'completed' => $this->importDisbursed($row, $member, 'completed', true),
            'early_settled' => $this->importDisbursed($row, $member, 'early_settled', true),
            default => throw new \InvalidArgumentException("Unsupported loan_status: {$loanStatus}"),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importPending(array $row, Member $member): void
    {
        $amountRequested = $this->parseAmountRequestedForPending($row);
        $amountApproved = $this->parseOptionalMoney($this->cell($row, 'amount_approved'), 'amount_approved');

        $purpose = $this->cell($row, 'purpose');
        if ($purpose === '') {
            $purpose = 'Imported loan';
        }

        $isEmergency = $this->parseBool($this->cell($row, 'is_emergency'));
        $loanTier = $this->resolveLoanTierOptional($row, $amountRequested, $isEmergency);

        $installmentsCount = $this->parseOptionalPositiveInt($this->cell($row, 'installments_count'), 12);

        $appliedAt = $this->parseOptionalDateTime($this->cell($row, 'applied_at')) ?? now();

        Loan::create([
            'member_id' => $member->id,
            'loan_tier_id' => $loanTier?->id,
            'fund_tier_id' => null,
            'queue_position' => null,
            'amount_requested' => $amountRequested,
            'amount_approved' => $amountApproved,
            'purpose' => $purpose,
            'installments_count' => $installmentsCount,
            'status' => 'pending',
            'applied_at' => $appliedAt,
            'is_emergency' => $isEmergency,
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importApproved(array $row, Member $member): void
    {
        $amount = $this->parseMoney($this->cell($row, 'amount_approved'), 'amount_approved');
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_approved must be positive.');
        }

        $purpose = $this->cell($row, 'purpose');
        if ($purpose === '') {
            $purpose = 'Imported loan';
        }

        $isEmergency = $this->parseBool($this->cell($row, 'is_emergency'));
        $loanTier = $this->resolveLoanTier($row, $amount, $isEmergency);
        $fundTier = $this->resolveFundTier($row, $loanTier, $isEmergency);

        $threshold = $this->parseThreshold($this->cell($row, 'settlement_threshold'));
        $minInstall = (float) ($loanTier?->min_monthly_installment ?? 1000);

        $count = $this->resolveInstallmentsCountForApproved($row, $amount, $member, $minInstall, $threshold);

        $amountRequested = $this->parseOptionalMoney($this->cell($row, 'amount_requested'), 'amount_requested') ?? $amount;

        $approvedAt = $this->parseOptionalDateTime($this->cell($row, 'approved_at')) ?? now();
        $appliedAt = $this->parseOptionalDateTime($this->cell($row, 'applied_at')) ?? $approvedAt;

        Loan::create([
            'member_id' => $member->id,
            'loan_tier_id' => $loanTier?->id,
            'fund_tier_id' => $fundTier->id,
            'queue_position' => null,
            'amount_requested' => $amountRequested,
            'amount_approved' => $amount,
            'purpose' => $purpose,
            'installments_count' => $count,
            'status' => 'approved',
            'applied_at' => $appliedAt,
            'approved_at' => $approvedAt,
            'approved_by_id' => auth()->id(),
            'settlement_threshold' => $threshold,
            'is_emergency' => $isEmergency,
        ]);

        LoanQueueOrderingService::resequenceFundTier($fundTier->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importDisbursed(
        array $row,
        Member $member,
        string $terminalStatus,
        bool $allPaid,
    ): void {
        $amount = $this->parseMoney($this->cell($row, 'amount_approved'), 'amount_approved');
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_approved must be positive.');
        }

        $memberPortion = $this->parseMoney($this->cell($row, 'member_portion'), 'member_portion');
        $masterPortion = $this->parseMoney($this->cell($row, 'master_portion'), 'master_portion');

        if ($memberPortion < 0 || $masterPortion < 0) {
            throw new \InvalidArgumentException('member_portion and master_portion cannot be negative.');
        }

        if (abs(($memberPortion + $masterPortion) - $amount) > 0.02) {
            throw new \InvalidArgumentException(
                'member_portion + master_portion must equal amount_approved (within 0.02 SAR).'
            );
        }

        $isEmergency = $this->parseBool($this->cell($row, 'is_emergency'));
        $loanTier = $this->resolveLoanTier($row, $amount, $isEmergency);
        $fundTier = $this->resolveFundTier($row, $loanTier, $isEmergency);

        $threshold = $this->parseThreshold($this->cell($row, 'settlement_threshold'));
        $minInstall = (float) ($loanTier?->min_monthly_installment ?? 1000);

        $count = $this->resolveInstallmentsCountDisbursed($row, $amount, $memberPortion, $minInstall, $threshold);

        if ($allPaid) {
            $paidCount = $count;
        } else {
            $paidCount = $this->parsePaidInstallmentsCount($row, $count);
        }

        $disbursedAt = $this->parseDisbursedAt($this->cell($row, 'disbursed_at'));
        $exemption = Loan::computeExemptionAndFirstRepayment($disbursedAt);
        $exemption = Loan::adjustFirstRepaymentIfContributionAlreadyMade($member, $exemption);

        $purpose = $this->cell($row, 'purpose');
        if ($purpose === '') {
            $purpose = 'Imported loan';
        }

        $totalRepaidCell = $this->cell($row, 'total_amount_repaid');
        if ($totalRepaidCell !== '') {
            $totalRepaid = round($this->parseMoney($totalRepaidCell, 'total_amount_repaid'), 2);
            if ($totalRepaid < 0) {
                throw new \InvalidArgumentException('total_amount_repaid cannot be negative.');
            }
        } else {
            $totalRepaid = round($paidCount * $minInstall, 2);
        }

        if ($allPaid && $totalRepaid <= 0) {
            throw new \InvalidArgumentException(
                'completed / early_settled loans need a positive repayment total (set total_amount_repaid or use installments_count × min monthly installment).'
            );
        }

        $amountRequested = $this->parseOptionalMoney($this->cell($row, 'amount_requested'), 'amount_requested') ?? $amount;

        $settledAt = ($terminalStatus === 'active')
            ? null
            : ($this->parseOptionalDateTime($this->cell($row, 'settled_at')) ?? $disbursedAt);

        $appliedAt = $this->parseOptionalDateTime($this->cell($row, 'applied_at')) ?? $disbursedAt;
        $approvedAt = $this->parseOptionalDateTime($this->cell($row, 'approved_at')) ?? $disbursedAt;

        $accounting = app(AccountingService::class);

        DB::transaction(function () use ($member, $loanTier, $fundTier, $amount, $amountRequested, $purpose, $count, $disbursedAt, $exemption, $threshold, $isEmergency, $memberPortion, $masterPortion, $accounting, $paidCount, $minInstall, $totalRepaid, $terminalStatus, $settledAt, $appliedAt, $approvedAt, ) {
            $loan = Loan::create([
                'member_id' => $member->id,
                'loan_tier_id' => $loanTier?->id,
                'fund_tier_id' => $fundTier->id,
                'queue_position' => null,
                'amount_requested' => $amountRequested,
                'amount_approved' => $amount,
                'purpose' => $purpose,
                'installments_count' => $count,
                'status' => 'active',
                'applied_at' => $appliedAt,
                'approved_at' => $approvedAt,
                'approved_by_id' => auth()->id(),
                'disbursed_at' => $disbursedAt,
                'due_date' => $disbursedAt->copy()->addMonths($count)->toDateString(),
                'exempted_month' => $exemption['exempted_month'],
                'exempted_year' => $exemption['exempted_year'],
                'first_repayment_month' => $exemption['first_repayment_month'],
                'first_repayment_year' => $exemption['first_repayment_year'],
                'settlement_threshold' => $threshold,
                'is_emergency' => $isEmergency,
            ]);

            $accounting->postLoanDisbursementWithPortions($loan, $memberPortion, $masterPortion);
            $loan->refresh();

            if ($totalRepaid > 0) {
                $accounting->postImportedLoanRepayments($loan, $totalRepaid);
                $loan->refresh();
            }

            $startDate = Carbon::create(
                $exemption['first_repayment_year'],
                $exemption['first_repayment_month'],
                5
            );

            for ($i = 1; $i <= $count; $i++) {
                $dueDate = $startDate->copy()->addMonths($i - 1);
                $isPaid = $i <= $paidCount;
                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $i,
                    'amount' => $minInstall,
                    'due_date' => $dueDate->toDateString(),
                    'paid_at' => $isPaid ? $dueDate->copy()->startOfDay() : null,
                    'status' => $isPaid ? 'paid' : 'pending',
                ]);
            }

            if ($terminalStatus !== 'active') {
                $loan->update([
                    'status' => $terminalStatus,
                    'settled_at' => $settledAt,
                ]);
            }
        });

        LoanQueueOrderingService::resequenceFundTier($fundTier->id);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveMember(array $row): Member
    {
        $email = strtolower($this->cell($row, 'member_email'));
        $number = $this->cell($row, 'member_number');
        $nationalId = $this->cell($row, 'national_id');

        if ($email === '' && $number === '' && $nationalId === '') {
            throw new \InvalidArgumentException('Provide member_email, member_number, or national_id.');
        }

        if ($email !== '') {
            $user = User::where('email', $email)->first();
            if ($user === null || $user->member === null) {
                throw new \InvalidArgumentException("No member found for email: {$email}");
            }

            return $user->member;
        }

        if ($number !== '') {
            $member = Member::where('member_number', $number)->first();
            if ($member === null) {
                throw new \InvalidArgumentException("No member found for member_number: {$number}");
            }

            return $member;
        }

        $members = Member::query()
            ->whereHas('membershipApplications', fn ($q) => $q->where('national_id', $nationalId))
            ->get();

        if ($members->isEmpty()) {
            throw new \InvalidArgumentException("No member found for national_id: {$nationalId}");
        }

        if ($members->count() > 1) {
            throw new \InvalidArgumentException(
                "Multiple members found for national_id: {$nationalId}. Use member_number or member_email."
            );
        }

        return $members->first();
    }

    private function parseLoanStatus(string $value): string
    {
        $v = strtolower(trim($value));

        if ($v === '') {
            return 'active';
        }

        if (in_array($v, ['pending', 'approved', 'active', 'completed', 'early_settled'], true)) {
            return $v;
        }

        throw new \InvalidArgumentException(
            'loan_status must be pending, approved, active, completed, or early_settled (got: ' . $value . ')'
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function parseAmountRequestedForPending(array $row): float
    {
        $req = $this->cell($row, 'amount_requested');
        $app = $this->cell($row, 'amount_approved');

        if ($req !== '') {
            return $this->parseMoney($req, 'amount_requested');
        }

        if ($app !== '') {
            return $this->parseMoney($app, 'amount_requested');
        }

        throw new \InvalidArgumentException('For pending loans, provide amount_requested or amount_approved.');
    }

    private function parseOptionalMoney(string $value, string $column): ?float
    {
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$column} must be numeric (got: {$value})");
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseAssociativeCsv(string $absolutePath): array
    {
        if (!is_readable($absolutePath)) {
            throw new \InvalidArgumentException('Cannot read the uploaded file.');
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \InvalidArgumentException('Cannot read the uploaded file.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

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

    private function parseMoney(string $value, string $column): float
    {
        if ($value === '') {
            throw new \InvalidArgumentException("{$column} is required.");
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$column} must be numeric (got: {$value})");
        }

        return round((float) $value, 2);
    }

    private function parseBool(string $value): bool
    {
        $v = strtolower($value);

        return in_array($v, ['1', 'true', 'yes', 'y'], true);
    }

    private function parseThreshold(string $value): float
    {
        if ($value === '') {
            return Setting::loanSettlementThreshold();
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("settlement_threshold must be numeric (got: {$value})");
        }

        $t = (float) $value;
        if ($t < 0 || $t > 1) {
            throw new \InvalidArgumentException('settlement_threshold must be between 0 and 1.');
        }

        return $t;
    }

    private function parseDisbursedAt(string $value): Carbon
    {
        if ($value === '') {
            return now();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid disbursed_at: {$value}");
        }
    }

    private function parseOptionalDateTime(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid date/time: {$value}");
        }
    }

    private function parseOptionalPositiveInt(string $value, int $default): int
    {
        if ($value === '') {
            return $default;
        }

        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('installments_count must be a positive integer.');
        }

        $n = (int) $value;

        return $n >= 1 ? $n : throw new \InvalidArgumentException('installments_count must be at least 1.');
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveInstallmentsCountForApproved(
        array $row,
        float $amount,
        Member $member,
        float $minInstall,
        float $threshold,
    ): int {
        $cell = $this->cell($row, 'installments_count');
        if ($cell !== '') {
            if (!ctype_digit($cell)) {
                throw new \InvalidArgumentException('installments_count must be a positive integer.');
            }
            $n = (int) $cell;

            return $n >= 1 ? $n : throw new \InvalidArgumentException('installments_count must be at least 1.');
        }

        $fundBal = (float) ($member->fundAccount()?->balance ?? 0);

        return Loan::computeInstallmentsCount($amount, $fundBal, $minInstall, $threshold);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveInstallmentsCountDisbursed(
        array $row,
        float $amount,
        float $memberPortion,
        float $minInstall,
        float $threshold,
    ): int {
        $cell = $this->cell($row, 'installments_count');
        if ($cell !== '') {
            if (!ctype_digit($cell)) {
                throw new \InvalidArgumentException('installments_count must be a positive integer.');
            }
            $n = (int) $cell;

            return $n >= 1 ? $n : throw new \InvalidArgumentException('installments_count must be at least 1.');
        }

        return Loan::computeInstallmentsCountFromPortions(
            $amount,
            $memberPortion,
            $minInstall,
            $threshold
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function parsePaidInstallmentsCount(array $row, int $count): int
    {
        $paidCell = $this->cell($row, 'paid_installments_count');
        if ($paidCell === '') {
            return 0;
        }

        if (!preg_match('/^\d+$/', $paidCell)) {
            throw new \InvalidArgumentException('paid_installments_count must be a non-negative integer.');
        }

        $paidCount = (int) $paidCell;
        if ($paidCount < 0 || $paidCount > $count) {
            throw new \InvalidArgumentException("paid_installments_count must be between 0 and {$count}.");
        }

        return $paidCount;
    }

    private function resolveLoanTier(array $row, float $amount, bool $isEmergency): ?LoanTier
    {
        $tierNum = $this->cell($row, 'loan_tier_number');
        if ($tierNum !== '') {
            if (!ctype_digit($tierNum)) {
                throw new \InvalidArgumentException('loan_tier_number must be a non-negative integer.');
            }
            $tier = LoanTier::where('tier_number', (int) $tierNum)->where('is_active', true)->first();
            if ($tier === null) {
                throw new \InvalidArgumentException("No active loan tier with tier_number {$tierNum}.");
            }

            return $tier;
        }

        if ($isEmergency) {
            return null;
        }

        $tier = LoanTier::forAmount($amount);
        if ($tier === null) {
            throw new \InvalidArgumentException('No active loan tier covers amount_approved; set loan_tier_number.');
        }

        return $tier;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveLoanTierOptional(array $row, float $amount, bool $isEmergency): ?LoanTier
    {
        $tierNum = $this->cell($row, 'loan_tier_number');
        if ($tierNum !== '') {
            if (!ctype_digit($tierNum)) {
                throw new \InvalidArgumentException('loan_tier_number must be a non-negative integer.');
            }
            $tier = LoanTier::where('tier_number', (int) $tierNum)->where('is_active', true)->first();
            if ($tier === null) {
                throw new \InvalidArgumentException("No active loan tier with tier_number {$tierNum}.");
            }

            return $tier;
        }

        if ($isEmergency) {
            return null;
        }

        return LoanTier::forAmount($amount);
    }

    private function resolveFundTier(array $row, ?LoanTier $loanTier, bool $isEmergency): FundTier
    {
        if ($isEmergency) {
            $emergency = FundTier::emergency();
            if ($emergency === null) {
                throw new \InvalidArgumentException('No active emergency fund tier configured.');
            }

            return $emergency;
        }

        $fundNum = $this->cell($row, 'fund_tier_number');
        if ($fundNum !== '') {
            if (!ctype_digit($fundNum)) {
                throw new \InvalidArgumentException('fund_tier_number must be a non-negative integer.');
            }
            $ft = FundTier::where('tier_number', (int) $fundNum)->where('is_active', true)->first();
            if ($ft === null) {
                throw new \InvalidArgumentException("No active fund tier with tier_number {$fundNum}.");
            }

            return $ft;
        }

        if ($loanTier === null) {
            throw new \InvalidArgumentException('Cannot resolve fund tier: set loan_tier_number or is_emergency=1.');
        }

        $ft = FundTier::forLoanTier($loanTier->id);
        if ($ft === null) {
            throw new \InvalidArgumentException('No active fund tier for this loan tier; set fund_tier_number.');
        }

        return $ft;
    }
}
