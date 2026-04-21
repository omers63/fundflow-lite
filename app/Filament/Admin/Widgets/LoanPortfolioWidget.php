<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Loan;
use Filament\Widgets\Widget;

class LoanPortfolioWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.loan-portfolio';

    protected static ?int $sort = 4;

    public function getData(): array
    {
        $statuses = [
            'pending' => ['label' => __('Pending'), 'color' => 'rgba(251,191,36,0.85)', 'ring' => 'ring-amber-300', 'bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-300'],
            'approved' => ['label' => __('Approved'), 'color' => 'rgba(99,102,241,0.85)', 'ring' => 'ring-indigo-300', 'bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'text' => 'text-indigo-700 dark:text-indigo-300'],
            'active' => ['label' => __('Active'), 'color' => 'rgba(16,185,129,0.85)', 'ring' => 'ring-emerald-300', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-300'],
            'completed' => ['label' => __('Completed'), 'color' => 'rgba(107,114,128,0.85)', 'ring' => 'ring-gray-300', 'bg' => 'bg-gray-100 dark:bg-gray-700', 'text' => 'text-gray-600 dark:text-gray-300'],
            'early_settled' => ['label' => __('Early Settled'), 'color' => 'rgba(52,211,153,0.85)', 'ring' => 'ring-teal-300', 'bg' => 'bg-teal-100 dark:bg-teal-900/30', 'text' => 'text-teal-700 dark:text-teal-300'],
            'rejected' => ['label' => __('Rejected'), 'color' => 'rgba(239,68,68,0.85)', 'ring' => 'ring-red-300', 'bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-300'],
            'cancelled' => ['label' => __('Cancelled'), 'color' => 'rgba(156,163,175,0.85)', 'ring' => 'ring-gray-200', 'bg' => 'bg-gray-50 dark:bg-gray-800', 'text' => 'text-gray-500 dark:text-gray-400'],
        ];

        $counts = Loan::selectRaw('status, COUNT(*) as count, SUM(amount_approved) as total')
            ->groupBy('status')->get()->keyBy('status');

        $labels = [];
        $data = [];
        $colors = [];
        $legend = [];
        $totalAll = $counts->sum('count');
        $totalAmt = $counts->sum('total');

        foreach ($statuses as $key => $cfg) {
            $row = $counts->get($key);
            if ($row && $row->count > 0) {
                $labels[] = $cfg['label'];
                $data[] = (int) $row->count;
                $colors[] = $cfg['color'];
                $legend[] = [
                    'label' => $cfg['label'],
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                    'pct' => $totalAll > 0 ? round($row->count / $totalAll * 100) : 0,
                    'bg' => $cfg['bg'],
                    'text' => $cfg['text'],
                    'ring' => $cfg['ring'],
                    'color' => $cfg['color'],
                ];
            }
        }

        return [
            'chart' => [
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff',
                        'hoverOffset' => 6,
                    ]
                ],
                'labels' => $labels,
            ],
            'options' => [
                'plugins' => ['legend' => ['display' => false], 'tooltip' => ['callbacks' => []]],
                'cutout' => '65%',
                'maintainAspectRatio' => true,
            ],
            'legend' => $legend,
            'total_all' => $totalAll,
            'total_amt' => $totalAmt,
        ];
    }
}
