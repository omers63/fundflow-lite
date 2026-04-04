<?php

namespace App\Filament\Member\Widgets;

use App\Models\Contribution;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ContributionHistoryWidget extends ChartWidget
{
    protected ?string $heading = 'My Contribution History — Last 12 Months';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $member = auth()->user()?->member;
        if (! $member) {
            return ['datasets' => [], 'labels' => []];
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
                ->where('month', $m)
                ->where('year', $y)
                ->first();

            $amounts[] = $row ? (float) $row->amount : 0;
            $lateFlags[] = $row && $row->is_late;
        }

        // Color each bar: late = amber, paid = emerald, missed = light gray
        $colors = [];
        foreach ($amounts as $idx => $amount) {
            if ($amount === 0.0) {
                $colors[] = 'rgba(209, 213, 219, 0.6)'; // gray / missed
            } elseif ($lateFlags[$idx]) {
                $colors[] = 'rgba(251, 191, 36, 0.85)'; // amber / late
            } else {
                $colors[] = 'rgba(16, 185, 129, 0.85)'; // emerald / on-time
            }
        }

        return [
            'datasets' => [[
                'label' => 'Contribution (SAR)',
                'data' => $amounts,
                'backgroundColor' => $colors,
                'borderColor' => array_map(fn ($c) => str_replace('0.85', '1', str_replace('0.6', '1', $c)), $colors),
                'borderWidth' => 1,
                'borderRadius' => 4,
            ]],
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
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'SAR'],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        return '🟢 On-time   🟡 Late   ⬜ Missed';
    }
}
