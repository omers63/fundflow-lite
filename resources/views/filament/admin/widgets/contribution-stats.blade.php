@php
    $d = $this->getData();
    $fmt = fn(float $v) => $v >= 1_000_000
        ? number_format($v / 1_000_000, 2) . 'M'
        : ($v >= 1_000 ? number_format($v / 1_000, 1) . 'k' : number_format($v, 0));
    $compColor = fn(int $pct) => $pct >= 90 ? 'bg-emerald-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-red-500');
    $compText  = fn(int $pct) => $pct >= 90
        ? 'text-emerald-600 dark:text-emerald-400'
        : ($pct >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');
@endphp

<div class="w-full max-w-none space-y-4 mb-2">

    {{-- ── KPI row ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">

        {{-- All-time total --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Collected</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">SAR {{ $fmt($d['all_time_total']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ number_format($d['all_time_count']) }} payments all time</p>
            </div>
        </div>

        {{-- This month total --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-calendar-days class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $d['this_month_label'] }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">SAR {{ $fmt($d['this_month_total']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $d['this_month_count'] }} of {{ $d['active_members'] }} members</p>
            </div>
        </div>

        {{-- This month compliance --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $compColor($d['compliance_this']) }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-chart-pie class="w-4 h-4 {{ $compText($d['compliance_this']) }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Compliance (this mo.)</p>
                </div>
                <p class="text-2xl font-bold {{ $compText($d['compliance_this']) }}">{{ $d['compliance_this'] }}%</p>
                <div class="mt-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700 h-1.5">
                    <div class="h-1.5 rounded-full {{ $compColor($d['compliance_this']) }}" style="width: {{ min(100, $d['compliance_this']) }}%"></div>
                </div>
            </div>
        </div>

        {{-- Last month compliance --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $compColor($d['compliance_last']) }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Compliance (prev. mo.)</p>
                </div>
                <p class="text-2xl font-bold {{ $compText($d['compliance_last']) }}">{{ $d['compliance_last'] }}%</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $d['last_month_label'] }}</p>
            </div>
        </div>

        {{-- Late payments --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['late_total'] > 0 ? 'bg-amber-400' : 'bg-emerald-500' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 {{ $d['late_total'] > 0 ? 'text-amber-500' : 'text-emerald-500' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Late Payments</p>
                </div>
                <p class="text-2xl font-bold {{ $d['late_total'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($d['late_total']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">All-time late payments</p>
            </div>
        </div>

    </div>

    {{-- ── 6-month trend bars ────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
            <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-gray-400" />
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">6-Month Contribution Trend</h4>
            <span class="ml-auto text-xs text-gray-400">by compliance %</span>
        </div>
        <div class="px-5 py-4">
            <div class="flex items-end gap-3 h-20">
                @php $maxTotal = max(1, collect($d['trend'])->max('total')); @endphp
                @foreach($d['trend'] as $t)
                @php $barH = round($t['total'] / $maxTotal * 100); @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $t['pct'] }}%</span>
                    <div class="w-full rounded-t-md bg-primary-500/20 dark:bg-primary-500/10 relative" style="height: {{ max(4, $barH) }}%">
                        <div class="absolute bottom-0 left-0 right-0 rounded-t-md {{ $t['pct'] >= 90 ? 'bg-emerald-500' : ($t['pct'] >= 70 ? 'bg-amber-400' : 'bg-red-400') }}"
                             style="height: {{ max(4, $barH) }}%"></div>
                    </div>
                    <span class="text-xs text-gray-400">{{ $t['label'] }}</span>
                </div>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>≥90% compliant</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>70–89%</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span>&lt;70%</span>
            </div>
        </div>
    </div>

</div>
