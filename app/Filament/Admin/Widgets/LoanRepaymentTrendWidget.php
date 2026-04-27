<?php

namespace App\Filament\Admin\Widgets;

use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class LoanRepaymentTrendWidget extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Loan Repayment Trend — Last 12 Months';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumnSpan(): int | string | array
    {
        return 'full';
    }

    protected function getData(): array
    {
        $labels = [];
        $onTime = [];
        $late = [];
        $overdue = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i)->startOfMonth();
            $m = (int) $date->month;
            $y = (int) $date->year;
            $labels[] = $date->locale(app()->getLocale())->translatedFormat('M Y');

            $paid = LoanInstallment::whereYear('due_date', $y)
                ->whereMonth('due_date', $m)
                ->where('status', 'paid')
                ->selectRaw(
                    'SUM(CASE WHEN is_late = 0 THEN amount ELSE 0 END) as on_time_amount,
                     SUM(CASE WHEN is_late = 1 THEN amount ELSE 0 END) as late_amount'
                )->first();

            $overdueAmt = LoanInstallment::whereYear('due_date', $y)
                ->whereMonth('due_date', $m)
                ->where('status', 'overdue')
                ->sum('amount');

            $onTime[] = $paid ? (float) $paid->on_time_amount : 0;
            $late[] = $paid ? (float) $paid->late_amount : 0;
            $overdue[] = (float) $overdueAmt;
        }

        $totalOnTime = array_sum($onTime);
        $totalLate = array_sum($late);
        $totalOverdue = array_sum($overdue);
        $totalRepaid = $totalOnTime + $totalLate;
        $recoveryRate = ($totalRepaid + $totalOverdue) > 0
            ? round($totalRepaid / ($totalRepaid + $totalOverdue) * 100)
            : 100;

        return [
            'datasets' => [
                ['label' => __('On-time (SAR)'), 'data' => $onTime, 'backgroundColor' => 'rgba(16,185,129,0.8)', 'stack' => 'r'],
                ['label' => __('Late (SAR)'), 'data' => $late, 'backgroundColor' => 'rgba(251,191,36,0.8)', 'stack' => 'r'],
                ['label' => __('Overdue (SAR)'), 'data' => $overdue, 'backgroundColor' => 'rgba(239,68,68,0.8)', 'stack' => 'r'],
            ],
            'labels' => $labels,
        ];
    }

    public function getDescription(): ?string
    {
        $paid = LoanInstallment::where('status', 'paid')
            ->selectRaw(
                'SUM(CASE WHEN is_late = 0 THEN amount ELSE 0 END) as on_time_amount,
                 SUM(CASE WHEN is_late = 1 THEN amount ELSE 0 END) as late_amount'
            )->first();
        $overdue = (float) LoanInstallment::where('status', 'overdue')->sum('amount');
        $onTime = (float) ($paid->on_time_amount ?? 0);
        $late = (float) ($paid->late_amount ?? 0);
        $repaid = $onTime + $late;
        $rate = ($repaid + $overdue) > 0 ? round($repaid / ($repaid + $overdue) * 100) : 100;

        return __('Recovery rate: :rate% | Overdue balance: SAR :overdue', [
            'rate' => $rate,
            'overdue' => number_format($overdue, 0),
        ]);
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'title' => ['display' => true, 'text' => __('SAR')], 'beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
        ];
    }
}
