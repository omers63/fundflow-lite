<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class ContributionHistoryWidget extends Widget
{
    protected string $view = 'filament.member.widgets.contribution-history';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getData(): array
    {
        $member = auth()->user()?->member;
        if (!$member) {
            return ['hasMember' => false, 'chart' => ['datasets' => [], 'labels' => []], 'options' => [], 'summary' => []];
        }

        $labels = [];
        $amounts = [];
        $lateFlags = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->startOfMonth();
            $m = (int) $date->month;
            $y = (int) $date->year;
            $labels[] = $date->format('M Y');

            $row = Contribution::where('member_id', $member->id)
                ->where('month', $m)->where('year', $y)->first();

            $amounts[] = $row ? (float) $row->amount : 0;
            $lateFlags[] = $row && $row->is_late;
        }

        $colors = array_map(function ($amt, $late) {
            if ($amt === 0.0) {
                return 'rgba(209,213,219,0.6)';
            }

            return $late ? 'rgba(251,191,36,0.85)' : 'rgba(16,185,129,0.85)';
        }, $amounts, $lateFlags);

        $paid = count(array_filter($amounts));
        $missed = count(array_filter($amounts, fn($a) => $a === 0.0));
        $lateC = count(array_filter($lateFlags));
        $total = array_sum($amounts);

        return [
            'hasMember' => true,
            'chart' => [
                'datasets' => [
                    [
                        'label' => 'Contribution (SAR)',
                        'data' => $amounts,
                        'backgroundColor' => $colors,
                        'borderColor' => array_map(
                            fn($c) => str_replace(['0.85', '0.6'], '1', $c),
                            $colors
                        ),
                        'borderWidth' => 1,
                        'borderRadius' => 4,
                    ]
                ],
                'labels' => $labels,
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'SAR']],
                ],
                'plugins' => [
                    'legend' => ['display' => false],
                    'tooltip' => ['callbacks' => []],
                ],
            ],
            'summary' => [
                'paid' => $paid,
                'missed' => $missed,
                'late' => $lateC,
                'total' => $total,
            ],
        ];
    }
}
