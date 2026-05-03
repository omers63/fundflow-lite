<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\BankTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\SmsTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
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
            $this->postEntry($masterCash, $cashAmount, 'credit', $description, $member, $member->id);
            $this->postEntry($memberCash, $cashAmount, 'credit', $description, $member, $member->id);
        }

        if ($fundAmount > 0) {
            $description = "Fund balance adjustment — credit (member import) – {$label}";
            $this->postEntry($masterFund, $fundAmount, 'credit', $description, $member, $member->id);
            $this->postEntry($memberFund, $fundAmount, 'credit', $description, $member, $member->id);
        } elseif ($fundAmount < 0) {
            $amount = abs($fundAmount);
            $description = "Fund balance adjustment — debit (member import) – {$label}";
            $this->postEntry($masterFund, $amount, 'debit', $description, $member, $member->id);
            $this->postEntry($memberFund, $amount, 'debit', $description, $member, $member->id);
        }
    }

    // =========================================================================
    // Membership application fee (master cash only — not master fund)
    // =========================================================================

    /**
     * Credit master cash only for a public membership application fee.
     * Does not touch the master fund account. Idempotent when fee already posted.
     */
    public function postMembershipApplicationFeeToMasterCash(MembershipApplication $application): void
    {
        if ($application->membership_fee_posted_at !== null) {
            return;
        }

        $amount = (float) ($application->membership_fee_amount ?? 0);
        if ($amount <= 0.00001) {
            return;
        }

        DB::transaction(function () use ($application, $amount) {
            $application->refresh();

            if ($application->membership_fee_posted_at !== null) {
                return;
            }

            $application->loadMissing('user');
            $masterCash = Account::masterCash();
            $label = $application->user?->name ?? 'Applicant';
            $ref = $application->membership_fee_transfer_reference ?? '—';
            $description = "Membership application fee — {$label} — ref {$ref}";

            $this->postEntry($masterCash, $amount, 'credit', $description, $application, null);

            $application->update(['membership_fee_posted_at' => now()]);
        });
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
        $this->postBankTransactionToCashWithOptionalMember($tx, $member);
    }

    /**
     * Post a bank transaction to master cash, with optional member cash mirroring.
     *
     * - Credit: member is optional. If provided, mirror to member cash.
     * - Debit: member + a specific {@see LoanDisbursement} are required for disbursement
     *   reconciliation. Cash posting is still recorded on master cash (and mirrored to member
     *   cash), but fund accounts are not touched here.
     */
    public function postBankTransactionToCashWithOptionalMember(
        BankTransaction $tx,
        ?Member $member = null,
        ?LoanDisbursement $loanDisbursement = null,
    ): void {
        if ($tx->isPosted()) {
            return;
        }

        $masterCash = Account::masterCash();

        $loan = null;
        if ($tx->transaction_type === 'debit') {
            if (! $member) {
                throw new \InvalidArgumentException('Member is required when posting a debit bank transaction.');
            }
            if (! $loanDisbursement) {
                throw new \InvalidArgumentException('A loan disbursement record is required when posting a debit bank transaction.');
            }
            $loanDisbursement->loadMissing('loan.member');
            $loan = $loanDisbursement->loan;
            if (! $loan) {
                throw new \InvalidArgumentException('Loan disbursement is missing its loan.');
            }
            if ((int) $loan->member_id !== (int) $member->id) {
                throw new \InvalidArgumentException('Selected disbursement must belong to the selected member’s loan.');
            }
        }

        $memberCash = null;
        if ($member) {
            $this->ensureMemberAccounts($member);
            $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
                ->where('member_id', $member->id)
                ->firstOrFail();
        }

        $entryType = $tx->transaction_type === 'credit' ? 'credit' : 'debit';
        $description = sprintf(
            '%s transaction on %s — %s',
            ucfirst($entryType),
            $tx->transaction_date->format('d M Y'),
            $tx->description ?? $tx->reference ?? 'Bank import'
        );

        DB::transaction(function () use ($tx, $member, $loan, $loanDisbursement, $masterCash, $memberCash, $entryType, $description) {
            $this->postEntry($masterCash, $tx->amount, $entryType, $description, $tx, $member?->id);
            if ($memberCash) {
                $this->postEntry($memberCash, $tx->amount, $entryType, $description, $tx, $member?->id);
            }

            $tx->update([
                'member_id' => $member?->id,
                'loan_id' => $loan?->id,
                'loan_disbursement_id' => $loanDisbursement?->id,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Add the member Cash Account mirror for a bank credit that was posted to master cash only.
     *
     * @param  AccountTransaction|null  $masterLedgerLine  When set (e.g. from the Ledger Entries table), that exact master-cash row is updated with {@see Member}. Avoids mismatches with {@see firstOrFail()} and ensures bulk actions touch the selected row.
     *
     * @throws \InvalidArgumentException When the transaction cannot be mirrored.
     */
    public function mirrorBankCreditToMemberCash(BankTransaction $tx, Member $member, ?AccountTransaction $masterLedgerLine = null): void
    {
        if (! $this->canMirrorBankCreditToMemberCash($tx)) {
            throw new \InvalidArgumentException('This bank transaction cannot be mirrored to member cash.');
        }

        $this->ensureMemberAccounts($member);

        $memberCash = Account::query()
            ->where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $masterCash = Account::masterCash();

        if ($masterLedgerLine !== null) {
            if ((int) $masterLedgerLine->account_id !== (int) $masterCash->id) {
                throw new \InvalidArgumentException('Ledger line must belong to the master cash account.');
            }
            if ($masterLedgerLine->source_type !== $tx->getMorphClass()
                || (int) $masterLedgerLine->source_id !== (int) $tx->getKey()) {
                throw new \InvalidArgumentException('Ledger line does not match this bank transaction.');
            }
            $masterLine = $masterLedgerLine;
        } else {
            $masterLine = AccountTransaction::query()
                ->where('account_id', $masterCash->id)
                ->where('source_type', $tx->getMorphClass())
                ->where('source_id', $tx->getKey())
                ->orderBy('id')
                ->firstOrFail();
        }

        DB::transaction(function () use ($tx, $member, $memberCash, $masterLine): void {
            $this->postEntry(
                $memberCash,
                (float) $masterLine->amount,
                $masterLine->entry_type,
                (string) $masterLine->description,
                $tx,
                $member->id,
                $masterLine->transacted_at,
            );

            $tx->update([
                'member_id' => $member->id,
            ]);

            // Ledger UI and filters use AccountTransaction.member_id (not only BankTransaction.member_id).
            $masterLine->update([
                'member_id' => $member->id,
            ]);
        });
    }

    public function canMirrorBankCreditToMemberCash(BankTransaction $tx): bool
    {
        if ($tx->transaction_type !== 'credit' || $tx->member_id !== null) {
            return false;
        }

        if (! $tx->isPosted()) {
            return false;
        }

        if ($this->hasMemberCashMirrorForBankTransaction($tx)) {
            return false;
        }

        return AccountTransaction::query()
            ->where('account_id', Account::masterCash()->id)
            ->where('source_type', $tx->getMorphClass())
            ->where('source_id', $tx->getKey())
            ->exists();
    }

    private function hasMemberCashMirrorForBankTransaction(BankTransaction $tx): bool
    {
        return AccountTransaction::query()
            ->where('source_type', $tx->getMorphClass())
            ->where('source_id', $tx->getKey())
            ->whereHas('account', fn ($q) => $q->where('type', Account::TYPE_MEMBER_CASH))
            ->exists();
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
     * Post ledger effects for a contribution: optional member cash debit (cash_account),
     * then master + member fund credits. Used by ContributionObserver and restores.
     */
    public function postContribution(Contribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $lateFee = (float) ($contribution->late_fee_amount ?? 0);

            if ($contribution->payment_method === Contribution::PAYMENT_METHOD_CASH_ACCOUNT) {
                $this->debitMemberCashForContribution($contribution, $lateFee);
            }

            $member = $contribution->member;
            $this->ensureMemberAccounts($member);

            $masterFund = Account::masterFund();
            $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
                ->where('member_id', $member->id)
                ->firstOrFail();

            $description = sprintf(
                'Contribution – %s %s',
                $contribution->month ? date('F', mktime(0, 0, 0, (int) $contribution->month, 1)) : '',
                $contribution->year ?? ''
            );

            $this->postEntry($masterFund, (float) $contribution->amount, 'credit', $description, $contribution, $member->id);
            $this->postEntry($memberFund, (float) $contribution->amount, 'credit', $description, $contribution, $member->id);

            if ($contribution->is_late && $lateFee > 0.00001) {
                $monthName = $contribution->month
                    ? date('F', mktime(0, 0, 0, (int) $contribution->month, 1))
                    : '';
                $label = $member->user->name ?? 'Member';
                $lateDesc = "Contribution late fee – {$monthName} {$contribution->year} – {$label}";
                $this->postLateFeeCreditToMasterCash($lateFee, $lateDesc, $contribution, $member->id);
            }
        });
    }

    /**
     * Debit member cash for a contribution cycle payment (source = the contribution row).
     * When the contribution is late and carries a late fee, $lateFeeExtra is included in the same debit (bundled transfer).
     */
    public function debitMemberCashForContribution(Contribution $contribution, float $lateFeeExtra = 0.0): void
    {
        $member = $contribution->member;
        $member->loadMissing('user');

        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $monthName = date('F', mktime(0, 0, 0, (int) $contribution->month, 1));
        $description = "Contribution deduction – {$monthName} {$contribution->year}";
        if ($lateFeeExtra > 0.00001) {
            $description .= ' (incl. late fee SAR '.number_format($lateFeeExtra, 2).')';
        }

        $total = (float) $contribution->amount + $lateFeeExtra;
        $this->postEntry($cashAccount, $total, 'debit', $description, $contribution, $member->id);
    }

    /**
     * Post a loan disbursement funded by master fund and mirrored on member fund:
     *  - Master fund account is debited for the full loan amount.
     *  - Member fund account is debited for the same amount as a mirror entry.
     *  - Loan account is debited for total outstanding principal.
     *  - Member cash account is credited for payout to the member.
     *  - Loan model records master_portion = amount, member_portion = 0.
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
        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $totalAmount = (float) $loan->amount_approved;
        $memberPortion = 0.0;
        $masterPortion = $totalAmount;

        $description = "Loan #{$loan->id} disbursement – {$member->user->name}";

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $memberCash, $loanAccount, $description, $totalAmount, $memberPortion, $masterPortion) {
            // Re-read master fund inside the lock to prevent negative balance
            $masterFund = Account::query()->lockForUpdate()->findOrFail($masterFund->id);
            if ($masterPortion > 0 && (float) $masterFund->balance < $masterPortion) {
                throw new \RuntimeException(
                    'Insufficient master fund balance. Available: SAR '.number_format((float) $masterFund->balance, 2)
                    .', required: SAR '.number_format($masterPortion, 2).'.'
                );
            }
            // Master-funded loan disbursement mirrored on member fund account.
            $this->postEntry($masterFund, $masterPortion, 'debit', $description.' (master funded)', $loan, $member->id);
            $this->postEntry($memberFund, $masterPortion, 'debit', $description.' (member mirror)', $loan, $member->id);
            // Loan account tracks total outstanding
            $this->postEntry($loanAccount, $totalAmount, 'debit', $description, $loan, $member->id);
            // Cash payout to the borrower.
            $this->postEntry($memberCash, $totalAmount, 'credit', $description.' (cash payout)', $loan, $member->id);

            // Snapshot portions onto the loan record
            $loan->update([
                'member_portion' => $memberPortion,
                'master_portion' => $masterPortion,
                'amount_disbursed' => $totalAmount,
            ]);
        });
    }

    /**
     * Post a **partial** loan disbursement.
     *
     * The full tranche is debited from the master fund and the **same amount** is mirrored on the
     * member fund account (operational / ledger symmetry), then credited to member cash as payout.
     * Member vs. master **split** used for
     * installment count and loan `member_portion` / `master_portion` is computed separately from
     * the member’s fund balance **before** disbursement (see admin disburse flow), not from these lines.
     *
     * - Throws \RuntimeException if master fund would go negative.
     * - Increments `loans.amount_disbursed` by $amount.
     * - Snapshots portions on the disbursement record as member_portion = 0, master_portion = amount
     *   (ledger mirror totals; semantic portions live on the loan at activation).
     *
     * Must be called inside a surrounding DB::transaction if the caller needs
     * atomicity with other writes (e.g. creating installments).
     */
    public function postPartialLoanDisbursement(
        Loan $loan,
        float $amount,
        LoanDisbursement $disbursementRecord,
    ): void {
        $member = $loan->member;
        $this->ensureMemberAccounts($member);
        $loanAccount = $this->ensureLoanAccount($loan);

        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();
        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        DB::transaction(function () use ($loan, $member, $memberFund, $memberCash, $loanAccount, $amount, $disbursementRecord) {
            // Lock both accounts to prevent races
            $memberFund = Account::query()->lockForUpdate()->findOrFail($memberFund->id);
            $masterFund = Account::query()->lockForUpdate()->findOrFail(Account::masterFund()->id);

            $memberPortion = 0.0;
            $masterPortion = $amount;

            if ($masterPortion > 0 && (float) $masterFund->balance < $masterPortion) {
                throw new \RuntimeException(
                    'Insufficient master fund balance. Available: SAR '
                    .number_format((float) $masterFund->balance, 2)
                    .', required: SAR '.number_format($masterPortion, 2).'.'
                );
            }

            $seq = $loan->disbursements()->count(); // 0-based before this one
            $label = "Loan #{$loan->id} disbursement (#{$seq}) – {$member->user->name}";

            $this->postEntry($masterFund, $masterPortion, 'debit', $label.' (master funded)', $loan, $member->id);
            $this->postEntry($memberFund, $masterPortion, 'debit', $label.' (member mirror)', $loan, $member->id);
            $this->postEntry($loanAccount, $amount, 'debit', $label, $loan, $member->id);
            $this->postEntry($memberCash, $amount, 'credit', $label.' (cash payout)', $loan, $member->id);

            // Snapshot portions on the disbursement record
            $disbursementRecord->update([
                'member_portion' => $memberPortion,
                'master_portion' => $masterPortion,
            ]);

            // Accumulate running total on the loan
            $loan->increment('amount_disbursed', $amount);
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

        $memberCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} disbursement (import) – {$member->user->name}";

        DB::transaction(function () use ($loan, $member, $masterFund, $memberFund, $memberCash, $loanAccount, $description, $totalAmount, $memberPortion, $masterPortion) {
            if ($memberPortion > 0) {
                $this->postEntry($memberFund, $memberPortion, 'debit', $description.' (member portion)', $loan, $member->id);
            }
            if ($masterPortion > 0) {
                $this->postEntry($masterFund, $masterPortion, 'debit', $description.' (fund portion)', $loan, $member->id);
            }
            $this->postEntry($loanAccount, $totalAmount, 'debit', $description, $loan, $member->id);
            $this->postEntry($memberCash, $totalAmount, 'credit', $description.' (cash payout)', $loan, $member->id);

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

        if (! $loanAccount) {
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

        if (! $loanAccount) {
            $loanAccount = $this->ensureLoanAccount($loan);
        }

        $masterFund = Account::masterFund();
        $memberFund = Account::where('type', Account::TYPE_MEMBER_FUND)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = "Loan #{$loan->id} repayment (installment #{$installment->installment_number}) – {$member->user->name}";
        $amount = (float) $installment->amount;
        $lateFee = (float) ($installment->late_fee_amount ?? 0);

        DB::transaction(function () use ($installment, $loan, $member, $masterFund, $memberFund, $loanAccount, $description, $amount, $lateFee) {
            // Fund and member fund both increase on every repayment
            $this->postEntry($masterFund, $amount, 'credit', $description, $installment, $member->id);
            $this->postEntry($memberFund, $amount, 'credit', $description, $installment, $member->id);
            // Loan account: credit reduces outstanding balance
            $this->postEntry($loanAccount, $amount, 'credit', $description, $installment, $member->id);

            // Track how much has been credited back to the master fund (for guarantor release)
            $loan->increment('repaid_to_master', $amount);
            $loan->refresh();
            $loan->releaseGuarantorIfDue();

            if ($installment->is_late && $lateFee > 0.00001) {
                $lateDesc = "Loan repayment late fee – #{$loan->id} inst. {$installment->installment_number} – {$member->user->name}";
                $this->postLateFeeCreditToMasterCash($lateFee, $lateDesc, $installment, $member->id);
            }
        });
    }

    // =========================================================================
    // Cash debit for loan repayment cycle
    // =========================================================================

    /**
     * Debit a member's Cash Account for a loan repayment installment (principal) plus optional late fee.
     * Called by LoanRepaymentService before marking the installment as paid.
     */
    public function debitCashForRepayment(Member $member, LoanInstallment $installment, float $lateFee = 0.0): void
    {
        $installment->loadMissing('loan');
        $cashAccount = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $member->id)
            ->firstOrFail();

        $description = sprintf(
            'Loan #%d repayment – installment %d of %d',
            $installment->loan_id,
            $installment->installment_number,
            $installment->loan->installments_count
        );
        if ($lateFee > 0.00001) {
            $description .= ' (incl. late fee SAR '.number_format($lateFee, 2).')';
        }

        $total = (float) $installment->amount + $lateFee;
        $this->postEntry($cashAccount, $total, 'debit', $description, $installment, $member->id);
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
                "Insufficient balance in {$parent->user->name}'s Cash Account. ".
                'Available: SAR '.number_format((float) $parentCash->balance, 2)
            );
        }

        $debitDesc = trim("Transfer to {$dependent->user->name}'s cash account".($note ? " — {$note}" : ''));
        $creditDesc = trim("Transfer from {$parent->user->name}'s cash account".($note ? " — {$note}" : ''));

        DB::transaction(function () use ($parent, $dependent, $parentCash, $dependentCash, $amount, $debitDesc, $creditDesc) {
            $this->postEntry($parentCash, $amount, 'debit', $debitDesc, $parent, $parent->id);
            $this->postEntry($dependentCash, $amount, 'credit', $creditDesc, $dependent, $dependent->id);
        });
    }

    /**
     * Transfer funds from one member's cash account to another member's cash account.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function transferMemberCash(
        Member $from,
        Member $to,
        float $amount,
        string $note = '',
    ): void {
        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('You cannot transfer to your own account.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        $this->ensureMemberAccounts($from);
        $this->ensureMemberAccounts($to);

        $fromCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $from->id)
            ->firstOrFail();

        $toCash = Account::where('type', Account::TYPE_MEMBER_CASH)
            ->where('member_id', $to->id)
            ->firstOrFail();

        if ((float) $fromCash->balance + 0.00001 < $amount) {
            throw new \RuntimeException(
                "Insufficient balance in {$from->user->name}'s Cash Account. ".
                'Available: SAR '.number_format((float) $fromCash->balance, 2)
            );
        }

        $debitDesc = trim("Transfer to {$to->user->name} cash account".($note ? " — {$note}" : ''));
        $creditDesc = trim("Transfer from {$from->user->name} cash account".($note ? " — {$note}" : ''));

        DB::transaction(function () use ($from, $to, $fromCash, $toCash, $amount, $debitDesc, $creditDesc) {
            $this->postEntry($fromCash, $amount, 'debit', $debitDesc, $from, $from->id);
            $this->postEntry($toCash, $amount, 'credit', $creditDesc, $to, $to->id);
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
        if (! $tx->isPosted()) {
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
                'loan_id' => null,
                'loan_disbursement_id' => null,
            ]);
        });
    }

    /**
     * Remove cash postings for an SMS import line (master + member cash) and clear posted flags.
     */
    public function reverseSmsTransactionPosting(SmsTransaction $tx): void
    {
        if (! $tx->isPosted()) {
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
     * Update ledger row fields and reconcile the account balance when amount or entry type changes.
     * Does not alter polymorphic source (required for reversal and traceability).
     *
     * @param  array{description?: string, entry_type?: string, amount?: float|int|string, transacted_at?: mixed, member_id?: int|null}  $data
     */
    public function updateLedgerEntry(AccountTransaction $entry, array $data): AccountTransaction
    {
        $entry->loadMissing('account');
        $account = $entry->account;
        if ($account === null) {
            throw new \InvalidArgumentException('Ledger entry has no account.');
        }

        $trimmed = trim((string) ($data['description'] ?? $entry->description ?? ''));
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Description is required.');
        }

        $entryType = (string) ($data['entry_type'] ?? $entry->entry_type);
        if (! in_array($entryType, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Entry type must be credit or debit.');
        }

        $amount = round((float) ($data['amount'] ?? $entry->amount), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $transactedAt = $data['transacted_at'] ?? $entry->transacted_at;
        if (! $transactedAt instanceof CarbonInterface) {
            $transactedAt = Carbon::parse($transactedAt);
        }

        $memberId = array_key_exists('member_id', $data)
            ? $data['member_id']
            : $entry->member_id;
        if ($memberId !== null && $memberId !== '') {
            $memberId = (int) $memberId;
        } else {
            $memberId = null;
        }

        if ($account->member_id !== null) {
            $memberId = (int) $account->member_id;
        }

        $oldType = (string) $entry->entry_type;
        $oldAmount = round((float) $entry->amount, 2);
        $balanceAffectingChanged = $oldType !== $entryType || abs($oldAmount - $amount) >= 0.005;

        return DB::transaction(function () use ($entry, $account, $trimmed, $entryType, $amount, $transactedAt, $memberId, $balanceAffectingChanged, $oldType, $oldAmount) {
            $lockedAccount = Account::query()->lockForUpdate()->findOrFail($account->id);

            if ($balanceAffectingChanged) {
                if ($oldType === 'credit') {
                    $lockedAccount->decrement('balance', $oldAmount);
                } else {
                    $lockedAccount->increment('balance', $oldAmount);
                }

                if ($entryType === 'credit') {
                    $lockedAccount->increment('balance', $amount);
                } else {
                    $lockedAccount->decrement('balance', $amount);
                }
            }

            $entry->update([
                'description' => $trimmed,
                'entry_type' => $entryType,
                'amount' => $amount,
                'transacted_at' => $transactedAt,
                'member_id' => $memberId,
            ]);

            return $entry->fresh();
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
        if (! in_array($entryType, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Entry type must be credit or debit.');
        }

        $trimmed = trim($description);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Description is required.');
        }

        $memberId = $memberId ?? $account->member_id;

        if ($transactedAt !== null && ! $transactedAt instanceof CarbonInterface) {
            $transactedAt = Carbon::parse($transactedAt);
        }

        return DB::transaction(function () use ($account, $entryType, $amount, $trimmed, $memberId, $transactedAt) {
            $account = Account::query()->lockForUpdate()->findOrFail($account->id);

            $postedById = auth()->id() ?? 1;
            $source = User::query()->findOrFail($postedById);

            return $this->postEntry($account, $amount, $entryType, $trimmed, $source, $memberId, $transactedAt);
        });
    }

    // =========================================================================
    // Accounting reversal (non-destructive counter-entry)
    // =========================================================================

    /**
     * Post an equal-and-opposite counter-entry on the SAME account, leaving the original
     * entry intact.  This is the audit-safe way to correct a posted ledger line — both the
     * original and the reversal remain visible in the ledger history.
     *
     * The counter-entry's source is set to the original AccountTransaction so reversals
     * are fully queryable via source_type / source_id.
     */
    public function createReversalEntry(
        AccountTransaction $original,
        string $reason,
        ?\Illuminate\Support\Carbon $at = null,
    ): AccountTransaction {
        if ($original->trashed()) {
            throw new \InvalidArgumentException('Cannot reverse a deleted (soft-deleted) entry. Restore it first.');
        }

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('A reason is required for a reversal entry.');
        }

        return DB::transaction(function () use ($original, $trimmed, $at) {
            $account     = Account::query()->lockForUpdate()->findOrFail($original->account_id);
            $counterType = $original->entry_type === 'credit' ? 'debit' : 'credit';
            $description = 'Reversal of #' . $original->id . ': '
                . ($original->description ?? '—')
                . ' — ' . $trimmed;

            // Use the authenticated user as the source model (same pattern as manual entries)
            // but override the source columns after postEntry so they point at the original row.
            $authUser = User::query()->findOrFail(auth()->id() ?? 1);
            $entry = $this->postEntry(
                $account,
                (float) $original->amount,
                $counterType,
                $description,
                $authUser,
                $original->member_id,
                $at ?? now(),
            );

            // Overwrite source to point at the original AccountTransaction so it is queryable.
            $entry->update([
                'source_type' => (new AccountTransaction)->getMorphClass(),
                'source_id'   => $original->id,
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Reverse ALL non-deleted ledger entries that share the same source record
     * (source_type + source_id) as the given entry.
     *
     * Useful when a single business event (e.g. a Contribution posting) created
     * multiple ledger lines across accounts and all legs need to be unwound.
     *
     * @return int  Number of counter-entries created.
     */
    public function createFullSourceReversal(
        AccountTransaction $anyEntry,
        string $reason,
        ?\Illuminate\Support\Carbon $at = null,
    ): int {
        if (blank($anyEntry->source_type) || blank($anyEntry->source_id)) {
            throw new \InvalidArgumentException('This entry has no source reference — use single-entry reversal instead.');
        }

        $siblings = AccountTransaction::query()
            ->where('source_type', $anyEntry->source_type)
            ->where('source_id', $anyEntry->source_id)
            ->get();

        if ($siblings->isEmpty()) {
            throw new \InvalidArgumentException('No ledger entries found for this source.');
        }

        $count = 0;
        DB::transaction(function () use ($siblings, $reason, $at, &$count) {
            foreach ($siblings as $entry) {
                if ($entry->trashed()) {
                    continue;
                }
                $this->createReversalEntry($entry, $reason, $at);
                $count++;
            }
        });

        return $count;
    }

    /**
     * True when at least one non-deleted reversal counter-entry already exists for this row.
     */
    public function hasExistingReversal(AccountTransaction $entry): bool
    {
        return AccountTransaction::query()
            ->where('source_type', (new AccountTransaction)->getMorphClass())
            ->where('source_id', $entry->id)
            ->exists();
    }

    /**
     * True when this entry itself is a reversal counter-entry (its source points to another AccountTransaction).
     */
    public function isReversalEntry(AccountTransaction $entry): bool
    {
        return $entry->source_type === (new AccountTransaction)->getMorphClass();
    }

    // =========================================================================
    // Split transaction (master cash only)
    // =========================================================================

    /**
     * Replace one master-cash ledger entry with N labelled parts summing to the same total.
     * Net balance effect is zero (old entry reversed then the parts re-posted).
     *
     * @param  array<int, array{amount: float, description: string}>  $parts
     */
    public function splitTransaction(AccountTransaction $original, array $parts): void
    {
        $partTotal = array_sum(array_column($parts, 'amount'));

        if (abs($partTotal - (float) $original->amount) > 0.01) {
            throw new \InvalidArgumentException(
                'Parts must sum to the original amount (SAR ' . number_format((float) $original->amount, 2) . ').'
            );
        }

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('At least two parts are required for a split.');
        }

        foreach ($parts as $i => $part) {
            if (($part['amount'] ?? 0) <= 0) {
                throw new \InvalidArgumentException("Part #" . ($i + 1) . " must have a positive amount.");
            }
            if (empty(trim($part['description'] ?? ''))) {
                throw new \InvalidArgumentException("Part #" . ($i + 1) . " requires a description.");
            }
        }

        DB::transaction(function () use ($original, $parts) {
            $account        = Account::query()->lockForUpdate()->findOrFail($original->account_id);
            $originalAt     = $original->transacted_at ?? now();
            $originalMember = $original->member_id;

            // Reverse the original entry.
            $this->reverseSingleLedgerEntry($original);

            // Re-post each part on the same account with the same timestamp.
            foreach ($parts as $part) {
                $source = User::query()->findOrFail(auth()->id() ?? 1);
                $this->postEntry(
                    $account,
                    (float) $part['amount'],
                    'credit',
                    trim($part['description']),
                    $source,
                    $originalMember,
                    $originalAt,
                );
            }
        });
    }

    // =========================================================================
    // Refund (member cash → also debits master cash)
    // =========================================================================

    /**
     * Debit a member's cash account AND the master cash account to record a refund payment.
     * The refund description is applied to both ledger lines for easy reconciliation.
     */
    public function refundMemberCash(
        Account $memberCash,
        float $amount,
        string $description,
        ?Member $member = null,
        ?\Illuminate\Support\Carbon $at = null,
    ): void {
        if ($memberCash->type !== Account::TYPE_MEMBER_CASH) {
            throw new \InvalidArgumentException('Refund can only be posted to a member cash account.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }
        if ($amount > (float) $memberCash->balance) {
            throw new \InvalidArgumentException(
                'Refund amount (SAR ' . number_format($amount, 2) . ') exceeds the available cash balance (SAR ' . number_format((float) $memberCash->balance, 2) . ').'
            );
        }

        $trimmed = trim($description);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Refund description is required.');
        }

        DB::transaction(function () use ($memberCash, $amount, $trimmed, $member, $at) {
            $member     = $member ?? $memberCash->member;
            $memberId   = $member?->id ?? $memberCash->member_id;
            $masterCash = Account::masterCash();
            $source     = User::query()->findOrFail(auth()->id() ?? 1);
            $refundDesc = 'Refund — ' . ($member?->user?->name ?? 'Member') . ' — ' . $trimmed;
            $transactedAt = $at ?? now();

            // Debit member cash (balance decreases).
            $memberCashLocked = Account::query()->lockForUpdate()->findOrFail($memberCash->id);
            $this->postEntry($memberCashLocked, $amount, 'debit', $refundDesc, $source, $memberId, $transactedAt);

            // Debit master cash (cash leaves the fund).
            $masterCashLocked = Account::query()->lockForUpdate()->findOrFail($masterCash->id);
            $this->postEntry($masterCashLocked, $amount, 'debit', $refundDesc, $source, $memberId, $transactedAt);
        });
    }

    // =========================================================================
    // Annual subscription fee (master cash only, like membership application fees)
    // =========================================================================

    /**
     * Credit master cash for an annual subscription fee and link the ledger entry
     * back to the MemberSubscriptionFee record.
     */
    public function postSubscriptionFeeToMasterCash(\App\Models\MemberSubscriptionFee $fee): void
    {
        $fee->loadMissing('member.user');
        $member = $fee->member;

        $masterCash = Account::masterCash();
        $label      = $member->user?->name ?? 'Member';
        $description = "Annual Subscription Fee {$fee->year} — {$label}";

        DB::transaction(function () use ($masterCash, $fee, $description, $member) {
            $source = User::query()->findOrFail(auth()->id() ?? 1);
            $entry = $this->postEntry(
                $masterCash,
                (float) $fee->amount,
                'credit',
                $description,
                $source,
                $member->id,
                $fee->paid_at,
            );

            $fee->update(['account_transaction_id' => $entry->id]);
        });
    }

    /**
     * Late fees increase master cash only (not master fund), same idea as membership application fees.
     */
    private function postLateFeeCreditToMasterCash(
        float $amount,
        string $description,
        Model $source,
        ?int $memberId,
    ): void {
        if ($amount <= 0.00001) {
            return;
        }

        $masterCash = Account::masterCash();
        $this->postEntry($masterCash, $amount, 'credit', $description, $source, $memberId);
    }

    // =========================================================================
    // Internal: create one ledger entry and update the account balance atomically
    // =========================================================================

    private function postEntry(
        Account $account,
        float $amount,
        string $entryType,
        string $description,
        Model $source,
        ?int $memberId = null,
        ?CarbonInterface $transactedAt = null,
    ): AccountTransaction {
        $entry = AccountTransaction::create([
            'account_id' => $account->id,
            'amount' => $amount,
            'entry_type' => $entryType,
            'description' => $description,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
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
