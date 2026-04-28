@php
    $d = $this->getData();
    $chart = $d['chart'];
    $opts = $d['options'];
@endphp

<div
    class="rounded-2xl bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700/60 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div
        class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-chart-pie class="w-5 h-5 text-primary-500" />
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Loan Portfolio') }}</h3>
        </div>
        <span class="text-xs text-gray-400">{{ __(':count total loans', ['count' => $d['total_all']]) }}</span>
    </div>

    <div class="flex flex-col gap-4 p-5">

        {{-- Summary: total amount --}}
        <div
            class="rounded-xl bg-indigo-50 dark:bg-indigo-900/20 ring-1 ring-indigo-100 dark:ring-indigo-800 px-4 py-3 text-center">
            <p class="text-xs font-medium text-indigo-500 dark:text-indigo-400 uppercase tracking-wide">
                {{ __('Total Loan Value') }}
            </p>
            <p class="mt-0.5 text-xl font-bold text-indigo-700 dark:text-indigo-300">SAR
                {{ number_format($d['total_amt'], 0) }}
            </p>
        </div>

        {{-- Doughnut chart --}}
        <div class="flex-1 flex items-center justify-center" style="min-height: 180px; max-height: 220px;" wire:ignore
            x-data="{
                chart: null,
                init() {
                    this.$nextTick(() => {
                        if (typeof Chart !== 'undefined') {
                            this.chart = new Chart(this.$refs.canvas, {
                                type: 'doughnut',
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

        {{-- Legend --}}
        <div class="space-y-1.5">
            @foreach($d['legend'] as $item)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="flex-shrink-0 w-2.5 h-2.5 rounded-full"
                            style="background: {{ $item['color'] }}"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400 truncate">{{ $item['label'] }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                        <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $item['count'] }}</span>
                        <span class="text-xs text-gray-400 w-7 text-right">{{ $item['pct'] }}%</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>