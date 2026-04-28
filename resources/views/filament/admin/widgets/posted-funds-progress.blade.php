@php
    $d = $this->getData();
    $chart = $d['chart'];
    $opts = $d['options'];
@endphp

<div
    class="rounded-2xl bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700/60 shadow-sm overflow-hidden">
    <div
        class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-chart-bar-square class="w-5 h-5 text-sky-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('Posted Funds Progress — Current Cycle') }}</h3>
        </div>
    </div>

    <div class="p-5" style="height: 300px;" wire:ignore x-data="{
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
</div>