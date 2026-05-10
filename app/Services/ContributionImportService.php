<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\FundTier;
use App\Models\ImportIdempotencyLedger;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTier;
use App\Models\Member;
use App\Models\Setting;
use App\Services\LoanQueueOrderingService;
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
        $isSimplifiedMixedImport = $this->isSimplifiedMixedImportSchema($rows);
        $memberLoanState = [];

        if (!$isSimplifiedMixedImport) {
            foreach ($rows as $index => $row) {
                $rawAmount = trim((string) ($row['amount'] ?? ''));
                if ($rawAmount !== '' && is_numeric($rawAmount) && (float) $rawAmount < 0) {
                    $lineNumber = $lineBase + $index;
                    $failed++;
                    $errors[] = "Row {$lineNumber}: negative amount detected, but CSV is not in mixed loan/contribution format. Use headers: member name, month, year, amount, paid_at, guarantor, check#.";
                }
            }
        }

        foreach ($rows as $index => $row) {
            $lineNumber = $lineBase + $index;

            if ($this->isRowEmpty($row)) {
                $skipped++;

                continue;
            }

            try {
                if ($isSimplifiedMixedImport) {
                    $result = $this->importRowFromSimplifiedMixedCsv($row, $memberLoanState);
                } else {
                    $result = $this->importRow($row, [
                        'lineNumber' => $lineNumber,
                        'fileFingerprint' => $fileFingerprint,
                    ]);
                }
                if ($result === 'created') {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Row {$lineNumber}: {$e->getMessage()}";
                logger()->warning('ContributionImportService row failed', [
                    'line' => $lineNumber,
                    'error' => $e->getMessage(),
                    'row' => $row,
                    'mixed_schema' => $isSimplifiedMixedImport,
                ]);
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
     * @param  array<int, array<string, string>>  $rows
     */
    private function isSimplifiedMixedImportSchema(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $keys = array_map('strtolower', array_keys($rows[0]));
        $hasMemberName = in_array('member name', $keys, true)
            || in_array('member_name', $keys, true)
            || in_array('membername', $keys, true)
            || in_array('name', $keys, true);

        return $hasMemberName
            && in_array('amount', $keys, true)
            && in_array('month', $keys, true)
            && in_array('year', $keys, true);
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

        if (Contribution::hasPaidScheduledLoanInstallmentOnActiveLoan((int) $member->id, $month, $year)) {
            throw new \InvalidArgumentException(Contribution::scheduledRepaymentPrecludesContributionMessage($month, $year));
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
     * @param  array<string, string>  $row
     * @param  array<int, Loan>  $memberLoanState
     */
    private function importRowFromSimplifiedMixedCsv(array $row, array &$memberLoanState): string
    {
        $memberName = $this->firstNonEmptyCell($row, ['member name', 'member_name', 'membername', 'name']);
        if ($memberName === '') {
            throw new \InvalidArgumentException('member name is required.');
        }

        $member = $this->resolveSingleMemberByName(
            $memberName,
            "No member found for member name: {$memberName}",
            "Multiple members found for member name: {$memberName}. Use unique naming or import with identifier-based CSV.",
        );
        $month = $this->parseMonth($this->cell($row, 'month'));
        $year = $this->parseYear($this->cell($row, 'year'));
        $paidAt = $this->parsePaidAt($this->cell($row, 'paid_at'));
        $amount = $this->parseSignedAmount($this->cell($row, 'amount'));
        $checkNumber = $this->firstNonEmptyCell($row, ['check#', 'check_number', 'check_no', 'check']);
        $guarantorName = $this->firstNonEmptyCell($row, ['guarantor', 'guarantor_name']);
        $loanHint = strtolower($this->firstNonEmptyCell($row, ['loan', 'is_loan', 'loan_flag', 'type', 'row_type']));

        if (abs($amount) <= 0.00001) {
            return 'skipped';
        }

        if (
            $amount > 0
            && in_array($loanHint, ['1', 'yes', 'y', 'true', 'loan', 'disbursement'], true)
        ) {
            throw new \InvalidArgumentException(
                'Loan row was marked, but amount is positive. In mixed import, loan disbursement rows must use a negative amount (e.g. -80000).'
            );
        }

        if ($amount < 0) {
            $principal = round(abs($amount), 2);
            $guarantor = null;
            if ($guarantorName !== '') {
                $guarantor = $this->resolveSingleMemberByName(
                    $guarantorName,
                    "No member found for guarantor '{$guarantorName}'.",
                    "Multiple members found for guarantor '{$guarantorName}'. Use a unique name.",
                );
            }

            $threshold = Setting::loanSettlementThreshold();
            $loanTier = LoanTier::forAmount($principal);
            if ($loanTier === null) {
                throw new \InvalidArgumentException(
                    'No active loan tier covers this disbursement amount; configure loan tiers or use the dedicated loan import with loan_tier_number.'
                );
            }
            $fundTier = FundTier::query()
                ->where('loan_tier_id', $loanTier->id)
                ->where('is_active', true)
                ->first();
            if ($fundTier === null) {
                throw new \InvalidArgumentException(
                    'No active fund tier is linked to the loan tier for this amount; link fund tiers to loan tiers in settings or use the loan CSV with fund_tier_number.'
                );
            }
            $minInstall = (float) ($loanTier->min_monthly_installment ?? 1000);
            if (Setting::importLoanBlankPortionsUseFiftyFiftySplit()) {
                $memberPortionStored = round($principal / 2, 2);
                $masterPortionStored = round($principal - $memberPortionStored, 2);
                $installmentsCount = Loan::computeInstallmentsCountFromPortions($principal, $memberPortionStored, $minInstall, $threshold);
            } else {
                $fundBal = (float) ($member->fundAccount()?->balance ?? 0);
                $memberPortionStored = round(min(max(0.0, $fundBal), $principal), 2);
                $masterPortionStored = round($principal - $memberPortionStored, 2);
                $installmentsCount = Loan::computeInstallmentsCount($principal, $fundBal, $minInstall, $threshold);
            }
            $exemption = Loan::computeExemptionAndFirstRepayment($paidAt, true);
            $exemption = Loan::finalizeExemptionForDisbursement($member, $exemption, $paidAt);

            $loan = Loan::create([
                'member_id' => $member->id,
                'loan_tier_id' => $loanTier->id,
                'fund_tier_id' => $fundTier->id,
                'queue_position' => null,
                'amount_requested' => $principal,
                'amount_approved' => $principal,
                'purpose' => 'Imported from mixed contributions/repayments CSV',
                'installments_count' => $installmentsCount,
                'status' => 'active',
                'applied_at' => $paidAt,
                'approved_at' => $paidAt,
                'approved_by_id' => auth()->id(),
                'disbursed_at' => $paidAt,
                'due_date' => $paidAt->copy()->addMonths($installmentsCount)->toDateString(),
                'settlement_threshold' => $threshold,
                'guarantor_member_id' => $guarantor?->id,
                'has_grace_cycle' => true,
                'is_emergency' => false,
                'member_portion' => $memberPortionStored,
                'master_portion' => $masterPortionStored,
            ] + $exemption);

            $checkSuffix = $checkNumber === '' ? null : 'check# ' . $checkNumber;
            app(AccountingService::class)->postLoanDisbursementWithPortions(
                $loan,
                $memberPortionStored,
                $masterPortionStored,
                $paidAt,
                $checkSuffix,
                allowNegativeMasterFundBalance: true,
                mirrorFullFundDebits: true,
            );

            $startDate = Carbon::create(
                $exemption['first_repayment_year'],
                $exemption['first_repayment_month'],
                5
            );
            for ($i = 1; $i <= $installmentsCount; $i++) {
                $dueDate = $startDate->copy()->addMonths($i - 1);
                LoanInstallment::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $i,
                    'amount' => $minInstall,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'pending',
                ]);
            }

            LoanQueueOrderingService::resequenceFundTier($fundTier->id);
            $memberLoanState[(int) $member->id] = $loan->fresh();

            return 'created';
        }

        $loan = $memberLoanState[(int) $member->id] ?? $this->resolveLoanForMixedRepayment($member);

        if ($loan instanceof Loan) {
            $repaymentAmount = round($amount, 2);

            DB::transaction(function () use ($member, $loan, $repaymentAmount, $paidAt, $checkNumber, $month, $year): void {
                $this->applyMixedImportLoanRepayment($member, $loan, $repaymentAmount, $paidAt, $checkNumber, $month, $year);
            });

            $loan->refresh();
            if ($loan->isReadyToSettle()) {
                $loan->update([
                    'status' => 'completed',
                    'settled_at' => $paidAt,
                ]);
                unset($memberLoanState[(int) $member->id]);
            } else {
                $memberLoanState[(int) $member->id] = $loan;
            }

            return 'created';
        }

        if (Contribution::activePeriodExists((int) $member->id, $month, $year)) {
            throw new \InvalidArgumentException(Contribution::duplicateCycleMessage($month, $year));
        }

        if (Contribution::hasPaidScheduledLoanInstallmentOnActiveLoan((int) $member->id, $month, $year)) {
            throw new \InvalidArgumentException(Contribution::scheduledRepaymentPrecludesContributionMessage($month, $year));
        }

        Contribution::create([
            'member_id' => $member->id,
            'month' => $month,
            'year' => $year,
            'amount' => round($amount, 2),
            'paid_at' => $paidAt,
            'payment_method' => Contribution::PAYMENT_METHOD_IMPORT_CSV,
            'reference_number' => $checkNumber === '' ? null : $checkNumber,
            'notes' => 'Imported from mixed contributions/repayments CSV',
            'is_late' => false,
            'late_fee_amount' => null,
        ]);

        return 'created';
    }

    private function parseSignedAmount(string $value): float
    {
        if ($value === '' || !is_numeric($value)) {
            throw new \InvalidArgumentException('amount is required and must be numeric.');
        }

        return round((float) $value, 2);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $keys
     */
    private function firstNonEmptyCell(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $v = trim((string) ($row[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * Simplified mixed CSV — loan repayment rows: mirror {@see importRowWithAutoAllocation} repayment logic.
     * Credits the full row amount once, applies **full** installments (principal + late fee at paid_at) to
     * oldest unpaid cycles **strictly before** the CSV month/year, then to the installment due in that
     * month/year. Residual stays on member cash. Uses {@see LoanRepaymentService::applyImportedInstallmentPayment}
     * so ledger postings and late handling match scheduled imports (the legacy partial installment setting
     * does not apply here).
     */
    private function applyMixedImportLoanRepayment(
        Member $member,
        Loan $loan,
        float $repaymentAmount,
        Carbon $paidAt,
        string $checkNumber,
        int $cycleMonth,
        int $cycleYear,
    ): void {
        if ($repaymentAmount <= 0.00001) {
            return;
        }

        $ref = trim($checkNumber);
        $desc = $ref === ''
            ? "Mixed import repayment – loan #{$loan->id}"
            : "Mixed import repayment – loan #{$loan->id} (check# {$ref})";

        app(AccountingService::class)->creditMemberCashFromImportReceipt($member, $repaymentAmount, $desc, $paidAt);

        $loanRepaymentService = app(LoanRepaymentService::class);
        $lateFeeService = app(LateFeeService::class);
        $usedForRepayments = 0.0;
        $rowCycleKey = $cycleYear * 12 + $cycleMonth;

        while (Loan::active()->where('member_id', $member->id)->exists()) {
            $freshLoan = Loan::active()->where('member_id', $member->id)->first();
            if (!$freshLoan instanceof Loan) {
                break;
            }

            $nextOlder = $freshLoan->installments()
                ->whereIn('status', ['pending', 'overdue'])
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get()
                ->first(function (LoanInstallment $inst) use ($rowCycleKey): bool {
                    return self::loanInstallmentCycleKeyFromDueDate($inst->due_date) < $rowCycleKey;
                });

            if ($nextOlder === null) {
                break;
            }

            if (
                !$this->attemptAutoImportFullInstallmentRepayment(
                    $member,
                    $nextOlder,
                    $paidAt,
                    $loanRepaymentService,
                    $lateFeeService,
                    $usedForRepayments,
                )
            ) {
                break;
            }
        }

        $loanForCurrent = Loan::active()->where('member_id', $member->id)->first();
        if ($loanForCurrent instanceof Loan) {
            $current = $loanRepaymentService->installmentForPeriod($loanForCurrent, $cycleMonth, $cycleYear);
            if ($current instanceof LoanInstallment && !$current->isPaid()) {
                $freshCurrent = LoanInstallment::query()->whereKey($current->getKey())->first();
                if ($freshCurrent !== null && !$freshCurrent->isPaid()) {
                    $this->attemptAutoImportFullInstallmentRepayment(
                        $member,
                        $freshCurrent,
                        $paidAt,
                        $loanRepaymentService,
                        $lateFeeService,
                        $usedForRepayments,
                    );
                }
            }
        }
    }

    /**
     * Auto-allocation mode (feature flag):
     * Credits the full CSV amount to member cash once, then allocates repayments oldest-unpaid-cycle first,
     * then the CSV row cycle. Late fees use the repayment cycle deadline vs imported paid_at when funding is delayed.
     * A contribution for the CSV period is recorded only when the member has **no pending loan installments**
     * (nothing left to repay on the schedule), using the residual of this row after debits for repayments above.
     * Any residual while repayments remain pending stays on member cash unless strict mode forbids leftovers.
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
            DB::transaction(function () use ($row, $member, $month, $year, $paidAt, $totalPaid, $strictMode, $allowUnappliedCredit, &$didPost): void {
                app(AccountingService::class)->creditMemberCashFromImportReceipt(
                    $member,
                    $totalPaid,
                    "Contribution/repayment import — {$month}/{$year}",
                    $paidAt,
                );
                $didPost = true;

                $loanRepaymentService = app(LoanRepaymentService::class);
                $lateFeeService = app(LateFeeService::class);
                $usedForRepayments = 0.0;
                $postedContributionFromRow = false;

                $rowCycleKey = $year * 12 + $month;

                while (Loan::active()->where('member_id', $member->id)->exists()) {
                    $freshLoan = Loan::active()->where('member_id', $member->id)->first();
                    if (!$freshLoan instanceof Loan) {
                        break;
                    }

                    $nextOlder = $freshLoan->installments()
                        ->whereIn('status', ['pending', 'overdue'])
                        ->orderBy('due_date')
                        ->orderBy('installment_number')
                        ->get()
                        ->first(function (LoanInstallment $inst) use ($rowCycleKey): bool {
                            return self::loanInstallmentCycleKeyFromDueDate($inst->due_date) < $rowCycleKey;
                        });

                    if ($nextOlder === null) {
                        break;
                    }

                    if (
                        !$this->attemptAutoImportFullInstallmentRepayment(
                            $member,
                            $nextOlder,
                            $paidAt,
                            $loanRepaymentService,
                            $lateFeeService,
                            $usedForRepayments,
                        )
                    ) {
                        break;
                    }
                }

                $loanForCurrent = Loan::active()->where('member_id', $member->id)->first();
                if ($loanForCurrent instanceof Loan) {
                    $current = $loanRepaymentService->installmentForPeriod($loanForCurrent, $month, $year);
                    if ($current instanceof LoanInstallment && !$current->isPaid()) {
                        $freshCurrent = LoanInstallment::query()->whereKey($current->getKey())->first();
                        if ($freshCurrent !== null && !$freshCurrent->isPaid()) {
                            $this->attemptAutoImportFullInstallmentRepayment(
                                $member,
                                $freshCurrent,
                                $paidAt,
                                $loanRepaymentService,
                                $lateFeeService,
                                $usedForRepayments,
                            );
                        }
                    }
                }

                $rowRemainder = round($totalPaid - $usedForRepayments, 2);

                $canAttemptContributionForRowRemainder = $rowRemainder > 0.00001
                    && !$member->isExemptFromContributions();

                if ($canAttemptContributionForRowRemainder) {
                    if (Contribution::activePeriodExists((int) $member->id, $month, $year)) {
                        throw new \InvalidArgumentException(Contribution::duplicateCycleMessage($month, $year));
                    }
                    if (Contribution::hasPaidScheduledLoanInstallmentOnActiveLoan((int) $member->id, $month, $year)) {
                        throw new \InvalidArgumentException(
                            Contribution::scheduledRepaymentPrecludesContributionMessage($month, $year)
                        );
                    }

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
                        'amount' => $rowRemainder,
                        'paid_at' => $paidAt,
                        'payment_method' => Contribution::PAYMENT_METHOD_IMPORT_CSV,
                        'reference_number' => $this->nullableString($this->cell($row, 'reference_number')),
                        'notes' => $this->appendAutoAllocationNote($this->nullableString($this->cell($row, 'notes')), $totalPaid),
                        'is_late' => $isLate,
                        'late_fee_amount' => $lateFeeAmount,
                    ]);
                    $postedContributionFromRow = true;
                }

                $leftoverOnCashFromImportRow = round($totalPaid - $usedForRepayments, 2);
                if ($postedContributionFromRow) {
                    $leftoverOnCashFromImportRow = 0.0;
                }

                if ($leftoverOnCashFromImportRow > 0.00001 && ($strictMode || !$allowUnappliedCredit)) {
                    throw new \InvalidArgumentException(
                        'After repayments from this row, SAR ' . number_format($leftoverOnCashFromImportRow, 2)
                        . ' remains on member cash because scheduled loan installments are still pending, '
                        . 'the member cannot take a contribution, or cycles conflict. Relax strict_mode / '
                        . 'allow_unapplied_credit or increase the payment.'
                    );
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

    /** Calendar period key YYYY×12+MM from an installment due date. */
    private static function loanInstallmentCycleKeyFromDueDate(\DateTimeInterface $due): int
    {
        $y = (int) $due->format('Y');
        $m = (int) $due->format('n');

        return $y * 12 + $m;
    }

    /**
     * Post one full installment repayment (principal + late fee at paid_at) if member cash suffices.
     * Caller must already have credited bank→member cash for the import row. Increments {@see $usedForRepaymentsTotal} by the cash debited on success.
     */
    private function attemptAutoImportFullInstallmentRepayment(
        Member $member,
        LoanInstallment $installment,
        Carbon $paidAt,
        LoanRepaymentService $loanRepSvc,
        LateFeeService $lateFeeSvc,
        float &$usedForRepaymentsTotal,
    ): bool {
        $installment->loadMissing('loan');
        if ($installment->isPaid()) {
            return false;
        }

        $loan = $installment->loan;
        if (!$loan instanceof Loan || $loan->status !== 'active') {
            return false;
        }

        $due = $installment->due_date;
        $dm = (int) $due->month;
        $dy = (int) $due->year;

        if (Contribution::activePeriodExists((int) $member->id, $dm, $dy)) {
            throw new \InvalidArgumentException(
                Contribution::contributionExistsBlocksRepaymentMessage($dm, $dy)
            );
        }

        $deadline = $loanRepSvc->deadline($dm, $dy);
        $days = $lateFeeSvc->daysPastDue($deadline, Carbon::instance($paidAt));
        $lateFee = $lateFeeSvc->repaymentLateFeeForDays($days);
        $need = round((float) $installment->amount + $lateFee, 2);

        if ($need <= 0.00001) {
            return false;
        }

        $member->unsetRelation('accounts');
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->first();

        $cashBal = (float) ($cashAccount?->balance ?? 0);

        if ($cashBal + 0.00001 < $need) {
            return false;
        }

        $freshInst = LoanInstallment::query()->whereKey($installment->getKey())->first();
        if ($freshInst === null || $freshInst->isPaid()) {
            return false;
        }

        $outcome = $loanRepSvc->applyImportedInstallmentPayment($freshInst, $paidAt, $lateFee);
        if ($outcome !== 'applied') {
            return false;
        }

        $usedForRepaymentsTotal = round($usedForRepaymentsTotal + $need, 2);

        return true;
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
            return $this->resolveSingleMemberByName(
                $nameRaw,
                "No member with name {$nameRaw}.",
                "Multiple members match name {$nameRaw}. Use member_id, member_number, or national_id instead.",
            );
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

    /**
     * Resolve a member by person name using normalization that works reliably with Arabic and extra spacing.
     */
    private function resolveSingleMemberByName(string $rawName, string $notFoundMessage, string $ambiguousMessage): Member
    {
        $matches = $this->findMembersByNormalizedName($rawName);
        if (count($matches) === 0) {
            throw new \InvalidArgumentException($notFoundMessage);
        }
        if (count($matches) > 1) {
            throw new \InvalidArgumentException($ambiguousMessage);
        }

        return $matches[0];
    }

    /**
     * @return array<int, Member>
     */
    private function findMembersByNormalizedName(string $rawName): array
    {
        $needle = $this->normalizePersonName($rawName);
        if ($needle === '') {
            return [];
        }

        return Member::query()
            ->with('user')
            ->get()
            ->filter(function (Member $member) use ($needle): bool {
                $candidate = $this->normalizePersonName((string) ($member->user?->name ?? ''));

                return $candidate !== '' && $candidate === $needle;
            })
            ->values()
            ->all();
    }

    private function normalizePersonName(string $value): string
    {
        $v = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($v === '') {
            return '';
        }

        return mb_strtolower($v);
    }

    /**
     * Find the loan that should receive mixed-import repayment rows.
     * Prefer active loans; if a loan was previously marked completed but still has pending/overdue
     * installments (legacy import bug), reopen it and continue repayment posting.
     */
    private function resolveLoanForMixedRepayment(Member $member): ?Loan
    {
        $active = Loan::active()
            ->where('member_id', $member->id)
            ->orderByDesc('id')
            ->first();
        if ($active instanceof Loan) {
            return $active;
        }

        $needsTopup = Loan::query()
            ->where('member_id', $member->id)
            ->whereHas('installments', fn($q) => $q->whereIn('status', ['pending', 'overdue']))
            ->orderByDesc('id')
            ->first();

        if (!$needsTopup instanceof Loan) {
            return null;
        }

        if (in_array((string) $needsTopup->status, ['completed', 'early_settled'], true)) {
            $needsTopup->update([
                'status' => 'active',
                'settled_at' => null,
            ]);
            $needsTopup->refresh();
        }

        return $needsTopup;
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
        $suffix = 'Auto-allocated from import total payment SAR ' . number_format($totalPaid, 2);

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
