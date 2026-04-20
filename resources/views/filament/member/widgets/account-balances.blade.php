@php $d = $this->getData(); @endphp

@if(!$d['hasMember'])
<div class="rounded-2xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800 px-6 py-5 text-red-700 dark:text-red-300">
    {{ __('No member record found.') }}
</div>
@else
<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-wallet class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Account Balances') }}</h3>
        </div>
        <span class="text-xs text-gray-400">{{ __('Total:') }} {{ __('SAR') }} {{ number_format($d['cash'] + $d['fund'], 0) }}</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">

        {{-- Left column: Cash + Fund --}}
        <div class="divide-y divide-gray-100 dark:divide-gray-700">

            {{-- Cash Balance --}}
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $d['cash_covers'] ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-red-100 dark:bg-red-900/40' }}">
                            <x-heroicon-o-banknotes class="w-4 h-4 {{ $d['cash_covers'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Cash Balance') }}</p>
                            <p class="text-xs text-gray-400">{{ __('Available cash') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $d['cash_covers'] ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                                             : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300' }}">
                        {{ $d['cash_covers'] ? __('✓ Sufficient') : __('⚠ Low') }}
                    </span>
                </div>
                <p class="text-2xl font-bold {{ $d['cash_covers'] ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                    {{ __('SAR') }} {{ number_format($d['cash'], 2) }}
                </p>
                <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full {{ $d['cash_covers'] ? 'bg-emerald-500' : 'bg-red-500' }} transition-all"
                         style="width: {{ $d['cash_pct'] }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">
                    {{ $d['cash_covers'] ? __('Covers') : __('⚠ Insufficient for') }} {{ $d['next_due_label'] }} ({{ __('SAR') }} {{ number_format($d['next_due'], 0) }})
                </p>
            </div>

            {{-- Fund Balance --}}
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl
                            {{ $d['fund'] >= $d['min_fund'] ? 'bg-emerald-100 dark:bg-emerald-900/40'
                             : ($d['fund'] >= $d['min_fund'] * 0.75 ? 'bg-amber-100 dark:bg-amber-900/40'
                                                                     : 'bg-red-100 dark:bg-red-900/40') }}">
                            <x-heroicon-o-building-library class="w-4 h-4
                                {{ $d['fund'] >= $d['min_fund'] ? 'text-emerald-600 dark:text-emerald-400'
                                 : ($d['fund'] >= $d['min_fund'] * 0.75 ? 'text-amber-600 dark:text-amber-400'
                                                                         : 'text-red-600 dark:text-red-400') }}" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Fund Balance') }}</p>
                            <p class="text-xs text-gray-400">{{ __('Savings fund') }}</p>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-gray-400">
                        {{ __(':pct% of min', ['pct' => $d['fund_pct']]) }}
                    </span>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('SAR') }} {{ number_format($d['fund'], 2) }}</p>
                <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full transition-all
                        {{ $d['fund'] >= $d['min_fund'] ? 'bg-emerald-500'
                         : ($d['fund'] >= $d['min_fund'] * 0.75 ? 'bg-amber-500' : 'bg-red-500') }}"
                         style="width: {{ $d['fund_pct'] }}%"></div>
                </div>
                <p class="mt-1.5 text-xs {{ $d['fund_to_go'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    @if($d['fund_to_go'] > 0)
                        {{ __(':currency :amount more to reach loan eligibility (:currency :min)', ['currency' => __('SAR'), 'amount' => number_format($d['fund_to_go'], 0), 'min' => number_format($d['min_fund'], 0)]) }}
                    @else
                        {{ __('Above loan eligibility threshold') }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Right column: Max Borrowable + Allocation --}}
        <div class="divide-y divide-gray-100 dark:divide-gray-700">

            {{-- Max Borrowable --}}
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $d['eligible'] ? 'bg-sky-100 dark:bg-sky-900/40' : 'bg-gray-100 dark:bg-gray-700' }}">
                            <x-heroicon-o-credit-card class="w-4 h-4 {{ $d['eligible'] ? 'text-sky-600 dark:text-sky-400' : 'text-gray-400' }}" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Max Borrowable') }}</p>
                            <p class="text-xs text-gray-400">{{ __('Loan eligibility') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $d['eligible'] ? 'bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300'
                                          : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                        {{ $d['eligible'] ? __('Eligible') : __('Not eligible') }}
                    </span>
                </div>
                <p class="text-2xl font-bold {{ $d['eligible'] ? 'text-sky-600 dark:text-sky-400' : 'text-gray-400' }}">
                    {{ $d['eligible'] ? __('SAR').' '.number_format($d['max_borrow'], 0) : '—' }}
                </p>
                <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full {{ $d['eligible'] ? 'bg-sky-500' : 'bg-gray-300 dark:bg-gray-600' }} transition-all"
                         style="width: {{ $d['eligible'] ? $d['fund_pct'] : min(100, $d['fund_pct']) }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">
                    @if($d['eligible'])
                        {{ __('Based on 2× your fund balance') }}
                    @else
                        {{ __('Eligible from :date', ['date' => $d['eligible_date']]) }}
                    @endif
                </p>
            </div>

            {{-- Monthly Allocation --}}
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/40">
                            <x-heroicon-o-calendar-days class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Monthly Allocation') }}</p>
                            <p class="text-xs text-gray-400">{{ __('Configured contribution') }}</p>
                        </div>
                    </div>
                </div>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ __('SAR') }} {{ number_format($d['monthly_alloc'], 0) }}</p>
                <div class="mt-3 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full bg-indigo-400 transition-all" style="width: {{ min(100, round($d['monthly_alloc'] / 3000 * 100)) }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">{{ __('Per month · :pct% of max (:currency 3,000)', ['pct' => round($d['monthly_alloc'] / 3000 * 100), 'currency' => __('SAR')]) }}</p>
            </div>
        </div>
    </div>
</div>
@endif
