@php $d = $d ?? $this->getData(); @endphp

@if(!($d['hasRecord'] ?? false))
    <div class="p-4 text-gray-400 text-sm">{{ __('No member selected.') }}</div>
@else
<div class="space-y-4">

    {{-- ── KPI row ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">

        {{-- Cash Balance --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1
            {{ $d['cash_balance'] >= 1000 ? 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700'
               : ($d['cash_balance'] > 0 ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700'
               : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700') }}">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                    {{ $d['cash_balance'] >= 1000 ? 'bg-emerald-500' : ($d['cash_balance'] > 0 ? 'bg-amber-500' : 'bg-red-500') }}">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Cash') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                {{ __('SAR :amount', ['amount' => number_format($d['cash_balance'], 2)]) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Member cash account') }}</p>
        </div>

        {{-- Fund Balance --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1
            {{ $d['fund_balance'] >= $d['min_fund'] ? 'bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700'
               : ($d['fund_balance'] > 0 ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700'
               : 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700') }}">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                    {{ $d['fund_balance'] >= $d['min_fund'] ? 'bg-blue-500' : ($d['fund_balance'] > 0 ? 'bg-amber-500' : 'bg-red-500') }}">
                    <x-heroicon-o-building-library class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Fund') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                {{ __('SAR :amount', ['amount' => number_format($d['fund_balance'], 2)]) }}
            </p>
            <div class="mt-1">
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="h-1.5 rounded-full {{ $d['fund_pct'] >= 100 ? 'bg-blue-500' : 'bg-amber-400' }}"
                         style="width: {{ $d['fund_pct'] }}%"></div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __(':pct% of SAR :amount min', ['pct' => $d['fund_pct'], 'amount' => number_format($d['min_fund'])]) }}</p>
            </div>
        </div>

        {{-- Net Worth --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-indigo-500">
                    <x-heroicon-o-scale class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Net Worth') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                {{ __('SAR :amount', ['amount' => number_format($d['net_worth'], 2)]) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $d['eligible'] ? __('✅ Loan eligible') : __('⏳ Not yet loan eligible') }}
            </p>
        </div>

        {{-- Max Borrowable --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1 bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-700">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-purple-500">
                    <x-heroicon-o-credit-card class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Max Loan') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                {{ __('SAR :amount', ['amount' => number_format($d['max_borrow'], 2)]) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if($d['active_loans_count'] > 0)
                    {{ __(':count active loan(s) · SAR :amount outstanding', ['count' => $d['active_loans_count'], 'amount' => number_format($d['outstanding_amt'], 2)]) }}
                @else
                    {{ __('No active loans') }}
                @endif
            </p>
        </div>

        {{-- Contributions --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1
            {{ $d['late_count'] > 0 ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700'
               : 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700' }}">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center {{ $d['late_count'] > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}">
                    <x-heroicon-o-currency-dollar class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Contributions') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                {{ __('SAR :amount', ['amount' => number_format($d['total_contributions'], 2)]) }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __(':count paid', ['count' => $d['contrib_count']]) }}
                @if($d['late_count'] > 0) · <span class="text-amber-600 dark:text-amber-400">{{ __(':count late', ['count' => $d['late_count']]) }}</span>@endif
            </p>
        </div>

        {{-- Repayments --}}
        <div class="col-span-1 rounded-xl p-4 flex flex-col gap-1
            {{ $d['overdue_installments'] > 0 ? 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700'
               : ($d['late_repay_count'] > 0 ? 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700'
               : 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700') }}">
            <div class="flex items-center gap-2 mb-1">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center
                    {{ $d['overdue_installments'] > 0 ? 'bg-red-500' : ($d['late_repay_count'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-white" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Repayments') }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white leading-tight">
                @if($d['overdue_installments'] > 0)
                    {{ __(':count overdue', ['count' => $d['overdue_installments']]) }}
                @elseif($d['late_repay_count'] > 0)
                    {{ __(':count late', ['count' => $d['late_repay_count']]) }}
                @else
                    {{ __('On track') }}
                @endif
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if($d['late_repay_amount'] > 0)
                    {{ __('SAR :amount late amt', ['amount' => number_format($d['late_repay_amount'], 2)]) }}
                @else
                    {{ __('No late repayments') }}
                @endif
            </p>
        </div>

    </div>

    {{-- ── Month / Next installment notice ─────────────────────────────── --}}
    @if($d['next_installment'] || !$d['paid_this_month'])
    <div class="flex flex-wrap gap-3">
        @if(!$d['paid_this_month'])
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-600 text-amber-800 dark:text-amber-200 text-sm">
            <x-heroicon-o-exclamation-circle class="w-4 h-4 flex-shrink-0" />
            {{ __('Contribution for :month not yet paid', ['month' => now()->format('F Y')]) }}
        </div>
        @endif
        @if($d['next_installment'])
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-100 dark:bg-blue-900/40 border border-blue-300 dark:border-blue-600 text-blue-800 dark:text-blue-200 text-sm">
            <x-heroicon-o-clock class="w-4 h-4 flex-shrink-0" />
            {{ __('Next loan installment due :date', ['date' => \Carbon\Carbon::parse($d['next_installment']->due_date)->format('d M Y')]) }}
            · {{ __('SAR :amount', ['amount' => number_format((float)$d['next_installment']->amount, 2)]) }}
        </div>
        @endif
    </div>
    @endif

</div>
@endif
