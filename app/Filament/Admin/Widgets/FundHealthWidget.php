<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Account;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Member;
use Filament\Widgets\Widget;

class FundHealthWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.fund-health';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $masterFund = (float) (Account::masterFund()?->balance ?? 0);
        $masterCash = (float) (Account::masterCash()?->balance ?? 0);
        $loanExposure = (float) Loan::whereIn('status', ['approved', 'active'])->sum('amount_approved');
        $loanCount = Loan::whereIn('status', ['approved', 'active'])->count();

        $coverage = $loanExposure > 0 ? round($masterFund / $loanExposure, 2) : null;
        $coveragePct = $loanExposure > 0 ? min(100, round(($masterFund / $loanExposure) * 66.67)) : 100;
        $coverageLabel = $coverage !== null ? number_format($coverage, 2) . '×' : 'N/A';
        $coverageStatus = match (true) {
            $coverage === null => 'gray',
            $coverage >= 1.5 => 'success',
            $coverage >= 1.0 => 'warning',
            default => 'danger',
        };

        $now = now();
        $activeCount = max(1, Member::active()->count());
        $paidThisMonth = Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->distinct('member_id')->count('member_id');
        $complianceRate = round($paidThisMonth / $activeCount * 100);
        $complianceStatus = match (true) {
            $complianceRate >= 90 => 'success',
            $complianceRate >= 70 => 'warning',
            default => 'danger',
        };

        $overdueAmount = (float) LoanInstallment::where('status', 'overdue')->sum('amount');
        $overdueCount = LoanInstallment::where('status', 'overdue')->count();

        $totalAssets = $masterFund + $masterCash;
        $fundPct = $totalAssets > 0 ? min(100, round($masterFund / $totalAssets * 100)) : 50;
        $cashPct = $totalAssets > 0 ? min(100, round($masterCash / $totalAssets * 100)) : 50;
        $exposurePct = $masterFund > 0 ? min(100, round($loanExposure / $masterFund * 100)) : 0;

        return [
            'master_fund' => $masterFund,
            'master_cash' => $masterCash,
            'loan_exposure' => $loanExposure,
            'loan_count' => $loanCount,
            'coverage' => $coverage,
            'coverage_pct' => $coveragePct,
            'coverage_label' => $coverageLabel,
            'coverage_status' => $coverageStatus,
            'paid_this_month' => $paidThisMonth,
            'active_count' => $activeCount,
            'compliance_rate' => $complianceRate,
            'compliance_status' => $complianceStatus,
            'overdue_amount' => $overdueAmount,
            'overdue_count' => $overdueCount,
            'total_assets' => $totalAssets,
            'fund_pct' => $fundPct,
            'cash_pct' => $cashPct,
            'exposure_pct' => $exposurePct,
        ];
    }
}
