<?php

namespace App\Filament\Member\Widgets;

use App\Models\LoanInstallment;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountBalancesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $member = auth()->user()?->member;
        if (! $member) {
            return [Stat::make('Error', 'No member record found')->color('danger')];
        }

        $cash = (float) ($member->cashAccount()?->balance ?? 0);
        $fund = (float) ($member->fundAccount()?->balance ?? 0);

        // Loan eligibility context
        $minFund = Setting::loanMinFundBalance();
        $fundToGo = max(0, $minFund - $fund);
        $fundColor = match (true) {
            $fund >= $minFund => 'success',
            $fund >= $minFund * 0.75 => 'warning',
            default => 'danger',
        };

        // Cash sufficiency for next due amount
        $nextInstallment = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        $nextContribAmount = $member->monthly_contribution_amount ?? 500;
        $nextDue = $nextInstallment ? (float) $nextInstallment->amount : $nextContribAmount;
        $nextLabel = $nextInstallment
            ? 'next installment due '.$nextInstallment->due_date->format('d M Y')
            : 'monthly contribution';
        $cashColor = $cash >= $nextDue ? 'success' : 'danger';
        $cashDesc = $cash >= $nextDue
            ? "Covers {$nextLabel}"
            : "⚠ Insufficient for {$nextLabel} (SAR ".number_format($nextDue).')';

        // Max borrowable
        $maxBorrow = $fund * Setting::loanMaxBorrowMultiplier();
        $loanMonths = Setting::loanEligibilityMonths();
        $eligible = $member->joined_at->addMonths($loanMonths)->isPast() && $fund >= $minFund;

        return [
            Stat::make('Cash Balance', '﷼ '.number_format($cash, 2))
                ->description($cashDesc)
                ->descriptionIcon($cash >= $nextDue ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->icon('heroicon-o-banknotes')
                ->color($cashColor),

            Stat::make('Fund Balance', '﷼ '.number_format($fund, 2))
                ->description(
                    $fundToGo > 0
                        ? 'SAR '.number_format($fundToGo).' more needed for loan eligibility'
                        : 'Above loan eligibility threshold'
                )
                ->descriptionIcon($fundToGo > 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-shield-check')
                ->icon('heroicon-o-building-library')
                ->color($fundColor),

            Stat::make('Max Borrowable', $eligible ? '﷼ '.number_format($maxBorrow, 2) : 'Not yet eligible')
                ->description(
                    $eligible
                        ? 'Based on 2× your fund balance'
                        : ($fund < $minFund
                            ? 'Fund balance below SAR '.number_format($minFund).' minimum'
                            : 'Eligibility: '.$member->joined_at->addMonths($loanMonths)->format('d M Y'))
                )
                ->descriptionIcon($eligible ? 'heroicon-o-credit-card' : 'heroicon-o-lock-closed')
                ->icon('heroicon-o-credit-card')
                ->color($eligible ? 'info' : 'gray'),

            Stat::make('Monthly Allocation', '﷼ '.number_format($member->monthly_contribution_amount ?? 500))
                ->description('Your configured monthly contribution')
                ->descriptionIcon('heroicon-o-calendar')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary'),
        ];
    }
}
