<?php

namespace App\Filament\Member\Widgets;

use App\Models\LoanInstallment;
use App\Models\Setting;
use Filament\Widgets\Widget;

class AccountBalancesWidget extends Widget
{
    protected string $view = 'filament.member.widgets.account-balances';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $member = auth()->user()?->member;
        if (!$member) {
            return ['hasMember' => false];
        }

        $cash = (float) ($member->cashAccount()?->balance ?? 0);
        $fund = (float) ($member->fundAccount()?->balance ?? 0);
        $minFund = Setting::loanMinFundBalance();
        $fundToGo = max(0, $minFund - $fund);
        $fundPct = $minFund > 0 ? min(100, round($fund / $minFund * 100)) : 100;

        $nextInstallment = LoanInstallment::whereHas('loan', fn($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')->orderBy('due_date')->first();

        $nextContribAmount = (float) ($member->monthly_contribution_amount ?? 500);
        $nextDue = $nextInstallment ? (float) $nextInstallment->amount : $nextContribAmount;
        $nextDueLabel = $nextInstallment
            ? 'installment due ' . $nextInstallment->due_date->format('d M')
            : 'monthly contribution';
        $cashCovers = $cash >= $nextDue;
        $cashPct = $nextDue > 0 ? min(100, round($cash / $nextDue * 100)) : 100;

        $maxBorrow = $fund * Setting::loanMaxBorrowMultiplier();
        $loanMonths = Setting::loanEligibilityMonths();
        $eligible = $member->joined_at->addMonths($loanMonths)->isPast() && $fund >= $minFund;
        $eligibleDate = $member->joined_at->addMonths($loanMonths)->format('d M Y');

        $monthlyAlloc = (float) ($member->monthly_contribution_amount ?? 500);

        return [
            'hasMember' => true,
            'cash' => $cash,
            'cash_pct' => $cashPct,
            'cash_covers' => $cashCovers,
            'next_due' => $nextDue,
            'next_due_label' => $nextDueLabel,
            'fund' => $fund,
            'fund_pct' => $fundPct,
            'fund_to_go' => $fundToGo,
            'min_fund' => $minFund,
            'max_borrow' => $maxBorrow,
            'eligible' => $eligible,
            'eligible_date' => $eligibleDate,
            'monthly_alloc' => $monthlyAlloc,
        ];
    }
}
