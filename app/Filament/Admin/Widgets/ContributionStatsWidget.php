<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
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
            $trend[] = [
                'label' => $d->format('M'),
                'total' => (float) ($row->total ?? 0),
                'cnt' => (int) ($row->cnt ?? 0),
                'pct' => $activeCount > 0 ? round(($row->cnt ?? 0) / $activeCount * 100) : 0,
            ];
        }

        return [
            'all_time_total' => (float) ($allTime->total ?? 0),
            'all_time_count' => (int) ($allTime->cnt ?? 0),
            'late_total' => $lateTotal,
            'this_month_total' => (float) ($thisMonth->total ?? 0),
            'this_month_count' => $thisCnt,
            'this_month_late' => (int) ($thisMonth->late ?? 0),
            'last_month_total' => (float) ($lastMonth->total ?? 0),
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
