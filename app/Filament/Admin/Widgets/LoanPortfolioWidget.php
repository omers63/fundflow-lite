<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Loan;
use Filament\Widgets\ChartWidget;

class LoanPortfolioWidget extends ChartWidget
{
    protected ?string $heading = 'Loan Portfolio — Status Distribution';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $statuses = [
            'pending' => ['label' => 'Pending',       'color' => 'rgba(251, 191, 36, 0.85)'],
            'approved' => ['label' => 'Approved',      'color' => 'rgba(99, 102, 241, 0.85)'],
            'active' => ['label' => 'Active',        'color' => 'rgba(16, 185, 129, 0.85)'],
            'completed' => ['label' => 'Completed',     'color' => 'rgba(107, 114, 128, 0.85)'],
            'early_settled' => ['label' => 'Early Settled', 'color' => 'rgba(52, 211, 153, 0.85)'],
            'rejected' => ['label' => 'Rejected',      'color' => 'rgba(239, 68, 68, 0.85)'],
            'cancelled' => ['label' => 'Cancelled',     'color' => 'rgba(156, 163, 175, 0.85)'],
        ];

        $counts = Loan::selectRaw('status, COUNT(*) as count, SUM(amount_approved) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($statuses as $key => $cfg) {
            $row = $counts->get($key);
            if ($row && $row->count > 0) {
                $labels[] = $cfg['label'].' ('.$row->count.')';
                $data[] = (int) $row->count;
                $colors[] = $cfg['color'];
            }
        }

        return [
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $colors,
                'borderWidth' => 2,
                'borderColor' => '#ffffff',
                'hoverOffset' => 6,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'right'],
                'tooltip' => [
                    'callbacks' => [],
                ],
            ],
            'cutout' => '62%',
        ];
    }
}
