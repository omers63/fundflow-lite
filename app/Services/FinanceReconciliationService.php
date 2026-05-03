<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MemberSubscriptionFee;
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
     * @return array{
     *   meta: array,
     *   verdict: array,
     *   checks: array,
     *   coverage_matrix: list<array{flow: string, checks: list<array{key: string, severity: string}>}>,
     *   pipeline: array,
     *   period_metrics: array,
     *   summary: array
     * }
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
            'note' => 'Member cash debits (repayments / transfers), guarantor fund debits, and loan disbursement cash credits can break strict parity; treat as hygiene, not hard failure.',
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

        // --- 3e) Bank transaction posting integrity (all posted rows) ---
        $bankPostingIssues = [];
        $bankPostedCount = 0;

        BankTransaction::query()
            ->whereNull('deleted_at')
            ->whereNotNull('posted_at')
            ->where(function ($q): void {
                $q->whereNull('is_duplicate')->orWhere('is_duplicate', false);
            })
            ->with('loanDisbursement')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$bankPostingIssues, &$bankPostedCount, $bankTxMorph, $masterCashId): void {
                foreach ($rows as $tx) {
                    if (!$tx instanceof BankTransaction) {
                        continue;
                    }

                    $bankPostedCount++;
                    $entryType = $tx->transaction_type === 'credit' ? 'credit' : 'debit';

                    $lines = AccountTransaction::query()
                        ->where('source_type', $bankTxMorph)
                        ->where('source_id', $tx->id)
                        ->whereNull('deleted_at')
                        ->get();

                    $masterLine = $masterCashId
                        ? $lines->first(fn(AccountTransaction $l) => (int) $l->account_id === (int) $masterCashId)
                        : null;

                    if ($masterLine === null) {
                        $bankPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'missing master cash ledger line',
                        ];
                    } elseif (
                        $masterLine->entry_type !== $entryType
                        || abs((float) $masterLine->amount - (float) $tx->amount) > self::AMOUNT_TOLERANCE
                    ) {
                        $bankPostingIssues[] = [
                            'bank_transaction_id' => $tx->id,
                            'issue' => 'master cash ledger line amount/type mismatch',
                            'ledger_entry_type' => $masterLine->entry_type,
                            'ledger_amount' => (float) $masterLine->amount,
                            'tx_type' => $tx->transaction_type,
                            'tx_amount' => (float) $tx->amount,
                        ];
                    }

                    if ($tx->member_id !== null) {
                        $memberCashLine = AccountTransaction::query()
                            ->where('source_type', $bankTxMorph)
                            ->where('source_id', $tx->id)
                            ->whereNull('deleted_at')
                            ->where('member_id', $tx->member_id)
                            ->whereHas('account', fn($q) => $q
                                ->where('type', Account::TYPE_MEMBER_CASH)
                                ->where('member_id', $tx->member_id))
                            ->first();

                        if ($memberCashLine === null) {
                            $bankPostingIssues[] = [
                                'bank_transaction_id' => $tx->id,
                                'issue' => 'missing member cash mirror line',
                                'member_id' => $tx->member_id,
                            ];
                        } elseif (
                            $memberCashLine->entry_type !== $entryType
                            || abs((float) $memberCashLine->amount - (float) $tx->amount) > self::AMOUNT_TOLERANCE
                        ) {
                            $bankPostingIssues[] = [
                                'bank_transaction_id' => $tx->id,
                                'issue' => 'member cash mirror line amount/type mismatch',
                                'member_id' => $tx->member_id,
                                'ledger_entry_type' => $memberCashLine->entry_type,
                                'ledger_amount' => (float) $memberCashLine->amount,
                                'tx_type' => $tx->transaction_type,
                                'tx_amount' => (float) $tx->amount,
                            ];
                        }
                    }

                    if ($tx->transaction_type === 'debit') {
                        if ($tx->member_id === null) {
                            $bankPostingIssues[] = [
                                'bank_transaction_id' => $tx->id,
                                'issue' => 'debit bank transaction is missing member_id',
                            ];
                        }

                        if ($tx->loan_disbursement_id === null) {
                            $bankPostingIssues[] = [
                                'bank_transaction_id' => $tx->id,
                                'issue' => 'debit bank transaction is missing loan_disbursement_id',
                            ];
                        } elseif (
                            ($tx->loanDisbursement?->loan_id !== null)
                            && ($tx->loan_id !== null)
                            && ((int) $tx->loanDisbursement->loan_id !== (int) $tx->loan_id)
                        ) {
                            $bankPostingIssues[] = [
                                'bank_transaction_id' => $tx->id,
                                'issue' => 'loan_id does not match referenced loan disbursement',
                                'loan_id' => $tx->loan_id,
                                'loan_disbursement_loan_id' => $tx->loanDisbursement?->loan_id,
                            ];
                        }
                    }
                }
            });

        if ($bankPostingIssues !== []) {
            $incrementCritical();
        }

        $checks['bank_transaction_posting_integrity'] = [
            'label' => 'Bank transactions — posted ledger legs integrity',
            'severity' => $bankPostingIssues === [] ? 'ok' : 'critical',
            'transactions_checked' => $bankPostedCount,
            'issue_count' => count($bankPostingIssues),
            'issues' => array_slice($bankPostingIssues, 0, 120),
            'issues_truncated' => count($bankPostingIssues) > 120,
        ];

        // --- 3f) SMS transaction posting integrity ---
        $smsPostingIssues = [];
        $smsPostedCount = 0;
        $smsMorph = SmsTransaction::class;

        SmsTransaction::query()
            ->whereNull('deleted_at')
            ->whereNotNull('posted_at')
            ->where(function ($q): void {
                $q->whereNull('is_duplicate')->orWhere('is_duplicate', false);
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$smsPostingIssues, &$smsPostedCount, $smsMorph, $masterCashId): void {
                foreach ($rows as $tx) {
                    if (!$tx instanceof SmsTransaction) {
                        continue;
                    }

                    $smsPostedCount++;
                    $entryType = $tx->transaction_type === 'credit' ? 'credit' : 'debit';

                    if ($tx->member_id === null) {
                        $smsPostingIssues[] = [
                            'sms_transaction_id' => $tx->id,
                            'issue' => 'posted SMS transaction is missing member_id',
                        ];
                        continue;
                    }

                    $lines = AccountTransaction::query()
                        ->where('source_type', $smsMorph)
                        ->where('source_id', $tx->id)
                        ->whereNull('deleted_at')
                        ->get();

                    $masterLine = $masterCashId
                        ? $lines->first(fn(AccountTransaction $l) => (int) $l->account_id === (int) $masterCashId)
                        : null;

                    if ($masterLine === null) {
                        $smsPostingIssues[] = [
                            'sms_transaction_id' => $tx->id,
                            'issue' => 'missing master cash ledger line',
                        ];
                    } elseif (
                        $masterLine->entry_type !== $entryType
                        || abs((float) $masterLine->amount - (float) $tx->amount) > self::AMOUNT_TOLERANCE
                    ) {
                        $smsPostingIssues[] = [
                            'sms_transaction_id' => $tx->id,
                            'issue' => 'master cash ledger line amount/type mismatch',
                        ];
                    }

                    $memberCashLine = AccountTransaction::query()
                        ->where('source_type', $smsMorph)
                        ->where('source_id', $tx->id)
                        ->whereNull('deleted_at')
                        ->where('member_id', $tx->member_id)
                        ->whereHas('account', fn($q) => $q
                            ->where('type', Account::TYPE_MEMBER_CASH)
                            ->where('member_id', $tx->member_id))
                        ->first();

                    if ($memberCashLine === null) {
                        $smsPostingIssues[] = [
                            'sms_transaction_id' => $tx->id,
                            'issue' => 'missing member cash mirror line',
                            'member_id' => $tx->member_id,
                        ];
                    } elseif (
                        $memberCashLine->entry_type !== $entryType
                        || abs((float) $memberCashLine->amount - (float) $tx->amount) > self::AMOUNT_TOLERANCE
                    ) {
                        $smsPostingIssues[] = [
                            'sms_transaction_id' => $tx->id,
                            'issue' => 'member cash mirror line amount/type mismatch',
                            'member_id' => $tx->member_id,
                        ];
                    }
                }
            });

        if ($smsPostingIssues !== []) {
            $incrementCritical();
        }

        $checks['sms_transaction_posting_integrity'] = [
            'label' => 'SMS transactions — posted ledger legs integrity',
            'severity' => $smsPostingIssues === [] ? 'ok' : 'critical',
            'transactions_checked' => $smsPostedCount,
            'issue_count' => count($smsPostingIssues),
            'issues' => array_slice($smsPostingIssues, 0, 120),
            'issues_truncated' => count($smsPostingIssues) > 120,
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

        // --- 4c) Loan disbursement cash payout leg integrity ---
        $loanCashPayoutMismatches = [];
        Loan::query()
            ->whereIn('status', ['approved', 'active', 'completed', 'early_settled'])
            ->where('amount_disbursed', '>', 0)
            ->with(['member.user'])
            ->chunkById(100, function ($loans) use (&$loanCashPayoutMismatches): void {
                foreach ($loans as $loan) {
                    if (!$loan instanceof Loan || !$loan->member_id) {
                        continue;
                    }

                    $memberCashCredits = (float) AccountTransaction::query()
                        ->where('source_type', Loan::class)
                        ->where('source_id', $loan->id)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('member_id', $loan->member_id)
                        ->whereHas('account', fn($q) => $q
                            ->where('type', Account::TYPE_MEMBER_CASH)
                            ->where('member_id', $loan->member_id))
                        ->sum('amount');

                    $expected = (float) $loan->amount_disbursed;
                    if (abs($memberCashCredits - $expected) > self::AMOUNT_TOLERANCE) {
                        $loanCashPayoutMismatches[] = [
                            'loan_id' => $loan->id,
                            'member' => $loan->member?->user?->name,
                            'status' => $loan->status,
                            'amount_disbursed' => round($expected, 2),
                            'member_cash_credits_from_loan' => round($memberCashCredits, 2),
                            'delta' => round($memberCashCredits - $expected, 2),
                        ];
                    }
                }
            });

        if ($loanCashPayoutMismatches !== []) {
            $incrementCritical();
        }

        $checks['loan_disbursement_cash_payout_integrity'] = [
            'label' => 'Loan disbursements — member cash payout credit leg present',
            'severity' => $loanCashPayoutMismatches === [] ? 'ok' : 'critical',
            'mismatch_count' => count($loanCashPayoutMismatches),
            'mismatches' => array_slice($loanCashPayoutMismatches, 0, 100),
            'mismatches_truncated' => count($loanCashPayoutMismatches) > 100,
            'note' => 'Expected member cash credits sourced from Loan should equal loans.amount_disbursed.',
        ];

        // --- 4d) Contribution posting flow integrity (all expected legs by type) ---
        $contributionFlowIssues = [];
        Contribution::query()
            ->whereNull('deleted_at')
            ->with('member')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$contributionFlowIssues, $masterFund, $masterCash): void {
                foreach ($rows as $contribution) {
                    if (!$contribution instanceof Contribution) {
                        continue;
                    }

                    $memberId = (int) $contribution->member_id;
                    $amount = (float) $contribution->amount;
                    $lateFee = (float) ($contribution->late_fee_amount ?? 0);
                    $contribMorph = Contribution::class;

                    $masterFundCredits = (float) AccountTransaction::query()
                        ->where('source_type', $contribMorph)
                        ->where('source_id', $contribution->id)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('account_id', $masterFund?->id)
                        ->sum('amount');

                    if ($masterFund === null || abs($masterFundCredits - $amount) > self::AMOUNT_TOLERANCE) {
                        $contributionFlowIssues[] = [
                            'contribution_id' => $contribution->id,
                            'issue' => 'master fund credit leg missing/mismatch',
                            'expected' => round($amount, 2),
                            'actual' => round($masterFundCredits, 2),
                        ];
                    }

                    $memberFundCredits = (float) AccountTransaction::query()
                        ->where('source_type', $contribMorph)
                        ->where('source_id', $contribution->id)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('member_id', $memberId)
                        ->whereHas('account', fn($q) => $q
                            ->where('type', Account::TYPE_MEMBER_FUND)
                            ->where('member_id', $memberId))
                        ->sum('amount');

                    if (abs($memberFundCredits - $amount) > self::AMOUNT_TOLERANCE) {
                        $contributionFlowIssues[] = [
                            'contribution_id' => $contribution->id,
                            'issue' => 'member fund credit leg missing/mismatch',
                            'expected' => round($amount, 2),
                            'actual' => round($memberFundCredits, 2),
                            'member_id' => $memberId,
                        ];
                    }

                    if ((string) $contribution->payment_method === Contribution::PAYMENT_METHOD_CASH_ACCOUNT) {
                        $expectedCashDebit = $amount + max(0.0, $lateFee);
                        $memberCashDebits = (float) AccountTransaction::query()
                            ->where('source_type', $contribMorph)
                            ->where('source_id', $contribution->id)
                            ->where('entry_type', 'debit')
                            ->whereNull('deleted_at')
                            ->where('member_id', $memberId)
                            ->whereHas('account', fn($q) => $q
                                ->where('type', Account::TYPE_MEMBER_CASH)
                                ->where('member_id', $memberId))
                            ->sum('amount');

                        if (abs($memberCashDebits - $expectedCashDebit) > self::AMOUNT_TOLERANCE) {
                            $contributionFlowIssues[] = [
                                'contribution_id' => $contribution->id,
                                'issue' => 'member cash debit leg missing/mismatch for cash_account contribution',
                                'expected' => round($expectedCashDebit, 2),
                                'actual' => round($memberCashDebits, 2),
                                'member_id' => $memberId,
                            ];
                        }
                    }

                    if ($contribution->is_late && $lateFee > self::AMOUNT_TOLERANCE) {
                        $masterCashCredits = (float) AccountTransaction::query()
                            ->where('source_type', $contribMorph)
                            ->where('source_id', $contribution->id)
                            ->where('entry_type', 'credit')
                            ->whereNull('deleted_at')
                            ->where('account_id', $masterCash?->id)
                            ->sum('amount');

                        if ($masterCash === null || abs($masterCashCredits - $lateFee) > self::AMOUNT_TOLERANCE) {
                            $contributionFlowIssues[] = [
                                'contribution_id' => $contribution->id,
                                'issue' => 'late-fee master cash credit missing/mismatch',
                                'expected' => round($lateFee, 2),
                                'actual' => round($masterCashCredits, 2),
                            ];
                        }
                    }
                }
            });

        if ($contributionFlowIssues !== []) {
            $incrementCritical();
        }

        $checks['contribution_flow_integrity'] = [
            'label' => 'Contributions — full posting legs integrity by payment type',
            'severity' => $contributionFlowIssues === [] ? 'ok' : 'critical',
            'issue_count' => count($contributionFlowIssues),
            'issues' => array_slice($contributionFlowIssues, 0, 120),
            'issues_truncated' => count($contributionFlowIssues) > 120,
        ];

        // --- 4e) Membership application fee posting integrity ---
        $membershipFeeIssues = [];
        MembershipApplication::query()
            ->whereNull('deleted_at')
            ->whereNotNull('membership_fee_posted_at')
            ->where('membership_fee_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$membershipFeeIssues, $masterCash): void {
                foreach ($rows as $application) {
                    if (!$application instanceof MembershipApplication) {
                        continue;
                    }

                    $expected = (float) $application->membership_fee_amount;
                    $actual = (float) AccountTransaction::query()
                        ->where('source_type', MembershipApplication::class)
                        ->where('source_id', $application->id)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('account_id', $masterCash?->id)
                        ->sum('amount');

                    if ($masterCash === null || abs($actual - $expected) > self::AMOUNT_TOLERANCE) {
                        $membershipFeeIssues[] = [
                            'membership_application_id' => $application->id,
                            'issue' => 'master cash membership-fee credit missing/mismatch',
                            'expected' => round($expected, 2),
                            'actual' => round($actual, 2),
                        ];
                    }
                }
            });

        if ($membershipFeeIssues !== []) {
            $incrementCritical();
        }

        $checks['membership_application_fee_integrity'] = [
            'label' => 'Membership application fees — master cash credit integrity',
            'severity' => $membershipFeeIssues === [] ? 'ok' : 'critical',
            'issue_count' => count($membershipFeeIssues),
            'issues' => array_slice($membershipFeeIssues, 0, 100),
            'issues_truncated' => count($membershipFeeIssues) > 100,
        ];

        // --- 4f) Subscription fee posting integrity ---
        $subscriptionFeeIssues = [];
        MemberSubscriptionFee::query()
            ->whereNull('deleted_at')
            ->where('amount', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$subscriptionFeeIssues, $masterCash): void {
                foreach ($rows as $fee) {
                    if (!$fee instanceof MemberSubscriptionFee) {
                        continue;
                    }

                    $expected = (float) $fee->amount;
                    $actual = (float) AccountTransaction::query()
                        ->where('source_type', MemberSubscriptionFee::class)
                        ->where('source_id', $fee->id)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('account_id', $masterCash?->id)
                        ->sum('amount');

                    if ($masterCash === null || abs($actual - $expected) > self::AMOUNT_TOLERANCE) {
                        $subscriptionFeeIssues[] = [
                            'subscription_fee_id' => $fee->id,
                            'issue' => 'master cash subscription-fee credit missing/mismatch',
                            'expected' => round($expected, 2),
                            'actual' => round($actual, 2),
                        ];
                    }
                }
            });

        if ($subscriptionFeeIssues !== []) {
            $incrementCritical();
        }

        $checks['subscription_fee_integrity'] = [
            'label' => 'Subscription fees — master cash credit integrity',
            'severity' => $subscriptionFeeIssues === [] ? 'ok' : 'critical',
            'issue_count' => count($subscriptionFeeIssues),
            'issues' => array_slice($subscriptionFeeIssues, 0, 100),
            'issues_truncated' => count($subscriptionFeeIssues) > 100,
        ];

        // --- 4g) Loan installment posting integrity (repayment + borrower/guarantor legs) ---
        $loanInstallmentFlowIssues = [];
        $masterFundId = $masterFund?->id;
        $masterCashId = $masterCash?->id;

        LoanInstallment::query()
            ->whereNull('deleted_at')
            ->where(function ($q): void {
                $q->where('status', 'paid')->orWhere('paid_by_guarantor', true);
            })
            ->with('loan')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$loanInstallmentFlowIssues, $masterFundId, $masterCashId): void {
                foreach ($rows as $installment) {
                    if (!$installment instanceof LoanInstallment || !$installment->loan) {
                        continue;
                    }

                    $sourceType = LoanInstallment::class;
                    $sourceId = (int) $installment->id;
                    $amount = (float) $installment->amount;
                    $lateFee = (float) ($installment->late_fee_amount ?? 0);
                    $borrowerId = (int) ($installment->loan->member_id ?? 0);
                    $guarantorId = (int) ($installment->loan->guarantor_member_id ?? 0);

                    $masterFundCredits = (float) AccountTransaction::query()
                        ->where('source_type', $sourceType)
                        ->where('source_id', $sourceId)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('account_id', $masterFundId)
                        ->sum('amount');
                    if ($masterFundId === null || abs($masterFundCredits - $amount) > self::AMOUNT_TOLERANCE) {
                        $loanInstallmentFlowIssues[] = [
                            'installment_id' => $sourceId,
                            'loan_id' => $installment->loan_id,
                            'issue' => 'master fund credit leg missing/mismatch',
                            'expected' => round($amount, 2),
                            'actual' => round($masterFundCredits, 2),
                        ];
                    }

                    $memberFundCredits = (float) AccountTransaction::query()
                        ->where('source_type', $sourceType)
                        ->where('source_id', $sourceId)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->where('member_id', $borrowerId)
                        ->whereHas('account', fn($q) => $q
                            ->where('type', Account::TYPE_MEMBER_FUND)
                            ->where('member_id', $borrowerId))
                        ->sum('amount');
                    if (abs($memberFundCredits - $amount) > self::AMOUNT_TOLERANCE) {
                        $loanInstallmentFlowIssues[] = [
                            'installment_id' => $sourceId,
                            'loan_id' => $installment->loan_id,
                            'issue' => 'borrower member fund credit leg missing/mismatch',
                            'expected' => round($amount, 2),
                            'actual' => round($memberFundCredits, 2),
                            'member_id' => $borrowerId,
                        ];
                    }

                    $loanAccountCredits = (float) AccountTransaction::query()
                        ->where('source_type', $sourceType)
                        ->where('source_id', $sourceId)
                        ->where('entry_type', 'credit')
                        ->whereNull('deleted_at')
                        ->whereHas('account', fn($q) => $q
                            ->where('type', Account::TYPE_LOAN)
                            ->where('loan_id', $installment->loan_id))
                        ->sum('amount');
                    if (abs($loanAccountCredits - $amount) > self::AMOUNT_TOLERANCE) {
                        $loanInstallmentFlowIssues[] = [
                            'installment_id' => $sourceId,
                            'loan_id' => $installment->loan_id,
                            'issue' => 'loan account credit leg missing/mismatch',
                            'expected' => round($amount, 2),
                            'actual' => round($loanAccountCredits, 2),
                        ];
                    }

                    if ($installment->is_late && $lateFee > self::AMOUNT_TOLERANCE) {
                        $lateFeeMasterCashCredits = (float) AccountTransaction::query()
                            ->where('source_type', $sourceType)
                            ->where('source_id', $sourceId)
                            ->where('entry_type', 'credit')
                            ->whereNull('deleted_at')
                            ->where('account_id', $masterCashId)
                            ->sum('amount');

                        if ($masterCashId === null || abs($lateFeeMasterCashCredits - $lateFee) > self::AMOUNT_TOLERANCE) {
                            $loanInstallmentFlowIssues[] = [
                                'installment_id' => $sourceId,
                                'loan_id' => $installment->loan_id,
                                'issue' => 'late-fee master cash credit missing/mismatch',
                                'expected' => round($lateFee, 2),
                                'actual' => round($lateFeeMasterCashCredits, 2),
                            ];
                        }
                    }

                    if ((bool) $installment->paid_by_guarantor) {
                        $guarantorFundDebits = (float) AccountTransaction::query()
                            ->where('source_type', $sourceType)
                            ->where('source_id', $sourceId)
                            ->where('entry_type', 'debit')
                            ->whereNull('deleted_at')
                            ->where('member_id', $guarantorId)
                            ->whereHas('account', fn($q) => $q
                                ->where('type', Account::TYPE_MEMBER_FUND)
                                ->where('member_id', $guarantorId))
                            ->sum('amount');

                        if ($guarantorId <= 0 || abs($guarantorFundDebits - $amount) > self::AMOUNT_TOLERANCE) {
                            $loanInstallmentFlowIssues[] = [
                                'installment_id' => $sourceId,
                                'loan_id' => $installment->loan_id,
                                'issue' => 'guarantor fund debit missing/mismatch',
                                'expected' => round($amount, 2),
                                'actual' => round($guarantorFundDebits, 2),
                                'guarantor_member_id' => $guarantorId ?: null,
                            ];
                        }
                    } else {
                        $expectedCashDebit = $amount + max(0.0, $lateFee);
                        $borrowerCashDebits = (float) AccountTransaction::query()
                            ->where('source_type', $sourceType)
                            ->where('source_id', $sourceId)
                            ->where('entry_type', 'debit')
                            ->whereNull('deleted_at')
                            ->where('member_id', $borrowerId)
                            ->whereHas('account', fn($q) => $q
                                ->where('type', Account::TYPE_MEMBER_CASH)
                                ->where('member_id', $borrowerId))
                            ->sum('amount');

                        if (abs($borrowerCashDebits - $expectedCashDebit) > self::AMOUNT_TOLERANCE) {
                            $loanInstallmentFlowIssues[] = [
                                'installment_id' => $sourceId,
                                'loan_id' => $installment->loan_id,
                                'issue' => 'borrower cash debit missing/mismatch',
                                'expected' => round($expectedCashDebit, 2),
                                'actual' => round($borrowerCashDebits, 2),
                                'member_id' => $borrowerId,
                            ];
                        }
                    }
                }
            });

        if ($loanInstallmentFlowIssues !== []) {
            $incrementCritical();
        }

        $checks['loan_installment_flow_integrity'] = [
            'label' => 'Loan installments — repayment and cash/guarantor legs integrity',
            'severity' => $loanInstallmentFlowIssues === [] ? 'ok' : 'critical',
            'issue_count' => count($loanInstallmentFlowIssues),
            'issues' => array_slice($loanInstallmentFlowIssues, 0, 120),
            'issues_truncated' => count($loanInstallmentFlowIssues) > 120,
        ];

        // --- 4h) Member cash transfer integrity (member-sourced transfer rows) ---
        $memberTransferIssues = [];
        $memberTransferGroupRows = AccountTransaction::query()
            ->where('source_type', Member::class)
            ->whereNull('deleted_at')
            ->whereHas('account', fn($q) => $q->where('type', Account::TYPE_MEMBER_CASH))
            ->where(function ($q): void {
                $q->where('description', 'like', 'Transfer to % cash account%')
                    ->orWhere('description', 'like', 'Transfer from % cash account%');
            })
            ->get(['id', 'amount', 'entry_type', 'transacted_at']);

        $groupedTransfers = $memberTransferGroupRows->groupBy(function ($row): string {
            $ts = optional($row->transacted_at)?->format('Y-m-d H:i:s') ?? 'na';

            return $ts . '|' . number_format((float) $row->amount, 2, '.', '');
        });

        foreach ($groupedTransfers as $groupKey => $rows) {
            $creditSum = (float) $rows->where('entry_type', 'credit')->sum('amount');
            $debitSum = (float) $rows->where('entry_type', 'debit')->sum('amount');

            if (
                abs($creditSum - $debitSum) > self::AMOUNT_TOLERANCE
                || $rows->where('entry_type', 'credit')->count() === 0
                || $rows->where('entry_type', 'debit')->count() === 0
            ) {
                $memberTransferIssues[] = [
                    'group' => $groupKey,
                    'rows' => $rows->pluck('id')->all(),
                    'credit_sum' => round($creditSum, 2),
                    'debit_sum' => round($debitSum, 2),
                    'row_count' => $rows->count(),
                    'issue' => 'cash transfer group does not net to zero with both debit and credit legs',
                ];
            }
        }

        if ($memberTransferIssues !== []) {
            $incrementCritical();
        }

        $checks['member_cash_transfer_integrity'] = [
            'label' => 'Member cash transfers — paired debit/credit integrity',
            'severity' => $memberTransferIssues === [] ? 'ok' : 'critical',
            'group_count' => $groupedTransfers->count(),
            'issue_count' => count($memberTransferIssues),
            'issues' => array_slice($memberTransferIssues, 0, 80),
            'issues_truncated' => count($memberTransferIssues) > 80,
            'note' => 'Groups by timestamp+amount for transfer descriptions and expects equal debit/credit totals.',
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
                'contribution_flow_integrity' => $checks['contribution_flow_integrity']['severity'],
                'membership_application_fee_integrity' => $checks['membership_application_fee_integrity']['severity'],
                'subscription_fee_integrity' => $checks['subscription_fee_integrity']['severity'],
                'member_portal_posting_integrity' => $checks['member_portal_posting_integrity']['severity'],
                'bank_transaction_posting_integrity' => $checks['bank_transaction_posting_integrity']['severity'],
                'sms_transaction_posting_integrity' => $checks['sms_transaction_posting_integrity']['severity'],
                'loan_installment_flow_integrity' => $checks['loan_installment_flow_integrity']['severity'],
                'member_cash_transfer_integrity' => $checks['member_cash_transfer_integrity']['severity'],
                'orphan_loan_accounts' => $checks['orphan_loan_accounts']['severity'],
                'paired_control_totals' => $checks['paired_control_totals']['severity'],
                'active_loans' => $checks['active_loans_schedule_vs_ledger']['severity'],
                'approved_loans' => $checks['approved_loans_disbursement_vs_ledger']['severity'],
                'loan_disbursement_cash_payout_integrity' => $checks['loan_disbursement_cash_payout_integrity']['severity'],
            ],
            'pipeline' => $pipeline,
            'as_of' => $meta['as_of'],
        ];

        $checkSeverity = static function (string $key) use ($checks): string {
            return (string) ($checks[$key]['severity'] ?? 'unknown');
        };
        $covRow = static function (string $flow, array $keys) use ($checkSeverity): array {
            return [
                'flow' => $flow,
                'checks' => array_map(
                    fn(string $k): array => ['key' => $k, 'severity' => $checkSeverity($k)],
                    $keys,
                ),
            ];
        };

        $coverage_matrix = [
            $covRow('Book-wide: stored balance vs ledger; trial balance; paired control totals', ['ledger_balances', 'global_trial', 'paired_control_totals']),
            $covRow('Master cash vs declared bank / statement balance (optional)', ['bank_statement_vs_book']),
            $covRow('Bank import rows → ledger posting hygiene', ['bank_transaction_posting_integrity']),
            $covRow('SMS import rows → ledger posting hygiene', ['sms_transaction_posting_integrity']),
            $covRow('Member portal “post funds” → ledger', ['member_portal_posting_integrity']),
            $covRow('Contribution cycle: rows vs member fund + master fund legs', ['contribution_flow_integrity']),
            $covRow('Contributions: master fund credits & per-row ledger presence', ['contributions_ledger']),
            $covRow('Membership application fee → master cash', ['membership_application_fee_integrity']),
            $covRow('Subscription fee → master cash', ['subscription_fee_integrity']),
            $covRow('Loan disbursement: cash payout to member vs approved loan', ['loan_disbursement_cash_payout_integrity', 'approved_loans_disbursement_vs_ledger']),
            $covRow('Active loans: schedule vs loan ledger', ['active_loans_schedule_vs_ledger']),
            $covRow('Loan installments / repayments — paired flow', ['loan_installment_flow_integrity']),
            $covRow('Member cash transfers — debit/credit pairing', ['member_cash_transfer_integrity']),
            $covRow('Loan-type accounts missing a loan row', ['orphan_loan_accounts']),
        ];

        return [
            'meta' => $meta,
            'verdict' => $verdict,
            'checks' => $checks,
            'coverage_matrix' => $coverage_matrix,
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
