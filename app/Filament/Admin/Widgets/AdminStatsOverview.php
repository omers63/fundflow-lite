<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use App\Models\MembershipApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalFund = Contribution::sum('amount');
        $activeLoansTotal = Loan::where('loans.status', 'active')
            ->join('loan_installments', 'loans.id', '=', 'loan_installments.loan_id')
            ->whereIn('loan_installments.status', ['pending', 'overdue'])
            ->sum('loan_installments.amount');

        return [
            Stat::make('Active Members', Member::active()->count())
                ->description('Total approved members')
                ->icon('heroicon-o-users')
                ->color('success'),

            Stat::make('Pending Applications', MembershipApplication::where('status', 'pending')->count())
                ->description('Awaiting review')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning'),

            Stat::make('Total Fund (SAR)', '﷼'.number_format($totalFund, 2))
                ->description('Cumulative contributions')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Active Loans', Loan::where('status', 'active')->count())
                ->description('Outstanding loans')
                ->icon('heroicon-o-credit-card')
                ->color('info'),

            Stat::make('Overdue Installments', LoanInstallment::where('status', 'overdue')->count())
                ->description('Requires attention')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger'),

            Stat::make('Delinquent Members', Member::delinquent()->count())
                ->description('3+ overdue installments')
                ->icon('heroicon-o-user-minus')
                ->color('danger'),
        ];
    }
}
