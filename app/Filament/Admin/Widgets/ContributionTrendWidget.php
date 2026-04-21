<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ContributionTrendWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.contribution-trend';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $months = [];
        $labels = [];
        $totals = [];
        $memberCounts = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->startOfMonth();
            $months[] = ['month' => (int) $date->month, 'year' => (int) $date->year, 'label' => $date->locale(app()->getLocale())->translatedFormat('M Y')];
        }

        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $rows = Contribution::selectRaw(
            'month, year, SUM(amount) as total, COUNT(DISTINCT member_id) as member_count'
        )
            ->where(fn($q) => $q->where('year', '>', $startDate->year)
                ->orWhere(fn($q2) => $q2->where('year', $startDate->year)->where('month', '>=', $startDate->month)))
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn($r) => sprintf('%04d-%02d', $r->year, $r->month));

        $totalActive = max(1, Member::active()->count());

        foreach ($months as $m) {
            $key = sprintf('%04d-%02d', $m['year'], $m['month']);
            $row = $rows->get($key);
            $labels[] = $m['label'];
            $totals[] = $row ? (float) $row->total : 0;
            $memberCounts[] = $row ? (int) $row->member_count : 0;
        }

        $total12m = array_sum($totals);
        $avg12m = count(array_filter($totals)) > 0
            ? $total12m / max(1, count(array_filter($totals)))
            : 0;
        $bestMonth = max($totals);
        $bestLabel = $bestMonth > 0 ? $labels[array_search($bestMonth, $totals)] : __('N/A');
        $lastTotal = end($totals) ?: 0;
        $prevTotal = count($totals) >= 2 ? $totals[count($totals) - 2] : 0;
        $trend = $prevTotal > 0 ? round(($lastTotal - $prevTotal) / $prevTotal * 100) : 0;

        return [
            'chart' => [
                'datasets' => [
                    [
                        'label' => __('Total Contributions (SAR)'),
                        'data' => $totals,
                        'backgroundColor' => 'rgba(16,185,129,0.7)',
                        'borderColor' => 'rgba(16,185,129,1)',
                        'borderWidth' => 1,
                        'borderRadius' => 4,
                        'yAxisID' => 'y',
                        'order' => 1,
                    ],
                    [
                        'label' => __('Members Who Contributed'),
                        'data' => $memberCounts,
                        'type' => 'line',
                        'borderColor' => 'rgba(99,102,241,1)',
                        'backgroundColor' => 'rgba(99,102,241,0.1)',
                        'borderWidth' => 2,
                        'pointRadius' => 4,
                        'tension' => 0.3,
                        'fill' => false,
                        'yAxisID' => 'y1',
                        'order' => 0,
                    ],
                ],
                'labels' => $labels,
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => ['position' => 'left', 'title' => ['display' => true, 'text' => __('SAR')], 'beginAtZero' => true],
                    'y1' => ['position' => 'right', 'title' => ['display' => true, 'text' => __('Members')], 'grid' => ['drawOnChartArea' => false], 'beginAtZero' => true],
                ],
                'plugins' => [
                    'legend' => ['position' => 'top'],
                    'tooltip' => ['mode' => 'index', 'intersect' => false],
                ],
            ],
            'summary' => [
                'total_12m' => $total12m,
                'avg_monthly' => $avg12m,
                'best_month' => $bestMonth,
                'best_label' => $bestLabel,
                'trend' => $trend,
                'last_total' => $lastTotal,
            ],
        ];
    }
}
