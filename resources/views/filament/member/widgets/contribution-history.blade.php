@php
    $d = $this->getData();
    $chart = $d['chart'];
    $opts = $d['options'];
    $s = $d['summary'];
@endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div
        class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-calendar-days class="w-5 h-5 text-emerald-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('My Contribution History — Last 12 Months') }}</h3>
        </div>
    </div>

    @if(!$d['hasMember'])
        <div class="px-6 py-8 text-center text-sm text-gray-400">{{ __('No member record found.') }}</div>
    @else

        {{-- Summary tiles --}}
        <div
            class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
            <div class="px-5 py-4">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Paid On-time') }}</p>
                </div>
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $s['paid'] - $s['late'] }}</p>
            </div>
            <div class="px-5 py-4">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Paid Late') }}</p>
                </div>
                <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $s['late'] }}</p>
            </div>
            <div class="px-5 py-4">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Missed') }}</p>
                </div>
                <p class="text-lg font-bold {{ $s['missed'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                    {{ $s['missed'] }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('12-Month Total') }}</p>
                <p class="text-lg font-bold text-gray-900 dark:text-white">{{ __('SAR') }}
                    {{ number_format($s['total'], 0) }}</p>
            </div>
        </div>

        {{-- Fallback chart-free bars (keeps widget robust without Chart.js assets) --}}
        @php
            $values = $chart['datasets'][0]['data'] ?? [];
            $labels = $chart['labels'] ?? [];
            $max = max(1, (float) max($values ?: [0]));
        @endphp
    <div class="px-5 pt-4 pb-2">
        <div
            class="gap-1.5 items-end h-36"
            style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr));"
        >
                @foreach($values as $idx => $value)
                    @php
                        $amount = (float) $value;
                        $height = $max > 0 ? max(6, (int) round(($amount / $max) * 100)) : 6;
                        $isMissed = $amount <= 0.0;
                    @endphp
                    <div class="flex flex-col items-center justify-end gap-1 h-full"
                        title="{{ ($labels[$idx] ?? '') . ' — SAR ' . number_format($amount, 2) }}">
                        <div class="w-full h-full bg-gray-100/70 dark:bg-gray-700/40 rounded-sm relative overflow-hidden">
                            <div class="absolute bottom-0 left-0 right-0 rounded-t-sm {{ $isMissed ? 'bg-gray-300 dark:bg-gray-600' : 'bg-emerald-500/80' }}"
                                style="height: {{ $height }}%;">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        <div
            class="gap-1.5 mt-2"
            style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr));"
        >
                @foreach($labels as $label)
                <div
                    class="text-[10px] text-gray-400 text-center"
                    style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                    title="{{ $label }}"
                >
                    {{ $label }}
                </div>
                @endforeach
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 px-6 pb-4">
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-emerald-500/85 inline-block"></span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Paid on time') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-amber-400/85 inline-block"></span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Paid late') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-sm bg-gray-300/60 dark:bg-gray-600 inline-block"></span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Missed') }}</span>
            </div>
        </div>
    @endif
</div>