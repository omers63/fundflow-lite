<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\LoanInstallment;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ContributionStatsWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.contribution-stats';

    protected int|string|array $columnSpan = 'full';

    public function getData(): array
    {
        $now = Carbon::now();
        $prev = Carbon::now()->subMonthNoOverflow();

        $activeCount = max(1, Member::active()->count());

        // This month
        $thisMonth = Contribution::whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount),0) as total, COALESCE(SUM(is_late),0) as late')
            ->first();

        // Last month
        $lastMonth = Contribution::whereMonth('paid_at', $prev->month)
            ->whereYear('paid_at', $prev->year)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount),0) as total, COALESCE(SUM(is_late),0) as late')
            ->first();

        // All time
        $allTime = Contribution::selectRaw('COALESCE(SUM(amount),0) as total, COUNT(*) as cnt, COALESCE(SUM(is_late),0) as late_total')
            ->first();

        // Loan repayments (recorded on installments; mixed import / repayments do not always create Contribution rows)
        $repaymentExpr = 'COALESCE(SUM(amount + COALESCE(late_fee_amount, 0)), 0)';
        $repaymentsAllTime = LoanInstallment::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->selectRaw("{$repaymentExpr} as total, COUNT(*) as cnt")
            ->first();

        $repaymentsThisMonth = LoanInstallment::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->selectRaw("{$repaymentExpr} as total")
            ->first();

        $repaymentsLastMonth = LoanInstallment::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', $prev->month)
            ->whereYear('paid_at', $prev->year)
            ->selectRaw("{$repaymentExpr} as total")
            ->first();

        $thisCnt = (int) ($thisMonth->cnt ?? 0);
        $lastCnt = (int) ($lastMonth->cnt ?? 0);
        $lateTotal = (int) ($allTime->late_total ?? 0);

        $complianceThis = $activeCount > 0 ? round($thisCnt / $activeCount * 100) : 0;
        $complianceLast = $activeCount > 0 ? round($lastCnt / $activeCount * 100) : 0;

        // 6-month trend
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = Carbon::now()->subMonths($i)->startOfMonth();
            $row = Contribution::whereYear('paid_at', $d->year)
                ->whereMonth('paid_at', $d->month)
                ->selectRaw('COALESCE(SUM(amount),0) as total, COUNT(*) as cnt')
                ->first();
            $repMonth = LoanInstallment::query()
                ->where('status', 'paid')
                ->whereNotNull('paid_at')
                ->whereYear('paid_at', $d->year)
                ->whereMonth('paid_at', $d->month)
                ->selectRaw("{$repaymentExpr} as total")
                ->first();
            $trend[] = [
                'label' => $d->format('M'),
                'total' => (float) ($row->total ?? 0) + (float) ($repMonth->total ?? 0),
                'cnt' => (int) ($row->cnt ?? 0),
                'pct' => $activeCount > 0 ? round(($row->cnt ?? 0) / $activeCount * 100) : 0,
            ];
        }

        $repaymentsTotalAllTime = (float) ($repaymentsAllTime->total ?? 0);
        $repaymentsCountAllTime = (int) ($repaymentsAllTime->cnt ?? 0);

        return [
            'all_time_total' => (float) ($allTime->total ?? 0) + $repaymentsTotalAllTime,
            'all_time_count' => (int) ($allTime->cnt ?? 0) + $repaymentsCountAllTime,
            'late_total' => $lateTotal,
            'this_month_total' => (float) ($thisMonth->total ?? 0) + (float) ($repaymentsThisMonth->total ?? 0),
            'this_month_count' => $thisCnt,
            'this_month_late' => (int) ($thisMonth->late ?? 0),
            'last_month_total' => (float) ($lastMonth->total ?? 0) + (float) ($repaymentsLastMonth->total ?? 0),
            'last_month_count' => $lastCnt,
            'last_month_late' => (int) ($lastMonth->late ?? 0),
            'compliance_this' => $complianceThis,
            'compliance_last' => $complianceLast,
            'active_members' => $activeCount,
            'trend' => $trend,
            'this_month_label' => $now->format('F Y'),
            'last_month_label' => $prev->format('F Y'),
        ];
    }
}
