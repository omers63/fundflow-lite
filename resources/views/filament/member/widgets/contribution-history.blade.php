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
            <x-heroicon-o-calendar-days class="w-5 h-5 text-emerald-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">My Contribution History — Last 12 Months</h3>
        </div>
    </div>

    @if(!$d['hasMember'])
    <div class="px-6 py-8 text-center text-sm text-gray-400">No member record found.</div>
    @else

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Paid On-time</p>
            </div>
            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $s['paid'] - $s['late'] }}</p>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Paid Late</p>
            </div>
            <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $s['late'] }}</p>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-center gap-1.5 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Missed</p>
            </div>
            <p class="text-lg font-bold {{ $s['missed'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">{{ $s['missed'] }}</p>
        </div>
        <div class="px-5 py-4">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">12-Month Total</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">﷼ {{ number_format($s['total'], 0) }}</p>
        </div>
    </div>

    {{-- Chart --}}
    <div class="p-5" style="height: 240px;"
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
            <span class="w-3 h-3 rounded-sm bg-emerald-500/85 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Paid on time</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-amber-400/85 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Paid late</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-gray-300/60 dark:bg-gray-600 inline-block"></span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Missed</span>
        </div>
    </div>
    @endif
</div>
