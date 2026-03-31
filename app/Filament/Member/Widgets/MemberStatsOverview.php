<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemberStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $member = auth()->user()?->member;

        if (! $member) {
            return [
                Stat::make('Status', 'No member record')->color('warning'),
            ];
        }

        $totalContributions = Contribution::where('member_id', $member->id)->sum('amount');
        $activeLoan = Loan::where('member_id', $member->id)->where('status', 'active')->first();
        $overdueCount = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'overdue')
            ->count();

        $nextInstallment = LoanInstallment::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();

        return [
            Stat::make('Member Number', $member->member_number)
                ->icon('heroicon-o-identification')
                ->color('primary'),

            Stat::make('Total Contributions', '﷼' . number_format($totalContributions, 2))
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Active Loan', $activeLoan
                ? '﷼' . number_format($activeLoan->amount_approved, 2)
                : 'None')
                ->icon('heroicon-o-credit-card')
                ->color($activeLoan ? 'info' : 'gray'),

            Stat::make('Next Installment', $nextInstallment
                ? '﷼' . number_format($nextInstallment->amount, 2) . ' due ' . $nextInstallment->due_date->format('d M Y')
                : 'No pending installments')
                ->icon('heroicon-o-calendar-days')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }
}



