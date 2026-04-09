<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\Setting;
use Filament\Widgets\Widget;

class MemberAccountStatsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.member-account-stats';

    public ?Member $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        if (!$this->record) {
            return ['hasRecord' => false];
        }

        $member = $this->record->load(['accounts', 'loans']);

        $cashBalance = (float) ($member->cashAccount()?->balance ?? 0);
        $fundBalance = (float) ($member->fundAccount()?->balance ?? 0);
        $minFund = Setting::loanMinFundBalance();
        $fundPct = $minFund > 0 ? min(100, round($fundBalance / $minFund * 100)) : 100;

        $lateCount = $member->contributionsMarkedLateCount();
        $lateAmount = $member->contributionsMarkedLateAmount();

        $activeLoans = $member->loans()->whereIn('status', ['active', 'approved', 'disbursed'])->get();
        $activeLoansCount = $activeLoans->count();
        $outstandingAmt = 0.0;
        foreach ($activeLoans as $loan) {
            $outstandingAmt += (float) LoanInstallment::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->sum('amount');
        }

        $overdueInstallments = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        $lateRepayCount = (int) ($member->late_repayment_count ?? 0);
        $lateRepayAmount = (float) ($member->late_repayment_amount ?? 0);

        $totalContributions = (float) Contribution::where('member_id', $member->id)->sum('amount');
        $contribCount = Contribution::where('member_id', $member->id)->count();

        $eligibilityMonths = Setting::loanEligibilityMonths();
        $eligible = $member->joined_at
            && $member->joined_at->addMonths($eligibilityMonths)->isPast()
            && $fundBalance >= $minFund;

        $maxBorrow = $fundBalance * Setting::loanMaxBorrowMultiplier();

        $nextInstallment = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        $now = now();
        $paidThisMonth = Contribution::where('member_id', $member->id)
            ->where('month', $now->month)->where('year', $now->year)->exists();

        return [
            'hasRecord' => true,
            'cash_balance' => $cashBalance,
            'fund_balance' => $fundBalance,
            'fund_pct' => $fundPct,
            'min_fund' => $minFund,
            'net_worth' => $cashBalance + $fundBalance,
            'total_contributions' => $totalContributions,
            'contrib_count' => $contribCount,
            'late_count' => $lateCount,
            'late_amount' => $lateAmount,
            'active_loans_count' => $activeLoansCount,
            'outstanding_amt' => $outstandingAmt,
            'overdue_installments' => $overdueInstallments,
            'late_repay_count' => $lateRepayCount,
            'late_repay_amount' => $lateRepayAmount,
            'eligible' => $eligible,
            'max_borrow' => $maxBorrow,
            'next_installment' => $nextInstallment,
            'paid_this_month' => $paidThisMonth,
        ];
    }
}
