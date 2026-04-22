@php
    $d     = $this->getData();
    $chart = $d['chart'];
    $opts  = $d['options'];
    $s     = $d['summary'];
@endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-emerald-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Contribution Trend — Last 12 Months') }}</h3>
        </div>
        @if($s['trend'] !== 0)
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold
            {{ $s['trend'] >= 0 ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300' }}">
            {{ $s['trend'] >= 0 ? '▲' : '▼' }} {{ __(':pct% vs prev month', ['pct' => abs($s['trend'])]) }}
        </span>
        @endif
    </div>

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('12-Month Total') }}</p>
            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">SAR {{ number_format($s['total_12m'], 0) }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Monthly Average') }}</p>
            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">SAR {{ number_format($s['avg_monthly'], 0) }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Best Month') }}</p>
            <p class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">SAR {{ number_format($s['best_month'], 0) }}</p>
            <p class="text-xs text-gray-400">{{ $s['best_label'] }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Last Month') }}</p>
            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">SAR {{ number_format($s['last_total'], 0) }}</p>
        </div>
    </div>

    {{-- Chart --}}
    <div class="p-5" style="height: 280px;"
        wire:ignore
        x-data="{
            chart: null,
            init() {
                this.$nextTick(() => {
                    if (typeof Chart !== 'undefined') {
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: @js($chart),
                            options: @js($opts)
                        });
                    }
                });
            },
            destroy() { if (this.chart) this.chart.destroy(); }
        }">
        <canvas x-ref="canvas" class="w-full h-full"></canvas>
    </div>

    {{-- Legend note --}}
    <div class="flex items-center gap-4 px-6 pb-4">
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-emerald-500/70 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total Contributions (SAR)') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-0.5 bg-indigo-500 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Members Who Contributed') }}</span>
        </div>
    </div>
</div>
