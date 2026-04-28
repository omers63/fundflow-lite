<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\ReconciliationSnapshot;
use App\Models\Setting;
use App\Models\SmsTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Financial reconciliation: ledger integrity, control totals, pipeline hygiene,
 * bank statement vs book (optional), contribution vs fund ledger, and loan checks
 * (active + approved partial disbursement).
 */
class FinanceReconciliationService
{
    public const AMOUNT_TOLERANCE = 0.03;

    public const LOAN_SCHEDULE_TOLERANCE = 1.0;

    /**
     * Optional inputs from UI or {@see Setting} keys for scheduled runs:
     * - declared_bank_balance (float): statement / bank closing balance to compare to master_cash book.
     * - declared_bank_date (string|null): informational (Y-m-d).
     * - bank_mismatch_treat_as_critical (bool): if true, book vs stated variance is critical; else warning.
     *
     * @return array{meta: array, verdict: array, checks: array, pipeline: array, period_metrics: array, summary: array}
     */
    public function buildReport(
        string $mode,
        ?CarbonInterface $asOf = null,
        ?CarbonInterface $periodStart = null,
        ?CarbonInterface $periodEnd = null,
        array $options = [],
    ): array {
        $asOf = $asOf ? Carbon::parse($asOf) : now();

        $declaredBank = isset($options['declared_bank_balance']) && $options['declared_bank_balance'] !== null && $options['declared_bank_balance'] !== ''
            ? (float) $options['declared_bank_balance']
            : null;
        $declaredBankDate = isset($options['declared_bank_date']) ? (string) $options['declared_bank_date'] : null;
        $bankMismatchCritical = (bool) ($options['bank_mismatch_treat_as_critical'] ?? false);

        $meta = [
            'mode' => $mode,
            'as_of' => $asOf->toIso8601String(),
            'period_start' => $periodStart?->toIso8601String(),
            'period_end' => $periodEnd?->toIso8601String(),
            'timezone' => config('app.timezone'),
            'options' => array_filter([
                'declared_bank_balance' => $declaredBank,
                'declared_bank_date' => $declaredBankDate,
                'bank_mismatch_treat_as_critical' => $bankMismatchCritical,
            ], fn($v) => $v !== null && $v !== '' && $v !== false),
        ];

        $checks = [];
        $critical = 0;
        $warnings = 0;

        $incrementCritical = function () use (&$critical): void {
            $critical++;
        };
        $incrementWarning = function () use (&$warnings): void {
            $warnings++;
        };

        // --- 1) Per-account ledger vs stored balance ---
        $ledgerRows = AccountTransaction::query()
            ->selectRaw('account_id, SUM(CASE WHEN entry_type = ? THEN amount ELSE -amount END) as computed', ['credit'])
            ->whereNull('deleted_at')
            ->groupBy('account_id');

        $computedByAccount = DB::query()
            ->fromSub($ledgerRows, 't')
            ->pluck('computed', 'account_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $ledgerMismatches = [];
        $accounts = Account::query()->whereNull('deleted_at')->get();
        foreach ($accounts as $account) {
            $computed = (float) ($computedByAccount[$account->id] ?? 0.0);
            $stored = (float) $account->balance;
            $delta = abs($computed - $stored);
            if ($delta > self::AMOUNT_TOLERANCE) {
                $ledgerMismatches[] = [
                    'account_id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'member_id' => $account->member_id,
                    'loan_id' => $account->loan_id,
                    'stored_balance' => round($stored, 2),
                    'computed_from_ledger' => round($computed, 2),
                    'delta' => round($computed - $stored, 2),
                ];
            }
        }

        if ($ledgerMismatches !== []) {
            $incrementCritical();
        }

        $checks['ledger_balances'] = [
            'label' => 'Stored balance vs ledger roll-forward',
            'severity' => $ledgerMismatches === [] ? 'ok' : 'critical',
            'accounts_checked' => $accounts->count(),
            'mismatch_count' => count($ledgerMismatches),
            'mismatches' => array_slice($ledgerMismatches, 0, 200),
            'mismatches_truncated' => count($ledgerMismatches) > 200,
        ];

        // --- 2) Global trial ---
        $totals = AccountTransaction::query()
            ->whereNull('deleted_at')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as credits, COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as debits',
                ['credit', 'debit']
            )
            ->first();

        $sumCredits = (float) ($totals->credits ?? 0);
        $sumDebits = (float) ($totals->debits ?? 0);
        $trialDelta = abs($sumCredits - $sumDebits);
        $trialOk = $trialDelta <= self::AMOUNT_TOLERANCE;
        if (!$trialOk) {
            $incrementWarning();
        }

        $checks['global_trial'] = [
            'label' => 'Global posting trial (Σ credits vs Σ debits)',
            'severity' => $trialOk ? 'ok' : 'warning',
            'sum_credits' => round($sumCredits, 2),
            'sum_debits' => round($sumDebits, 2),
            'delta' => round($sumCredits - $sumDebits, 2),
            'note' => 'In a strictly paired ledger these should match. Drift may indicate one-sided manual entries, imports, or reversals.',
        ];

        // --- 3) Master vs Σ(member) — advisory ---
        $masterCash = $this->safeMasterAccount('master_cash', $incrementCritical);
        $masterFund = $this->safeMasterAccount('master_fund', $incrementCritical);

        $sumMemberCash = (float) Account::query()
            ->where('type', Account::TYPE_MEMBER_CASH)
            ->whereNull('deleted_at')
            ->sum('balance');

        $sumMemberFund = (float) Account::query()
            ->where('type', Account::TYPE_MEMBER_FUND)
            ->whereNull('deleted_at')
            ->sum('balance');

        $cashDelta = $masterCash !== null ? abs((float) $masterCash->balance - $sumMemberCash) : null;
        $fundDelta = $masterFund !== null ? abs((float) $masterFund->balance - $sumMemberFund) : null;

        if ($cashDelta !== null && $cashDelta > self::AMOUNT_TOLERANCE) {
            $incrementWarning();
        }
        if ($fundDelta !== null && $fundDelta > self::AMOUNT_TOLERANCE) {
            $incrementWarning();
        }

        $checks['paired_control_totals'] = [
            'label' => 'Master control vs aggregate member mirrors',
            'severity' => (
                ($cashDelta ?? 0) <= self::AMOUNT_TOLERANCE && ($fundDelta ?? 0) <= self::AMOUNT_TOLERANCE
            ) ? 'ok' : 'warning',
            'master_cash_balance' => $masterCash ? round((float) $masterCash->balance, 2) : null,
            'sum_member_cash' => round($sumMemberCash, 2),
            'cash_delta' => $cashDelta !== null ? round((float) $masterCash->balance - $sumMemberCash, 2) : null,
            'master_fund_balance' => $masterFund ? round((float) $masterFund->balance, 2) : null,
            'sum_member_fund' => round($sumMemberFund, 2),
            'fund_delta' => $fundDelta !== null ? round((float) $masterFund->balance - $sumMemberFund, 2) : null,
            'note' => 'Member-only cash debits (repayments) and guarantor fund debits intentionally break strict parity; treat as hygiene, not hard failure.',
        ];

        // --- 3b) Bank statement vs master_cash book (optional) ---
        if ($declaredBank !== null && $masterCash !== null) {
            $book = round((float) $masterCash->balance, 2);
            $stated = round($declaredBank, 2);
            $variance = round($book - $stated, 2);
            $match = abs($variance) <= self::AMOUNT_TOLERANCE;
            if (!$match) {
                if ($bankMismatchCritical) {
                    $incrementCritical();
                } else {
                    $incrementWarning();
                }
            }
            $checks['bank_statement_vs_book'] = [
                'label' => 'Master cash (book) vs declared bank / statement balance',
                'severity' => $match ? 'ok' : ($bankMismatchCritical ? 'critical' : 'warning'),
                'master_cash_book' => $book,
                'declared_balance' => $stated,
                'declared_bank_date' => $declaredBankDate,
                'variance_book_minus_stated' => $variance,
                'note' => 'Set optional fields when running from the UI, or Setting keys reconciliation.bank_statement_balance and reconciliation.bank_statement_date for scheduled runs.',
            ];
        } else {
            $checks['bank_statement_vs_book'] = [
                'label' => 'Master cash (book) vs declared bank / statement balance',
                'severity' => 'skipped',
                'note' => 'No declared statement balance supplied.',
            ];
        }

        // --- 3c) Contributions vs master fund ledger ---
        $contribMorph = Contribution::class;
        $missingLedgerContributions = [];
        if ($masterFund !== null) {
            Contribution::query()
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($contribMorph, &$missingLedgerContributions): void {
                    foreach ($rows as $row) {
                        if (!$row instanceof Contribution) {
                            continue;
                        }
                        $exists = AccountTransaction::query()
                            ->where('source_type', $contribMorph)
                            ->where('source_id', $row->id)
                            ->whereNull('deleted_at')
                            ->exists();
                        if (!$exists) {
                            $missingLedgerContributions[] = [
                                'contribution_id' => $row->id,
                                'member_id' => $row->member_id,
                                'amount' => (float) $row->amount,
                                'period' => $row->year . '-' . str_pad((string) $row->month, 2, '0', STR_PAD_LEFT),
                            ];
                        }
                    }
                });

            $ledgerContribMaster = (float) AccountTransaction::query()
                ->where('account_id', $masterFund->id)
                ->where('source_type', $contribMorph)
                ->where('entry_type', 'credit')
                ->whereNull('deleted_at')
                ->sum('amount');

            $contribSum = (float) Contribution::query()->whereNull('deleted_at')->sum('amount');
            $masterDelta = abs($ledgerContribMaster - $contribSum);
            $masterMatch = $masterDelta <= self::AMOUNT_TOLERANCE;

            if ($missingLedgerContributions !== []) {
                $incrementCritical();
            }
            if (!$masterMatch) {
                $incrementWarning();
            }

            $checks['contributions_ledger'] = [
                'label' => 'Contributions — ledger presence and master fund credits',
                'severity' => $missingLedgerContributions === [] && $masterMatch ? 'ok' : ($missingLedgerContributions !== [] ? 'critical' : 'warning'),
                'missing_ledger_count' => count($missingLedgerContributions),
                'missing_ledger_sample' => array_slice($missingLedgerContributions, 0, 50),
                'sum_contribution_rows' => round($contribSum, 2),
                'sum_master_fund_credits_sourced_contribution' => round($ledgerContribMaster, 2),
                'master_fund_delta' => round($ledgerContribMaster - $contribSum, 2),
                'note' => 'Each contribution should post paired master+member fund credits; missing lines indicate failed posting or data repair needs.',
            ];
        } else {
            $checks['contributions_ledger'] = [
                'label' => 'Contributions — ledger presence and master fund credits',
                'severity' => 'skipped',
                'note' => 'Master fund account missing.',
            ];
        }

        // --- 3d) Member portal "Post Funds" integrity (bank tx -> master/member cash mirror) ---
        $memberPortalPostingIssues = [];
        $memberPortalPostedCount = 0;
        $bankTxMorph = BankTransaction::class;

        $masterCashId = $masterCash?->id;

        BankTransaction::query()
            ->whereNull('deleted_at')
            ->where('raw_data->source', 'member_portal_post')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$memberPortalPostingIssues, &$memberPortalPostedCount, $bankTxMorph, $masterCashId): void {
                foreach ($rows as $tx) {
                    if (!$tx instanceof BankTransaction) {
                        continue;
                    }

                    $memberPortalPostedCount++;

                    if ($tx->posted_at === null) {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'member_portal_post transaction is not posted',
                        ];
                        continue;
                    }

                    if ($tx->transaction_type !== 'credit') {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'member_portal_post transaction type is not credit',
                            'transaction_type' => $tx->transaction_type,
                        ];
                    }

                    if ($tx->member_id === null) {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'member_portal_post transaction has no member_id',
                        ];
                        continue;
                    }

                    $lines = AccountTransaction::query()
                        ->where('source_type', $bankTxMorph)
                        ->where('source_id', $tx->id)
                        ->whereNull('deleted_at')
                        ->get();

                    $masterLine = $masterCashId
                        ? $lines->first(fn(AccountTransaction $l) => (int) $l->account_id === (int) $masterCashId)
                        : null;
                    if ($masterLine === null) {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'missing master cash ledger line for member_portal_post transaction',
                        ];
                    } elseif ($masterLine->entry_type !== 'credit' || abs((float) $masterLine->amount - (float) $tx->amount) > self::AMOUNT_TOLERANCE) {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'master cash ledger line does not match posted amount/type',
                            'ledger_amount' => (float) $masterLine->amount,
                            'transaction_amount' => (float) $tx->amount,
                            'ledger_entry_type' => $masterLine->entry_type,
                        ];
                    }

                    $memberCashLineExists = AccountTransaction::query()
                        ->where('source_type', $bankTxMorph)
                        ->where('source_id', $tx->id)
                        ->whereNull('deleted_at')
                        ->where('entry_type', 'credit')
                        ->where('member_id', $tx->member_id)
                        ->whereHas('account', fn($q) => $q->where('type', Account::TYPE_MEMBER_CASH)->where('member_id', $tx->member_id))
                        ->exists();

                    if (!$memberCashLineExists) {
                        $memberPortalPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'missing member cash mirror line for member_portal_post transaction',
                            'member_id' => $tx->member_id,
                        ];
                    }
                }
            });

        if ($memberPortalPostingIssues !== []) {
            $incrementCritical();
        }

        $checks['member_portal_posting_integrity'] = [
            'label' => 'Member portal Post Funds — posted + cash mirror integrity',
            'severity' => $memberPortalPostingIssues === [] ? 'ok' : 'critical',
            'transactions_checked' => $memberPortalPostedCount,
            'issue_count' => count($memberPortalPostingIssues),
            'issues' => array_slice($memberPortalPostingIssues, 0, 100),
            'issues_truncated' => count($memberPortalPostingIssues) > 100,
        ];

        // --- 4) Active loans: installment schedule vs loan ledger ---
        $activeLoanMismatches = [];
        Loan::query()
            ->where('status', 'active')
            ->with(['member.user', 'installments'])
            ->chunkById(100, function ($loans) use (&$activeLoanMismatches): void {
                foreach ($loans as $loan) {
                    if (!$loan instanceof Loan) {
                        continue;
                    }
                    $acc = $loan->account();
                    if (!$acc) {
                        continue;
                    }
                    $ledgerOutstanding = max(0.0, -(float) $acc->balance);
                    $scheduleOutstanding = (float) $loan->remaining_amount;
                    if (abs($ledgerOutstanding - $scheduleOutstanding) > self::LOAN_SCHEDULE_TOLERANCE) {
                        $activeLoanMismatches[] = [
                            'loan_id' => $loan->id,
                            'phase' => 'active',
                            'member' => $loan->member?->user?->name,
                            'ledger_outstanding' => round($ledgerOutstanding, 2),
                            'schedule_remaining' => round($scheduleOutstanding, 2),
                            'delta' => round($ledgerOutstanding - $scheduleOutstanding, 2),
                        ];
                    }
                }
            });

        if ($activeLoanMismatches !== []) {
            $incrementWarning();
        }

        $checks['active_loans_schedule_vs_ledger'] = [
            'label' => 'Active loans — pending installment total vs loan account',
            'severity' => $activeLoanMismatches === [] ? 'ok' : 'warning',
            'mismatch_count' => count($activeLoanMismatches),
            'mismatches' => array_slice($activeLoanMismatches, 0, 100),
            'mismatches_truncated' => count($activeLoanMismatches) > 100,
        ];

        // --- 4b) Approved loans with disbursement(s): ledger vs disbursed / schedule ---
        $approvedLoanMismatches = [];
        Loan::query()
            ->where('status', 'approved')
            ->where('amount_disbursed', '>', 0)
            ->with(['member.user', 'installments'])
            ->chunkById(100, function ($loans) use (&$approvedLoanMismatches): void {
                foreach ($loans as $loan) {
                    if (!$loan instanceof Loan) {
                        continue;
                    }
                    $acc = $loan->account();
                    if (!$acc) {
                        continue;
                    }
                    $ledgerOutstanding = max(0.0, -(float) $acc->balance);
                    $hasInstallments = $loan->installments()->exists();
                    if ($hasInstallments) {
                        $expected = (float) $loan->remaining_amount;
                    } else {
                        $expected = (float) $loan->amount_disbursed;
                    }
                    if (abs($ledgerOutstanding - $expected) > self::LOAN_SCHEDULE_TOLERANCE) {
                        $approvedLoanMismatches[] = [
                            'loan_id' => $loan->id,
                            'phase' => 'approved',
                            'member' => $loan->member?->user?->name,
                            'ledger_outstanding' => round($ledgerOutstanding, 2),
                            'expected_outstanding' => round($expected, 2),
                            'expected_basis' => $hasInstallments ? 'remaining_installments' : 'amount_disbursed',
                            'amount_disbursed' => round((float) $loan->amount_disbursed, 2),
                            'delta' => round($ledgerOutstanding - $expected, 2),
                        ];
                    }
                }
            });

        if ($approvedLoanMismatches !== []) {
            $incrementWarning();
        }

        $checks['approved_loans_disbursement_vs_ledger'] = [
            'label' => 'Approved loans (with disbursement) — ledger vs disbursed / schedule',
            'severity' => $approvedLoanMismatches === [] ? 'ok' : 'warning',
            'mismatch_count' => count($approvedLoanMismatches),
            'mismatches' => array_slice($approvedLoanMismatches, 0, 100),
            'mismatches_truncated' => count($approvedLoanMismatches) > 100,
            'note' => 'Before installments exist, loan account outstanding should match amount_disbursed; once installments exist, compare to remaining installment total.',
        ];

        // --- 5) Orphan loan accounts ---
        $orphanLoanAccounts = Account::query()
            ->where('type', Account::TYPE_LOAN)
            ->whereNotNull('loan_id')
            ->whereNull('deleted_at')
            ->whereNotExists(function ($q): void {
                $q->select(DB::raw('1'))
                    ->from('loans')
                    ->whereColumn('loans.id', 'accounts.loan_id');
            })
            ->get(['id', 'loan_id', 'name', 'balance'])
            ->map(fn(Account $a) => [
                'account_id' => $a->id,
                'loan_id' => $a->loan_id,
                'name' => $a->name,
                'balance' => (float) $a->balance,
            ])
            ->all();

        if ($orphanLoanAccounts !== []) {
            $incrementCritical();
        }

        $checks['orphan_loan_accounts'] = [
            'label' => 'Loan-type accounts whose loan row is missing',
            'severity' => $orphanLoanAccounts === [] ? 'ok' : 'critical',
            'count' => count($orphanLoanAccounts),
            'accounts' => $orphanLoanAccounts,
        ];

        // --- 6) Pipeline ---
        $bankUnposted = BankTransaction::query()
            ->whereNull('posted_at')
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNull('is_duplicate')->orWhere('is_duplicate', false);
            })
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as amt')
            ->first();

        $smsUnposted = SmsTransaction::query()
            ->whereNull('posted_at')
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->whereNull('is_duplicate')->orWhere('is_duplicate', false);
            })
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount), 0) as amt')
            ->first();

        $pipeline = [
            'bank_unposted_count' => (int) ($bankUnposted->c ?? 0),
            'bank_unposted_amount' => round((float) ($bankUnposted->amt ?? 0), 2),
            'sms_unposted_count' => (int) ($smsUnposted->c ?? 0),
            'sms_unposted_amount' => round((float) ($smsUnposted->amt ?? 0), 2),
        ];

        if ($pipeline['bank_unposted_count'] > 0 || $pipeline['sms_unposted_count'] > 0) {
            $incrementWarning();
        }

        // --- 7) Period metrics ---
        $periodMetrics = [
            'ledger_lines_in_period' => null,
            'bank_posted_in_period' => null,
        ];

        if ($periodStart && $periodEnd) {
            $pStart = Carbon::parse($periodStart)->startOfDay();
            $pEnd = Carbon::parse($periodEnd)->endOfDay();

            $periodMetrics['ledger_lines_in_period'] = (int) AccountTransaction::query()
                ->whereNull('deleted_at')
                ->whereBetween('transacted_at', [$pStart, $pEnd])
                ->count();

            $periodMetrics['bank_posted_in_period'] = (int) BankTransaction::query()
                ->whereNotNull('posted_at')
                ->whereBetween('posted_at', [$pStart, $pEnd])
                ->count();

            $periodMetrics['sms_posted_in_period'] = (int) SmsTransaction::query()
                ->whereNotNull('posted_at')
                ->whereBetween('posted_at', [$pStart, $pEnd])
                ->count();
        }

        $verdict = [
            'pass' => $critical === 0,
            'critical_issues' => $critical,
            'warnings' => $warnings,
        ];

        $summary = [
            'verdict' => $verdict,
            'headline_checks' => [
                'ledger_balances' => $checks['ledger_balances']['severity'],
                'global_trial' => $checks['global_trial']['severity'],
                'bank_statement_vs_book' => $checks['bank_statement_vs_book']['severity'],
                'contributions_ledger' => $checks['contributions_ledger']['severity'],
                'member_portal_posting_integrity' => $checks['member_portal_posting_integrity']['severity'],
                'orphan_loan_accounts' => $checks['orphan_loan_accounts']['severity'],
                'paired_control_totals' => $checks['paired_control_totals']['severity'],
                'active_loans' => $checks['active_loans_schedule_vs_ledger']['severity'],
                'approved_loans' => $checks['approved_loans_disbursement_vs_ledger']['severity'],
            ],
            'pipeline' => $pipeline,
            'as_of' => $meta['as_of'],
        ];

        return [
            'meta' => $meta,
            'verdict' => $verdict,
            'checks' => $checks,
            'pipeline' => $pipeline,
            'period_metrics' => $periodMetrics,
            'summary' => $summary,
        ];
    }

    /**
     * Build options array from {@see Setting} keys for CLI / scheduler.
     *
     * Keys: reconciliation.bank_statement_balance (numeric), reconciliation.bank_statement_date (Y-m-d),
     *       reconciliation.bank_variance_critical (boolish).
     *
     * @return array<string, mixed>
     */
    public static function bankOptionsFromSettings(): array
    {
        $balance = Setting::get('reconciliation.bank_statement_balance');
        $date = Setting::get('reconciliation.bank_statement_date');
        $critical = Setting::get('reconciliation.bank_variance_critical', false);

        $out = [];
        if ($balance !== null && $balance !== '' && is_numeric($balance)) {
            $out['declared_bank_balance'] = (float) $balance;
        }
        if (filled($date)) {
            $out['declared_bank_date'] = (string) $date;
        }
        $out['bank_mismatch_treat_as_critical'] = filter_var($critical, FILTER_VALIDATE_BOOL);

        return $out;
    }

    public function persistSnapshot(array $report, ?int $userId = null): ReconciliationSnapshot
    {
        $meta = $report['meta'];
        $verdict = $report['verdict'];

        return ReconciliationSnapshot::create([
            'mode' => $meta['mode'],
            'as_of' => Carbon::parse($meta['as_of']),
            'period_start' => filled($meta['period_start'] ?? null) ? Carbon::parse($meta['period_start']) : null,
            'period_end' => filled($meta['period_end'] ?? null) ? Carbon::parse($meta['period_end']) : null,
            'is_passing' => (bool) ($verdict['pass'] ?? false),
            'critical_issues' => (int) ($verdict['critical_issues'] ?? 0),
            'warnings' => (int) ($verdict['warnings'] ?? 0),
            'summary' => $report['summary'],
            'report' => $report,
            'created_by_id' => $userId,
        ]);
    }

    private function safeMasterAccount(string $slug, callable $onMissing): ?Account
    {
        /** @var Account|null $account */
        $account = Account::query()->where('slug', $slug)->whereNull('deleted_at')->first();
        if ($account === null) {
            $onMissing();

            return null;
        }

        return $account;
    }
}
