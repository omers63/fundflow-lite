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
                'name'      => "Loan #{$loan->id} – {$loan->member->user->name}",
                'member_id' => $loan->member_id,
                'balance'   => 0,
                'is_active' => true,
            ]
        );
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
     * Post a loan disbursement to the master Fund Account (debit), the member's Fund Account (debit),
     * and create/debit the loan's own account.
     * Called after loan approval.
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

        $description = "Loan #{$loan->id} disbursement – {$member->user->name}";
        $amount      = (float) $loan->amount_approved;

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $loanAccount, $description, $amount) {
            // Fund decreases (money goes out)
            $this->postEntry($masterFund, $amount, 'debit', $description, $loan, $member->id);
            $this->postEntry($memberFund, $amount, 'debit', $description, $loan, $member->id);
            // Loan account: debit = money owed by member (balance goes negative = outstanding)
            $this->postEntry($loanAccount, $amount, 'debit', $description, $loan, $member->id);
        });
    }

    /**
     * Post a loan repayment to the master Fund Account (credit), the member's Fund Account (credit),
     * and credit the loan's own account (reduces outstanding balance).
     * Called automatically by LoanInstallmentObserver when status → paid.
     */
    public function postLoanRepayment(LoanInstallment $installment): void
    {
        $loan   = $installment->loan;
        $member = $loan->member;
        $this->ensureMemberAccounts($member);

        $loanAccount = Account::where('type', Account::TYPE_LOAN)
            ->where('loan_id', $loan->id)
            ->first();

        if (! $loanAccount) {
            $loanAccount = $this->ensureLoanAccount($loan);
        }

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} repayment (installment #{$installment->installment_number}) – {$member->user->name}";
        $amount      = (float) $installment->amount;

        DB::transaction(function () use ($installment, $member, $masterFund, $memberFund, $loanAccount, $description, $amount) {
            // Fund increases (repayment received)
            $this->postEntry($masterFund, $amount, 'credit', $description, $installment, $member->id);
            $this->postEntry($memberFund, $amount, 'credit', $description, $installment, $member->id);
            // Loan account: credit = reduces outstanding balance
            $this->postEntry($loanAccount, $amount, 'credit', $description, $installment, $member->id);
        });
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
        float  $amount,
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
                "Available: SAR " . number_format((float) $parentCash->balance, 2)
            );
        }

        $debitDesc  = trim("Transfer to {$dependent->user->name}'s cash account" . ($note ? " — {$note}" : ''));
        $creditDesc = trim("Transfer from {$parent->user->name}'s cash account" . ($note ? " — {$note}" : ''));

        DB::transaction(function () use ($parent, $dependent, $parentCash, $dependentCash, $amount, $debitDesc, $creditDesc) {
            $this->postEntry($parentCash,    $amount, 'debit',  $debitDesc,  null, $parent->id);
            $this->postEntry($dependentCash, $amount, 'credit', $creditDesc, null, $dependent->id);
        });
    }

    // =========================================================================
    // Internal: create one ledger entry and update the account balance atomically
    // =========================================================================

    private function postEntry(
        Account $account,
        float   $amount,
        string  $entryType,
        string  $description,
        mixed   $source,
        ?int    $memberId = null,
    ): AccountTransaction {
        $entry = AccountTransaction::create([
            'account_id'    => $account->id,
            'amount'        => $amount,
            'entry_type'    => $entryType,
            'description'   => $description,
            'source_type'   => $source ? get_class($source) : null,
            'source_id'     => $source?->id,
            'member_id'     => $memberId,
            'posted_by'     => auth()->id() ?? 1,
            'transacted_at' => now(),
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
