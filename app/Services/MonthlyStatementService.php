<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MonthlyStatement;
use App\Models\Setting;
use App\Notifications\MonthlyStatementNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthlyStatementService
{
    /**
     * Generate (or regenerate) statements for all active members for a given period.
     *
     * @param  string  $period   YYYY-MM
     * @param  bool    $notify   Send email+DB notification after generation
     * @return int  Number of statements generated
     */
    public function generateForAllMembers(string $period, bool $notify = false): int
    {
        $generated = 0;

        Member::active()
            ->with(['user', 'accounts'])
            ->each(function (Member $member) use ($period, $notify, &$generated): void {
                try {
                    $this->generateForMember($member, $period, $notify);
                    $generated++;
                } catch (\Throwable $e) {
                    Log::error("MonthlyStatementService: failed for member {$member->id} period {$period}: " . $e->getMessage());
                }
            });

        return $generated;
    }

    /**
     * Generate (or regenerate) a statement for a single member and period.
     *
     * @param  string  $period   YYYY-MM
     */
    public function generateForMember(Member $member, string $period, bool $notify = false): MonthlyStatement
    {
        [$year, $month] = array_map('intval', explode('-', $period));

        $details = $this->buildDetails($member, $period, $month, $year);

        $statement = MonthlyStatement::upsertForMember($member->id, $period, [
            'opening_balance'     => $details['opening_balance'],
            'total_contributions' => $details['total_contributions'],
            'total_repayments'    => $details['total_repayments'],
            'closing_balance'     => $details['closing_balance'],
            'generated_at'        => now(),
            'details'             => $details,
            'notified_at'         => null,
        ]);

        if ($notify) {
            $this->sendNotification($statement);
        }

        return $statement;
    }

    /**
     * Send statement notification to a member (email + DB).
     */
    public function sendNotification(MonthlyStatement $statement): void
    {
        $statement->load('member.user');
        $user = $statement->member?->user;

        if (!$user) {
            return;
        }

        try {
            $user->notify(new MonthlyStatementNotification($statement));
            $statement->update(['notified_at' => now()]);
        } catch (\Throwable $e) {
            Log::error("MonthlyStatementService: notification failed for statement {$statement->id}: " . $e->getMessage());
        }
    }

    // =========================================================================
    // Private: build rich details payload
    // =========================================================================

    private function buildDetails(Member $member, string $period, int $month, int $year): array
    {
        // ── Opening balance: closing of last statement ────────────────────────
        $lastStatement = MonthlyStatement::where('member_id', $member->id)
            ->where('period', '<', $period)
            ->orderByDesc('period')
            ->first();

        $openingCash = (float) ($lastStatement?->details['cash_closing'] ?? $this->liveBalance($member, Account::TYPE_MEMBER_CASH));
        $openingFund = (float) ($lastStatement?->details['fund_closing'] ?? $this->liveBalance($member, Account::TYPE_MEMBER_FUND));
        // Legacy: if no previous detail, use the stored closing_balance field
        $opening = (float) ($lastStatement?->closing_balance ?? 0);

        // ── Period contributions ──────────────────────────────────────────────
        $periodContribs = Contribution::where('member_id', $member->id)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $totalContributions = (float) $periodContribs->sum('amount');

        // ── Period loan installments (paid this period) ───────────────────────
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd   = (clone $periodStart)->endOfMonth();

        $paidInstallments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->where('status', 'paid')
            ->get();

        $totalRepayments = (float) $paidInstallments->sum('amount');

        // ── Account transactions for the period ───────────────────────────────
        $memberAccountIds = Account::where('member_id', $member->id)
            ->whereIn('type', [Account::TYPE_MEMBER_CASH, Account::TYPE_MEMBER_FUND])
            ->pluck('id');

        $periodTransactions = AccountTransaction::whereIn('account_id', $memberAccountIds)
            ->whereBetween('transacted_at', [$periodStart, $periodEnd])
            ->with('account')
            ->orderBy('transacted_at')
            ->get()
            ->map(fn(AccountTransaction $tx) => [
                'date'         => $tx->transacted_at->toDateTimeString(),
                'description'  => $tx->description,
                'type'         => $tx->entry_type,
                'amount'       => (float) $tx->amount,
                'account_type' => $tx->account?->type ?? 'unknown',
            ])
            ->toArray();

        // ── Live balances at period end ────────────────────────────────────────
        // We reconstruct "balance at end of period" by summing all transactions up to period end
        $cashAtEnd = $this->balanceAtDate($member, Account::TYPE_MEMBER_CASH, $periodEnd);
        $fundAtEnd = $this->balanceAtDate($member, Account::TYPE_MEMBER_FUND, $periodEnd);

        // ── Active loan details ───────────────────────────────────────────────
        $activeLoan = Loan::where('member_id', $member->id)
            ->whereIn('status', ['active', 'settled'])
            ->orderByDesc('disbursed_at')
            ->with(['loanTier', 'installments'])
            ->first();

        $loanDetails = null;
        if ($activeLoan) {
            $allInstallments = $activeLoan->installments;
            $paidCount       = $allInstallments->where('status', 'paid')->count();
            $pendingCount    = $allInstallments->whereIn('status', ['pending', 'overdue'])->count();
            $loanDetails = [
                'id'                  => $activeLoan->id,
                'status'              => $activeLoan->status,
                'amount_approved'     => (float) $activeLoan->amount_approved,
                'remaining_amount'    => (float) $activeLoan->remaining_amount,
                'tier'                => $activeLoan->loanTier?->label,
                'disbursed_at'        => $activeLoan->disbursed_at?->toDateString(),
                'installments_total'  => $allInstallments->count(),
                'installments_paid'   => $paidCount,
                'installments_pending'=> $pendingCount,
                'next_due'            => $allInstallments->where('status', 'pending')
                                            ->sortBy('due_date')->first()?->due_date?->toDateString(),
            ];
        }

        // ── Late fee summary ──────────────────────────────────────────────────
        $periodLateFees = (float) LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->whereBetween('paid_at', [$periodStart, $periodEnd])
            ->where('status', 'paid')
            ->sum('late_fee_amount');

        // ── Contribution standing ─────────────────────────────────────────────
        $overdueInstallments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->get()
            ->map(fn(LoanInstallment $i) => [
                'installment_number' => $i->installment_number,
                'due_date'           => $i->due_date?->toDateString(),
                'amount'             => (float) $i->amount,
                'late_fee'           => (float) $i->late_fee_amount,
            ])
            ->toArray();

        // ── Closing balance (legacy single figure for backward compat) ─────────
        $closing = $opening + $totalContributions - $totalRepayments;

        return [
            // Summary (used by existing table columns)
            'opening_balance'     => $opening,
            'total_contributions' => $totalContributions,
            'total_repayments'    => $totalRepayments,
            'closing_balance'     => $closing,

            // Rich details
            'period'         => $period,
            'period_label'   => Carbon::create($year, $month, 1)->format('F Y'),
            'generated_at'   => now()->toDateTimeString(),

            'cash_opening'   => $openingCash,
            'fund_opening'   => $openingFund,
            'cash_closing'   => $cashAtEnd,
            'fund_closing'   => $fundAtEnd,

            'contributions'  => $periodContribs->map(fn(Contribution $c) => [
                'amount'     => (float) $c->amount,
                'paid_at'    => $c->paid_at?->toDateString(),
                'method'     => $c->payment_method,
                'status'     => $c->status,
                'is_late'    => (bool) $c->is_late,
            ])->toArray(),

            'period_installments' => $paidInstallments->map(fn(LoanInstallment $i) => [
                'installment_number' => $i->installment_number,
                'due_date'           => $i->due_date?->toDateString(),
                'paid_at'            => $i->paid_at?->toDateString(),
                'amount'             => (float) $i->amount,
                'late_fee'           => (float) $i->late_fee_amount,
            ])->toArray(),

            'period_transactions'  => $periodTransactions,
            'period_late_fees'     => $periodLateFees,
            'overdue_installments' => $overdueInstallments,
            'active_loan'          => $loanDetails,

            'member_snapshot' => [
                'name'              => $member->user?->name,
                'member_number'     => $member->member_number,
                'email'             => $member->user?->email,
                'phone'             => $member->user?->phone,
                'status'            => $member->status,
                'joined_at'         => $member->joined_at?->toDateString(),
                'monthly_contrib'   => (float) $member->monthly_contribution_amount,
                'late_contrib_count'=> (int) $member->late_contributions_count,
                'late_repay_count'  => (int) $member->late_repayment_count,
            ],
        ];
    }

    // =========================================================================
    // Private: helpers
    // =========================================================================

    private function liveBalance(Member $member, string $accountType): float
    {
        return (float) Account::where('member_id', $member->id)
            ->where('type', $accountType)
            ->value('balance') ?? 0.0;
    }

    /**
     * Compute account balance at the END of a given date by summing all
     * transactions up to and including that date.
     */
    private function balanceAtDate(Member $member, string $accountType, Carbon $date): float
    {
        $accountId = Account::where('member_id', $member->id)
            ->where('type', $accountType)
            ->value('id');

        if (!$accountId) {
            return 0.0;
        }

        $credits = (float) AccountTransaction::where('account_id', $accountId)
            ->where('entry_type', 'credit')
            ->where('transacted_at', '<=', $date->endOfDay())
            ->sum('amount');

        $debits = (float) AccountTransaction::where('account_id', $accountId)
            ->where('entry_type', 'debit')
            ->where('transacted_at', '<=', $date->endOfDay())
            ->sum('amount');

        return round($credits - $debits, 2);
    }
}
