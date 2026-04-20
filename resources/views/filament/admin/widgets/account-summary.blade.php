@php $d = $this->getData(); @endphp

<div class="w-full max-w-none space-y-5 mb-2">

    {{-- ── Master account tiles ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">

        {{-- Master Cash --}}
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:col-span-1">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-sky-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-sky-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Master Cash') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if(abs($d['master_cash']) >= 1000000)
                        {{ number_format($d['master_cash'] / 1000000, 2) }}M
                    @elseif(abs($d['master_cash']) >= 1000)
                        {{ number_format($d['master_cash'] / 1000, 1) }}k
                    @else
                        {{ number_format($d['master_cash'], 0) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('SAR') }} {{ number_format($d['master_cash'], 2) }}</p>
            </div>
        </div>

        {{-- Master Fund --}}
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:col-span-1">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-building-library class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Master Fund') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if(abs($d['master_fund']) >= 1000000)
                        {{ number_format($d['master_fund'] / 1000000, 2) }}M
                    @elseif(abs($d['master_fund']) >= 1000)
                        {{ number_format($d['master_fund'] / 1000, 1) }}k
                    @else
                        {{ number_format($d['master_fund'], 0) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('SAR') }} {{ number_format($d['master_fund'], 2) }}</p>
            </div>
        </div>

        {{-- Member Cash --}}
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-users class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Member Cash') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if(abs($d['member_cash_total']) >= 1000000)
                        {{ number_format($d['member_cash_total'] / 1000000, 2) }}M
                    @elseif(abs($d['member_cash_total']) >= 1000)
                        {{ number_format($d['member_cash_total'] / 1000, 1) }}k
                    @else
                        {{ number_format($d['member_cash_total'], 0) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':count accounts', ['count' => $d['member_cash_count']]) }}</p>
            </div>
        </div>

        {{-- Member Fund --}}
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-chart-bar class="w-4 h-4 text-teal-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Member Fund') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if(abs($d['member_fund_total']) >= 1000000)
                        {{ number_format($d['member_fund_total'] / 1000000, 2) }}M
                    @elseif(abs($d['member_fund_total']) >= 1000)
                        {{ number_format($d['member_fund_total'] / 1000, 1) }}k
                    @else
                        {{ number_format($d['member_fund_total'], 0) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':count accounts', ['count' => $d['member_fund_count']]) }}</p>
            </div>
        </div>

        {{-- Loan Outstanding --}}
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-amber-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-credit-card class="w-4 h-4 text-amber-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Loan Outstanding') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    @if($d['loan_outstanding'] >= 1000000)
                        {{ number_format($d['loan_outstanding'] / 1000000, 2) }}M
                    @elseif($d['loan_outstanding'] >= 1000)
                        {{ number_format($d['loan_outstanding'] / 1000, 1) }}k
                    @else
                        {{ number_format($d['loan_outstanding'], 0) }}
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':count loan accounts', ['count' => $d['loan_count']]) }}</p>
            </div>
        </div>

        {{-- Coverage Ratio --}}
        @php
            $cov = $d['coverage'];
            $covColor = match(true) {
                $cov === null    => 'text-gray-400',
                $cov >= 1.5      => 'text-emerald-600 dark:text-emerald-400',
                $cov >= 1.0      => 'text-amber-600 dark:text-amber-400',
                default          => 'text-red-600 dark:text-red-400',
            };
            $covAccent = match(true) {
                $cov === null    => 'bg-gray-400',
                $cov >= 1.5      => 'bg-emerald-500',
                $cov >= 1.0      => 'bg-amber-500',
                default          => 'bg-red-500',
            };
        @endphp
        <div class="relative overflow-hidden col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $covAccent }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-scale class="w-4 h-4 {{ $covColor }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Coverage Ratio') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $covColor }}">
                    {{ $cov !== null ? number_format($cov, 2) . '×' : __('N/A') }}
                </p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('Fund ÷ loan exposure') }}</p>
            </div>
        </div>

    </div>

    {{-- ── Analytics row ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- 30-day activity card --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-arrow-path class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Transaction Activity — Last 30 Days') }}</h4>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Credits') }}</span>
                    </div>
                    <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ __('SAR') }} {{ number_format($d['activity_credits'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Debits') }}</span>
                    </div>
                    <span class="text-sm font-semibold text-red-600 dark:text-red-400">{{ __('SAR') }} {{ number_format($d['activity_debits'], 2) }}</span>
                </div>
                <div class="pt-2 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <span class="text-xs text-gray-400">{{ __('Total entries') }}</span>
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ number_format($d['activity_tx_count']) }}</span>
                </div>
                @php $netFlow = $d['activity_credits'] - $d['activity_debits']; @endphp
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-400">{{ __('Net flow') }}</span>
                    <span class="text-xs font-semibold {{ $netFlow >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $netFlow >= 0 ? '+' : '' }}{{ __('SAR') }} {{ number_format($netFlow, 2) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Balance distribution bars --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-chart-pie class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Balance Distribution') }}</h4>
            </div>
            @php
                $total = $d['master_cash'] + $d['master_fund'] + $d['member_cash_total'] + $d['member_fund_total'];
                $bars = [
                    ['label' => __('Master Cash'),   'value' => $d['master_cash'],        'color' => 'bg-sky-500'],
                    ['label' => __('Master Fund'),   'value' => $d['master_fund'],        'color' => 'bg-emerald-500'],
                    ['label' => __('Member Cash'),   'value' => $d['member_cash_total'],  'color' => 'bg-primary-500'],
                    ['label' => __('Member Fund'),   'value' => $d['member_fund_total'],  'color' => 'bg-teal-500'],
                ];
            @endphp
            <div class="px-5 py-4 space-y-3">
                @foreach($bars as $bar)
                @php $pct = $total > 0 ? min(100, round($bar['value'] / $total * 100)) : 0; @endphp
                <div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span>{{ $bar['label'] }}</span>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $pct }}%</span>
                    </div>
                    <div class="w-full rounded-full bg-gray-100 dark:bg-gray-700 h-1.5">
                        <div class="h-1.5 rounded-full {{ $bar['color'] }} transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
                <p class="pt-1 text-xs text-gray-400">{{ __('Total: SAR :amount', ['amount' => number_format($total, 2)]) }}</p>
            </div>
        </div>

        {{-- Health indicators --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-shield-check class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Fund Health Indicators') }}</h4>
            </div>
            <div class="px-5 py-4 space-y-3">

                {{-- Coverage ratio bar --}}
                @php
                    $covPct = $d['coverage'] !== null ? min(100, round($d['coverage'] / 2 * 100)) : 0;
                    $covBarColor = match(true) {
                        $d['coverage'] === null => 'bg-gray-400',
                        $d['coverage'] >= 1.5   => 'bg-emerald-500',
                        $d['coverage'] >= 1.0   => 'bg-amber-500',
                        default                  => 'bg-red-500',
                    };
                @endphp
                <div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span>{{ __('Fund coverage ratio') }}</span>
                        <span class="{{ $covColor }} font-medium">{{ $d['coverage'] !== null ? number_format($d['coverage'], 2) . '×' : __('N/A') }}</span>
                    </div>
                    <div class="w-full rounded-full bg-gray-100 dark:bg-gray-700 h-1.5">
                        <div class="h-1.5 rounded-full {{ $covBarColor }} transition-all" style="width: {{ $covPct }}%"></div>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-400">{{ __('Target: ≥ 1.5× (green zone)') }}</p>
                </div>

                {{-- Members with zero cash --}}
                <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 {{ $d['zero_balance_count'] > 0 ? 'text-amber-500' : 'text-emerald-500' }}" />
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Members with zero cash') }}</span>
                        </div>
                        <span class="text-sm font-semibold {{ $d['zero_balance_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $d['zero_balance_count'] }}
                        </span>
                    </div>
                    <p class="mt-0.5 ml-5.5 text-xs text-gray-400">
                        @if($d['zero_balance_count'] > 0)
                            {{ __('May miss upcoming contribution cycle') }}
                        @else
                            {{ __('All active members have positive cash balance') }}
                        @endif
                    </p>
                </div>

                {{-- Loan exposure vs fund --}}
                <div class="pt-2 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('Loan exposure') }}</span>
                        <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">{{ __('SAR') }} {{ number_format($d['loan_outstanding'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ __('vs. Master Fund') }}</span>
                        <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ __('SAR') }} {{ number_format($d['master_fund'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
