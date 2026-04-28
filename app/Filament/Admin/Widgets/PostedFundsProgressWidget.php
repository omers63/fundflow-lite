<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BankTransaction;
use App\Services\ContributionCycleService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class PostedFundsProgressWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Posted Funds Progress — Current Cycle';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    protected function getData(): array
    {
        $cycleService = app(ContributionCycleService::class);
        [$month, $year] = $cycleService->currentOpenPeriod();
        $start = $cycleService->cycleStartAt($month, $year)->startOfDay();
        $end = $cycleService->cycleDueEndAt($month, $year)->endOfDay();

        $labels = [];
        $dailyPosted = [];
        $cumulative = [];

        $rawRows = BankTransaction::query()
            ->selectRaw('DATE(transaction_date) as tx_day, SUM(amount) as total')
            ->where('transaction_type', 'credit')
            ->where('raw_data->source', 'member_portal_post')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('tx_day')
            ->orderBy('tx_day')
            ->get()
            ->keyBy('tx_day');

        $running = 0.0;
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()) as $day) {
            /** @var Carbon $day */
            $dateKey = $day->toDateString();
            $amount = (float) ($rawRows->get($dateKey)->total ?? 0);
            $running += $amount;

            $labels[] = $day->locale(app()->getLocale())->translatedFormat('j M');
            $dailyPosted[] = $amount;
            $cumulative[] = $running;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Daily posted funds (SAR)'),
                    'data' => $dailyPosted,
                    'backgroundColor' => 'rgba(59,130,246,0.55)',
                    'borderColor' => 'rgba(59,130,246,0.95)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                    'order' => 1,
                ],
                [
                    'label' => __('Cumulative posted funds (SAR)'),
                    'data' => $cumulative,
                    'type' => 'line',
                    'borderColor' => 'rgba(16,185,129,1)',
                    'backgroundColor' => 'rgba(16,185,129,0.15)',
                    'borderWidth' => 2,
                    'pointRadius' => 2.5,
                    'tension' => 0.25,
                    'fill' => false,
                    'yAxisID' => 'y',
                    'order' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getDescription(): ?string
    {
        $cycleService = app(ContributionCycleService::class);
        [$month, $year] = $cycleService->currentOpenPeriod();
        $start = $cycleService->cycleStartAt($month, $year)->startOfDay();
        $end = $cycleService->cycleDueEndAt($month, $year)->endOfDay();

        $total = (float) BankTransaction::query()
            ->where('transaction_type', 'credit')
            ->where('raw_data->source', 'member_portal_post')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $posts = (int) BankTransaction::query()
            ->where('transaction_type', 'credit')
            ->where('raw_data->source', 'member_portal_post')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        return __('Cycle window: :start - :end | Posted: :count tx | Total: SAR :total', [
            'start' => $start->locale(app()->getLocale())->translatedFormat('j M Y'),
            'end' => $end->locale(app()->getLocale())->translatedFormat('j M Y'),
            'count' => $posts,
            'total' => number_format($total, 2),
        ]);
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => __('SAR')],
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => ['position' => 'top'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
        ];
    }
}

