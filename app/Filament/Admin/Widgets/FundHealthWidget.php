<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FundHealthWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $masterFund = (float) (Account::masterFund()?->balance ?? 0);
        $masterCash = (float) (Account::masterCash()?->balance ?? 0);

        // Total capital committed to active/approved loans
        $loanExposure = (float) Loan::whereIn('status', ['approved', 'active'])->sum('amount_approved');

        // Coverage ratio: how many times the fund covers its loan exposure
        $coverage = $loanExposure > 0 ? round($masterFund / $loanExposure, 2) : null;
        $coverageStat = $coverage !== null
            ? number_format($coverage, 2).'×'
            : 'N/A';
        $coverageColor = match (true) {
            $coverage === null => 'gray',
            $coverage >= 1.5 => 'success',
            $coverage >= 1.0 => 'warning',
            default => 'danger',
        };

        // Contributions this calendar month
        $now = now();
        $contribThisMonth = Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        // Active members not yet contributed this month
        $activeCount = Member::active()->count();
        $paidThisMonth = Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->distinct('member_id')
            ->count('member_id');
        $complianceRate = $activeCount > 0
            ? round($paidThisMonth / $activeCount * 100)
            : 0;
        $complianceColor = match (true) {
            $complianceRate >= 90 => 'success',
            $complianceRate >= 70 => 'warning',
            default => 'danger',
        };

        // Late installments outstanding
        $overdueAmount = (float) LoanInstallment::where('status', 'overdue')->sum('amount');

        return [
            Stat::make('Master Fund Balance', '﷼ '.number_format($masterFund, 2))
                ->description('Investable fund capital')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->icon('heroicon-o-building-library')
                ->color('success'),

            Stat::make('Cash on Hand', '﷼ '.number_format($masterCash, 2))
                ->description('Total member cash deposits')
                ->descriptionIcon('heroicon-o-banknotes')
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Loan Exposure', '﷼ '.number_format($loanExposure, 2))
                ->description(Loan::whereIn('status', ['approved', 'active'])->count().' active / approved loans')
                ->descriptionIcon('heroicon-o-credit-card')
                ->icon('heroicon-o-credit-card')
                ->color('warning'),

            Stat::make('Fund Coverage Ratio', $coverageStat)
                ->description('Master fund ÷ total loan exposure')
                ->descriptionIcon('heroicon-o-scale')
                ->icon('heroicon-o-scale')
                ->color($coverageColor),

            Stat::make('Contribution Compliance', $complianceRate.'%')
                ->description("{$paidThisMonth} of {$activeCount} members paid this month")
                ->descriptionIcon('heroicon-o-check-circle')
                ->icon('heroicon-o-chart-pie')
                ->color($complianceColor),

            Stat::make('Overdue Loan Amount', '﷼ '.number_format($overdueAmount, 2))
                ->description(LoanInstallment::where('status', 'overdue')->count().' installment(s) overdue')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($overdueAmount > 0 ? 'danger' : 'success'),
        ];
    }
}
