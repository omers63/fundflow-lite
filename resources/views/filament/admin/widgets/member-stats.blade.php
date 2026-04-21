@php
    $d = $this->getData();
    $total = max(1, $d['total']);
    $activePct      = round($d['active']      / $total * 100);
    $suspendedPct   = round($d['suspended']   / $total * 100);
    $delinquentPct  = round($d['delinquent']  / $total * 100);
    $terminatedPct  = round($d['terminated']  / $total * 100);
@endphp

<div class="w-full max-w-none space-y-4 mb-2">

    {{-- ── KPI row ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">

        {{-- Total members --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-users class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Total Members') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['total']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('Across all statuses') }}</p>
            </div>
        </div>

        {{-- Active --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Active') }}</p>
                </div>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($d['active']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% of total', ['pct' => $activePct]) }}</p>
            </div>
        </div>

        {{-- Delinquent --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['delinquent'] > 0 ? 'bg-red-500' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-user-minus class="w-4 h-4 {{ $d['delinquent'] > 0 ? 'text-red-500' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Delinquent') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $d['delinquent'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($d['delinquent']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% of total', ['pct' => $delinquentPct]) }}</p>
            </div>
        </div>

        {{-- Suspended --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['suspended'] > 0 ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-pause-circle class="w-4 h-4 {{ $d['suspended'] > 0 ? 'text-amber-500' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Suspended') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $d['suspended'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($d['suspended']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% of total', ['pct' => $suspendedPct]) }}</p>
            </div>
        </div>

        {{-- Terminated --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['terminated'] > 0 ? 'bg-red-600' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-no-symbol class="w-4 h-4 {{ $d['terminated'] > 0 ? 'text-red-600' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Terminated') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $d['terminated'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($d['terminated']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% of total', ['pct' => $terminatedPct]) }}</p>
            </div>
        </div>

        {{-- New this month --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-user-plus class="w-4 h-4 text-indigo-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('New This Month') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['new_this_month'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ now()->locale(app()->getLocale())->translatedFormat('F Y') }}</p>
            </div>
        </div>

        {{-- With active loans --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-credit-card class="w-4 h-4 text-teal-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('With Active Loans') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['with_active_loans'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':count have overdue installments', ['count' => $d['with_overdue']]) }}</p>
            </div>
        </div>

    </div>

    {{-- ── Bottom row: status breakdown + top contributors ─────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- Status breakdown --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-chart-bar class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Membership Status') }}</h4>
            </div>
            <div class="px-5 py-4 space-y-3">
                @foreach([
                    ['label' => __('Active'),     'count' => $d['active'],     'color' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400'],
                    ['label' => __('Delinquent'), 'count' => $d['delinquent'], 'color' => 'bg-red-500',     'text' => 'text-red-600 dark:text-red-400'],
                    ['label' => __('Suspended'),  'count' => $d['suspended'],  'color' => 'bg-amber-400',   'text' => 'text-amber-600 dark:text-amber-400'],
                    ['label' => __('Terminated'), 'count' => $d['terminated'], 'color' => 'bg-red-500',     'text' => 'text-red-600 dark:text-red-400'],
                ] as $row)
                @php $pct = $total > 0 ? round($row['count'] / $total * 100) : 0; @endphp
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $row['label'] }}</span>
                        <span class="text-xs font-semibold {{ $row['text'] }}">{{ number_format($row['count']) }} &middot; {{ $pct }}%</span>
                    </div>
                    <div class="w-full rounded-full bg-gray-100 dark:bg-gray-700 h-2">
                        <div class="h-2 rounded-full {{ $row['color'] }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
                <p class="text-xs text-gray-400 pt-1">{{ __('Avg. monthly contribution: SAR :amount', ['amount' => number_format($d['avg_contribution'])]) }}</p>
            </div>
        </div>

        {{-- Top contributors this year --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-trophy class="w-4 h-4 text-amber-500" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Top Contributors — :year', ['year' => $d['year_label']]) }}</h4>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($d['top_contributors'] as $i => $c)
                <div class="flex items-center gap-3 px-5 py-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                        {{ $i === 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                        {{ $i + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $c['name'] }}</p>
                        <p class="text-xs text-gray-400">{{ $c['number'] }}</p>
                    </div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('SAR') }} {{ number_format($c['total']) }}</span>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-sm text-gray-400">{{ __('No contribution data yet.') }}</div>
                @endforelse
            </div>
        </div>

    </div>

</div>
