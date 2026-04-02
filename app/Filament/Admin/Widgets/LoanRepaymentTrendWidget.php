<?php

namespace App\Filament\Admin\Widgets;

use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class LoanRepaymentTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Loan Repayment Trend — Last 12 Months';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $labels   = [];
        $onTime   = [];
        $late     = [];
        $overdue  = [];

        for ($i = 11; $i >= 0; $i--) {
            $date  = Carbon::now()->subMonths($i)->startOfMonth();
            $m     = (int) $date->month;
            $y     = (int) $date->year;
            $labels[] = $date->format('M Y');

            $paid = LoanInstallment::whereYear('due_date', $y)
                ->whereMonth('due_date', $m)
                ->where('status', 'paid')
                ->selectRaw('SUM(CASE WHEN is_late = 0 THEN amount ELSE 0 END) as on_time_amount,
                              SUM(CASE WHEN is_late = 1 THEN amount ELSE 0 END) as late_amount')
                ->first();

            $overdueAmt = LoanInstallment::whereYear('due_date', $y)
                ->whereMonth('due_date', $m)
                ->where('status', 'overdue')
                ->sum('amount');

            $onTime[]  = $paid ? (float) $paid->on_time_amount : 0;
            $late[]    = $paid ? (float) $paid->late_amount : 0;
            $overdue[] = (float) $overdueAmt;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'On-time (SAR)',
                    'data'            => $onTime,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'stack'           => 'repayments',
                ],
                [
                    'label'           => 'Late (SAR)',
                    'data'            => $late,
                    'backgroundColor' => 'rgba(251, 191, 36, 0.8)',
                    'stack'           => 'repayments',
                ],
                [
                    'label'           => 'Still Overdue (SAR)',
                    'data'            => $overdue,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'stack'           => 'repayments',
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
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'title' => ['display' => true, 'text' => 'SAR']],
            ],
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
        ];
    }
}
