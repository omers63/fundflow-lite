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
            <x-heroicon-o-credit-card class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Loan Repayment Trend — Last 12 Months</h3>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold
            {{ $s['recovery_rate'] >= 90 ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300'
             : ($s['recovery_rate'] >= 70 ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300'
                                          : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300') }}">
            {{ $s['recovery_rate'] }}% recovery rate
        </span>
    </div>

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">On-time Repaid</p>
            </div>
            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">﷼ {{ number_format($s['total_on_time'], 0) }}</p>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Late Repaid</p>
            </div>
            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">﷼ {{ number_format($s['total_late'], 0) }}</p>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Still Overdue</p>
            </div>
            <p class="text-lg font-bold {{ $s['total_overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                ﷼ {{ number_format($s['total_overdue'], 0) }}
            </p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Total Collected</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">﷼ {{ number_format($s['total_repaid'], 0) }}</p>
            @if($s['overdue_count'] > 0)
            <p class="text-xs text-red-500 dark:text-red-400">{{ $s['overdue_count'] }} installment{{ $s['overdue_count'] !== 1 ? 's' : '' }} pending</p>
            @else
            <p class="text-xs text-emerald-500 dark:text-emerald-400">All current</p>
            @endif
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

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-4 px-6 pb-4">
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-emerald-500/80 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">On-time</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-amber-400/80 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Late</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-red-500/80 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Overdue</span>
        </div>
    </div>
</div>
