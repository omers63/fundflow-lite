<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\SmsTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    // =========================================================================
    // Member & Loan account setup (idempotent)
    // =========================================================================

    /**
     * Ensure the two member virtual accounts exist.
     * Safe to call multiple times — uses firstOrCreate.
     */
    public function ensureMemberAccounts(Member $member): void
    {
        Account::firstOrCreate(
            ['type' => Account::TYPE_MEMBER_CASH, 'member_id' => $member->id],
            ['name' => "Cash – {$member->user->name}", 'balance' => 0, 'is_active' => true]
        );

        Account::firstOrCreate(
            ['type' => Account::TYPE_MEMBER_FUND, 'member_id' => $member->id],
            ['name' => "Fund – {$member->user->name}", 'balance' => 0, 'is_active' => true]
        );
    }

    /**
     * Ensure the loan's virtual account exists.
     */
    public function ensureLoanAccount(Loan $loan): Account
    {
        return Account::firstOrCreate(
            ['type' => Account::TYPE_LOAN, 'loan_id' => $loan->id],
            [
                'name' => "Loan #{$loan->id} – {$loan->member->user->name}",
                'member_id' => $loan->member_id,
                'balance' => 0,
                'is_active' => true,
            ]
        );
    }

    // =========================================================================
    // Member CSV import — balance adjustments (paired with master accounts)
    // =========================================================================

    /**
     * Apply cash and/or fund adjustments from CSV import (new or existing member).
     *
     * Cash: non-negative only. A positive amount credits master + member cash (same as a deposit).
     *
     * Fund: a positive amount credits master + member fund (same as a contribution). A negative
     * amount debits both by the absolute value (same direction as disbursement drawing from both).
     *
     * @param  float  $cashAmount  SAR adjustment, must be >= 0
     * @param  float  $fundAmount  SAR adjustment; may be negative (e.g. master-funded loan context)
     */
    public function applyImportedBalanceAdjustments(Member $member, float $cashAmount, float $fundAmount): void
    {
        if ($cashAmount < 0) {
            throw new \InvalidArgumentException('Cash balance adjustment cannot be negative.');
        }

        if ($cashAmount <= 0 && abs($fundAmount) < 0.00001) {
            return;
        }

        $this->ensureMemberAccounts($member);
        $member->loadMissing('user');

        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $masterCash = Account::masterCash();
        $masterFund = Account::masterFund();

        $label = $member->user->name ?? 'Member';

        if ($cashAmount > 0) {
            $description = "Cash balance adjustment (member import) – {$label}";
            $this->postEntry($masterCash, $cashAmount, 'credit', $description, null, $member->id);
            $this->postEntry($memberCash, $cashAmount, 'credit', $description, null, $member->id);
        }

        if ($fundAmount > 0) {
            $description = "Fund balance adjustment — credit (member import) – {$label}";
            $this->postEntry($masterFund, $fundAmount, 'credit', $description, null, $member->id);
            $this->postEntry($memberFund, $fundAmount, 'credit', $description, null, $member->id);
        } elseif ($fundAmount < 0) {
            $amount = abs($fundAmount);
            $description = "Fund balance adjustment — debit (member import) – {$label}";
            $this->postEntry($masterFund, $amount, 'debit', $description, null, $member->id);
            $this->postEntry($memberFund, $amount, 'debit', $description, null, $member->id);
        }
    }

    // =========================================================================
    // Cash Account posting (bank / SMS imports)
    // =========================================================================

    /**
     * Post a bank transaction to the master Cash Account and the member's Cash Account.
     * Marks the BankTransaction as posted.
     */
    public function postBankTransactionToCash(BankTransaction $tx, Member $member): void
    {
        if ($tx->isPosted()) {
            return;
        }

        $this->ensureMemberAccounts($member);

        $masterCash = Account::masterCash();
        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $entryType = $tx->transaction_type === 'credit' ? 'credit' : 'debit';
        $description = sprintf(
            '%s transaction on %s — %s',
            ucfirst($entryType),
            $tx->transaction_date->format('d M Y'),
            $tx->description ?? $tx->reference ?? 'Bank import'
        );

        DB::transaction(function () use ($tx, $member, $masterCash, $memberCash, $entryType, $description) {
            $this->postEntry($masterCash, $tx->amount, $entryType, $description, $tx, $member->id);
            $this->postEntry($memberCash, $tx->amount, $entryType, $description, $tx, $member->id);

            $tx->update([
                'member_id' => $member->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Post an SMS transaction to the master Cash Account and the member's Cash Account.
     */
    public function postSmsTransactionToCash(SmsTransaction $tx, Member $member): void
    {
        if ($tx->isPosted()) {
            return;
        }

        $this->ensureMemberAccounts($member);

        $masterCash = Account::masterCash();
        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $entryType = $tx->transaction_type === 'credit' ? 'credit' : 'debit';
        $description = sprintf(
            'SMS %s on %s — %s',
            ucfirst($entryType),
            $tx->transaction_date?->format('d M Y') ?? '?',
            $tx->reference ?? substr($tx->raw_sms, 0, 80)
        );

        DB::transaction(function () use ($tx, $member, $masterCash, $memberCash, $entryType, $description) {
            $this->postEntry($masterCash, $tx->amount, $entryType, $description, $tx, $member->id);
            $this->postEntry($memberCash, $tx->amount, $entryType, $description, $tx, $member->id);

            $tx->update([
                'member_id' => $member->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    // =========================================================================
    // Fund Account posting (contributions / loans)
    // =========================================================================

    /**
     * Post a contribution to the master Fund Account and the member's Fund Account.
     * Called automatically by ContributionObserver.
     */
    public function postContribution(Contribution $contribution): void
    {
        $member = $contribution->member;
        $this->ensureMemberAccounts($member);

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = sprintf(
            'Contribution – %s %s',
            $contribution->month ? date('F', mktime(0, 0, 0, $contribution->month, 1)) : '',
            $contribution->year ?? ''
        );

        DB::transaction(function () use ($contribution, $member, $masterFund, $memberFund, $description) {
            $this->postEntry($masterFund, $contribution->amount, 'credit', $description, $contribution, $member->id);
            $this->postEntry($memberFund, $contribution->amount, 'credit', $description, $contribution, $member->id);
        });
    }

    /**
     * Post a loan disbursement with split funding:
     *  - Member's fund account is debited first (member_portion = min(fund_balance, loan_amount)).
     *  - Master fund account is debited for the remainder (master_portion).
     *  - Loan account records the total outstanding.
     *  - Loan model is updated with the portion breakdown.
     */
    public function postLoanDisbursement(Loan $loan): void
    {
        $member = $loan->member;
        $this->ensureMemberAccounts($member);
        $loanAccount = $this->ensureLoanAccount($loan);

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $totalAmount = (float) $loan->amount_approved;
        $memberPortion = min((float) $memberFund->balance, $totalAmount);
        $masterPortion = $totalAmount - $memberPortion;

        $description = "Loan #{$loan->id} disbursement – {$member->user->name}";

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $loanAccount, $description, $totalAmount, $memberPortion, $masterPortion) {
            // Debit member's fund (up to its full balance)
            if ($memberPortion > 0) {
                $this->postEntry($memberFund, $memberPortion, 'debit', $description . ' (member portion)', $loan, $member->id);
            }
            // Debit master fund for the remainder
            if ($masterPortion > 0) {
                $this->postEntry($masterFund, $masterPortion, 'debit', $description . ' (fund portion)', $loan, $member->id);
            }
            // Loan account tracks total outstanding
            $this->postEntry($loanAccount, $totalAmount, 'debit', $description, $loan, $member->id);

            // Snapshot portions onto the loan record
            $loan->update([
                'member_portion' => $memberPortion,
                'master_portion' => $masterPortion,
            ]);
        });
    }

    /**
     * Post disbursement using explicit member/master portions (CSV / migration import).
     * Unlike {@see postLoanDisbursement}, portions are not derived from the member's current fund balance.
     */
    public function postLoanDisbursementWithPortions(Loan $loan, float $memberPortion, float $masterPortion): void
    {
        $totalAmount = round((float) $loan->amount_approved, 2);
        $sum = round($memberPortion + $masterPortion, 2);
        if (abs($sum - $totalAmount) > 0.02) {
            throw new \InvalidArgumentException(
                'member_portion + master_portion must equal amount_approved (within 0.02 SAR).'
            );
        }

        if ($memberPortion < -0.02 || $masterPortion < -0.02) {
            throw new \InvalidArgumentException('Portions cannot be negative.');
        }

        $member = $loan->member;
        $this->ensureMemberAccounts($member);
        $loanAccount = $this->ensureLoanAccount($loan);

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} disbursement (import) – {$member->user->name}";

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $loanAccount, $description, $totalAmount, $memberPortion, $masterPortion) {
            if ($memberPortion > 0) {
                $this->postEntry($memberFund, $memberPortion, 'debit', $description . ' (member portion)', $loan, $member->id);
            }
            if ($masterPortion > 0) {
                $this->postEntry($masterFund, $masterPortion, 'debit', $description . ' (fund portion)', $loan, $member->id);
            }
            $this->postEntry($loanAccount, $totalAmount, 'debit', $description, $loan, $member->id);

            $loan->update([
                'member_portion' => $memberPortion,
                'master_portion' => $masterPortion,
            ]);
        });
    }

    /**
     * Apply cumulative repayments already collected before go-live (import), matching {@see postLoanRepayment}
     * ledger pattern without touching individual installment rows (those are created separately as paid).
     */
    public function postImportedLoanRepayments(Loan $loan, float $totalRepaid): void
    {
        if ($totalRepaid <= 0.00001) {
            return;
        }

        $member = $loan->member;
        $this->ensureMemberAccounts($member);

        $loanAccount = Account::where('type', Account::TYPE_LOAN)
            ->where('loan_id', $loan->id)
            ->first();

        if (!$loanAccount) {
            $loanAccount = $this->ensureLoanAccount($loan);
        }

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} repayments (import, bulk) – {$member->user->name}";

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $loanAccount, $description, $totalRepaid) {
            $this->postEntry($masterFund, $totalRepaid, 'credit', $description, $loan, $member->id);
            $this->postEntry($memberFund, $totalRepaid, 'credit', $description, $loan, $member->id);
            $this->postEntry($loanAccount, $totalRepaid, 'credit', $description, $loan, $member->id);

            $loan->increment('repaid_to_master', $totalRepaid);
            $loan->refresh();
            $loan->releaseGuarantorIfDue();
        });
    }

    /**
     * Post a loan repayment to the master Fund Account (credit), the member's Fund Account (credit),
     * and credit the loan's own account (reduces outstanding balance).
     * Called automatically by LoanInstallmentObserver when status → paid.
     */
    public function postLoanRepayment(LoanInstallment $installment): void
    {
        $loan = $installment->loan;
        $member = $loan->member;
        $this->ensureMemberAccounts($member);

        $loanAccount = Account::where('type', Account::TYPE_LOAN)
            ->where('loan_id', $loan->id)
            ->first();

        if (!$loanAccount) {
            $loanAccount = $this->ensureLoanAccount($loan);
        }

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} repayment (installment #{$installment->installment_number}) – {$member->user->name}";
        $amount = (float) $installment->amount;

        DB::transaction(function () use ($installment, $loan, $member, $masterFund, $memberFund, $loanAccount, $description, $amount) {
            // Fund and member fund both increase on every repayment
            $this->postEntry($masterFund, $amount, 'credit', $description, $installment, $member->id);
            $this->postEntry($memberFund, $amount, 'credit', $description, $installment, $member->id);
            // Loan account: credit reduces outstanding balance
            $this->postEntry($loanAccount, $amount, 'credit', $description, $installment, $member->id);

            // Track how much has been credited back to the master fund (for guarantor release)
            $loan->increment('repaid_to_master', $amount);
            $loan->refresh();
            $loan->releaseGuarantorIfDue();
        });
    }

    // =========================================================================
    // Cash debit for loan repayment cycle
    // =========================================================================

    /**
     * Debit a member's Cash Account for a loan repayment installment.
     * Called by LoanRepaymentService before marking the installment as paid.
     */
    public function debitCashForRepayment(Member $member, LoanInstallment $installment): void
    {
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = sprintf(
            'Loan #%d repayment – installment %d of %d',
            $installment->loan_id,
            $installment->installment_number,
            $installment->loan->installments_count
        );

        $this->postEntry($cashAccount, (float) $installment->amount, 'debit', $description, $installment, $member->id);
    }

    // =========================================================================
    // Guarantor fund debit (on member default)
    // =========================================================================

    /**
     * Debit the guarantor's Fund Account for a defaulted installment.
     * Called by LoanDefaultService.
     */
    public function debitGuarantorFundForDefault(Member $guarantor, LoanInstallment $installment): void
    {
        $this->ensureMemberAccounts($guarantor);

        $guarantorFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $guarantor->id)
            ->firstOrFail();

        $description = sprintf(
            'Guarantor debit – Loan #%d installment %d (borrower: %s)',
            $installment->loan_id,
            $installment->installment_number,
            $installment->loan->member->user->name
        );

        $this->postEntry($guarantorFund, (float) $installment->amount, 'debit', $description, $installment, $guarantor->id);

        $installment->update(['paid_by_guarantor' => true]);
    }

    // =========================================================================
    // Cash debit for contribution cycle
    // =========================================================================

    /**
     * Debit a member's Cash Account as part of the contribution cycle.
     * Called by ContributionCycleService before creating the Contribution record.
     */
    public function debitCashForContribution(Member $member, float $amount, int $month, int $year): void
    {
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $description = "Contribution deduction – {$monthName} {$year}";

        $this->postEntry($cashAccount, $amount, 'debit', $description, null, $member->id);
    }

    // =========================================================================
    // Parent → Dependent cash transfer
    // =========================================================================

    /**
     * Transfer funds from a parent member's Cash Account to a dependent's Cash Account.
     * Intended for funding contributions and loan repayments.
     *
     * @throws \RuntimeException when parent has insufficient balance.
     */
    public function fundDependentCashAccount(
        Member $parent,
        Member $dependent,
        float $amount,
        string $note = '',
    ): void {
        $this->ensureMemberAccounts($parent);
        $this->ensureMemberAccounts($dependent);

        $parentCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $parent->id)
            ->firstOrFail();

        $dependentCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $dependent->id)
            ->firstOrFail();

        if ((float) $parentCash->balance < $amount) {
            throw new \RuntimeException(
                "Insufficient balance in {$parent->user->name}'s Cash Account. " .
                'Available: SAR ' . number_format((float) $parentCash->balance, 2)
            );
        }

        $debitDesc = trim("Transfer to {$dependent->user->name}'s cash account" . ($note ? " — {$note}" : ''));
        $creditDesc = trim("Transfer from {$parent->user->name}'s cash account" . ($note ? " — {$note}" : ''));

        DB::transaction(function () use ($parent, $dependent, $parentCash, $dependentCash, $amount, $debitDesc, $creditDesc) {
            $this->postEntry($parentCash, $amount, 'debit', $debitDesc, null, $parent->id);
            $this->postEntry($dependentCash, $amount, 'credit', $creditDesc, null, $dependent->id);
        });
    }

    // =========================================================================
    // Safe deletion: reverse ledger effects, then remove rows
    // =========================================================================

    /**
     * Reverse fund postings for a contribution (paired master + member fund lines).
     */
    public function reverseContributionPosting(Contribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $entries = AccountTransaction::query()
                ->where('source_type', Contribution::class)
                ->where('source_id', $contribution->id)
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->applyLedgerEntryReversal($entry);
            }
        });
    }

    /**
     * Reverse one ledger line (adjust account balance oppositely) and delete the row.
     */
    public function reverseSingleLedgerEntry(AccountTransaction $entry): void
    {
        DB::transaction(function () use ($entry) {
            $this->applyLedgerEntryReversal($entry);
        });
    }

    /** Must run inside an outer DB transaction when batching. */
    private function applyLedgerEntryReversal(AccountTransaction $entry): void
    {
        $account = Account::query()->lockForUpdate()->findOrFail($entry->account_id);

        if ($entry->entry_type === 'credit') {
            $account->decrement('balance', $entry->amount);
        } else {
            $account->increment('balance', $entry->amount);
        }

        $entry->delete();
    }

    /**
     * Remove cash postings for a bank import line (master + member cash) and clear posted flags.
     */
    public function reverseBankTransactionPosting(BankTransaction $tx): void
    {
        if (!$tx->isPosted()) {
            return;
        }

        DB::transaction(function () use ($tx) {
            $entries = AccountTransaction::query()
                ->where('source_type', BankTransaction::class)
                ->where('source_id', $tx->id)
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->applyLedgerEntryReversal($entry);
            }

            $tx->update([
                'posted_at' => null,
                'posted_by' => null,
                'member_id' => null,
            ]);
        });
    }

    /**
     * Remove cash postings for an SMS import line (master + member cash) and clear posted flags.
     */
    public function reverseSmsTransactionPosting(SmsTransaction $tx): void
    {
        if (!$tx->isPosted()) {
            return;
        }

        DB::transaction(function () use ($tx) {
            $entries = AccountTransaction::query()
                ->where('source_type', SmsTransaction::class)
                ->where('source_id', $tx->id)
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->applyLedgerEntryReversal($entry);
            }

            $tx->update([
                'posted_at' => null,
                'posted_by' => null,
                'member_id' => null,
            ]);
        });
    }

    public function safeDeleteBankTransaction(BankTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            if ($tx->isPosted()) {
                $entries = AccountTransaction::query()
                    ->where('source_type', BankTransaction::class)
                    ->where('source_id', $tx->id)
                    ->lockForUpdate()
                    ->get();
                foreach ($entries as $entry) {
                    $this->applyLedgerEntryReversal($entry);
                }
            }
            $tx->delete();
        });
    }

    public function safeDeleteSmsTransaction(SmsTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            if ($tx->isPosted()) {
                $entries = AccountTransaction::query()
                    ->where('source_type', SmsTransaction::class)
                    ->where('source_id', $tx->id)
                    ->lockForUpdate()
                    ->get();
                foreach ($entries as $entry) {
                    $this->applyLedgerEntryReversal($entry);
                }
            }
            $tx->delete();
        });
    }

    /**
     * Delete a single ledger entry after reversing its effect on the account balance.
     * Does not attempt to repair paired entries from the same source.
     */
    public function safeDeleteAccountTransaction(AccountTransaction $entry): void
    {
        $this->reverseSingleLedgerEntry($entry);
    }

    /**
     * Reverse every ledger line tied to this loan (disbursement, bulk import repayments,
     * per-installment fund repayments, cash debits, guarantor debits), remove installments,
     * drop the loan virtual account, then delete the loan row.
     */
    public function safeDeleteLoan(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            $loan->refresh();
            $installmentIds = $loan->installments()->pluck('id')->all();

            $entries = AccountTransaction::query()
                ->where(function ($q) use ($loan, $installmentIds) {
                    $q->where(function ($q2) use ($loan) {
                        $q2->where('source_type', Loan::class)
                            ->where('source_id', $loan->id);
                    });
                    if ($installmentIds !== []) {
                        $q->orWhere(function ($q2) use ($installmentIds) {
                            $q2->where('source_type', LoanInstallment::class)
                                ->whereIn('source_id', $installmentIds);
                        });
                    }
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($entries as $entry) {
                $this->applyLedgerEntryReversal($entry);
            }

            $loanAccountIds = Account::query()
                ->where('type', Account::TYPE_LOAN)
                ->where('loan_id', $loan->id)
                ->pluck('id');

            if ($loanAccountIds->isNotEmpty()) {
                $stragglers = AccountTransaction::query()
                    ->whereIn('account_id', $loanAccountIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($stragglers as $entry) {
                    $this->applyLedgerEntryReversal($entry);
                }
            }

            $loan->installments()->delete();

            Account::query()
                ->where('type', Account::TYPE_LOAN)
                ->where('loan_id', $loan->id)
                ->delete();

            $loan->delete();
        });
    }

    /**
     * Post a single manual line on one account (no paired master/member posting).
     * Use for adjustments and corrections on the account ledger only.
     */
    public function postManualLedgerEntry(
        Account $account,
        string $entryType,
        float $amount,
        string $description,
        ?int $memberId = null,
        CarbonInterface|string|null $transactedAt = null,
    ): AccountTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        if (!in_array($entryType, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Entry type must be credit or debit.');
        }

        $trimmed = trim($description);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Description is required.');
        }

        $memberId = $memberId ?? $account->member_id;

        if ($transactedAt !== null && !$transactedAt instanceof CarbonInterface) {
            $transactedAt = Carbon::parse($transactedAt);
        }

        return DB::transaction(function () use ($account, $entryType, $amount, $trimmed, $memberId, $transactedAt) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);

            return $this->postEntry($account, $amount, $entryType, $trimmed, null, $memberId, $transactedAt);
        });
    }

    // =========================================================================
    // Internal: create one ledger entry and update the account balance atomically
    // =========================================================================

    private function postEntry(
        Account $account,
        float $amount,
        string $entryType,
        string $description,
        mixed $source,
        ?int $memberId = null,
        ?CarbonInterface $transactedAt = null,
    ): AccountTransaction {
        $entry = AccountTransaction::create([
            'account_id' => $account->id,
            'amount' => $amount,
            'entry_type' => $entryType,
            'description' => $description,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->id,
            'member_id' => $memberId,
            'posted_by' => auth()->id() ?? 1,
            'transacted_at' => $transactedAt ?? now(),
        ]);

        // Update running balance atomically
        if ($entryType === 'credit') {
            $account->increment('balance', $amount);
        } else {
            $account->decrement('balance', $amount);
        }

        return $entry;
    }
}
