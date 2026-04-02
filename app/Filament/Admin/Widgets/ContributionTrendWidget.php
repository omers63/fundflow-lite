<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Contribution;
use App\Models\Member;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ContributionTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Contribution Trend — Last 12 Months';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $months      = [];
        $labels      = [];
        $totals      = [];
        $lateTotals  = [];
        $memberCounts = [];
        $compliance  = [];

        // Build 12 months going backwards from last month
        for ($i = 11; $i >= 0; $i--) {
            $date   = Carbon::now()->subMonths($i)->startOfMonth();
            $m      = (int) $date->month;
            $y      = (int) $date->year;
            $months[] = ['month' => $m, 'year' => $y, 'label' => $date->format('M Y')];
        }

        // Fetch all contributions for the 12-month window in one query
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();
        $rows = Contribution::selectRaw(
                'month, year, SUM(amount) as total, SUM(is_late) as late_count, COUNT(DISTINCT member_id) as member_count'
            )
            ->where(function ($q) use ($startDate) {
                $q->where('year', '>', $startDate->year)
                  ->orWhere(function ($q2) use ($startDate) {
                      $q2->where('year', $startDate->year)
                         ->where('month', '>=', $startDate->month);
                  });
            })
            ->groupBy('year', 'month')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->year, $r->month));

        // Active member count at reporting time (approximation: current active count)
        $totalActive = max(1, Member::active()->count());

        foreach ($months as $m) {
            $key      = sprintf('%04d-%02d', $m['year'], $m['month']);
            $row      = $rows->get($key);
            $labels[]      = $m['label'];
            $totals[]      = $row ? (float) $row->total : 0;
            $lateTotals[]  = $row ? (float) ($row->total - ($row->total - ($row->late_count > 0 ? $row->late_count * 500 : 0))) : 0;
            $memberCounts[] = $row ? (int) $row->member_count : 0;
            $compliance[]  = $row ? round($row->member_count / $totalActive * 100) : 0;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Total Contributions (SAR)',
                    'data'            => $totals,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor'     => 'rgba(16, 185, 129, 1)',
                    'borderWidth'     => 1,
                    'yAxisID'         => 'y',
                    'order'           => 1,
                ],
                [
                    'label'           => 'Members Who Contributed',
                    'data'            => $memberCounts,
                    'type'            => 'line',
                    'borderColor'     => 'rgba(99, 102, 241, 1)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'borderWidth'     => 2,
                    'pointRadius'     => 4,
                    'tension'         => 0.3,
                    'fill'            => false,
                    'yAxisID'         => 'y1',
                    'order'           => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y'  => ['position' => 'left',  'title' => ['display' => true, 'text' => 'SAR']],
                'y1' => ['position' => 'right', 'title' => ['display' => true, 'text' => 'Members'], 'grid' => ['drawOnChartArea' => false]],
            ],
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
        ];
    }
}
