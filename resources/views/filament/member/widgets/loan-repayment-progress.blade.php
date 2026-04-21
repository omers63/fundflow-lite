@php $loans = $this->getLoans(); @endphp

@if($loans->isNotEmpty())
<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Widget header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-credit-card class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Active Loan Repayment Progress') }}</h3>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full bg-primary-100 dark:bg-primary-900 px-2.5 py-1 text-xs font-medium text-primary-700 dark:text-primary-300">
            {{ __(':count active loan(s)', ['count' => $loans->count()]) }}
        </span>
    </div>

    @foreach($loans as $item)
    @php $loan = $item['loan']; @endphp

    <div class="px-6 py-5 @if(!$loop->last) border-b border-gray-100 dark:border-gray-700 @endif">

        {{-- Loan header row --}}
        <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-base font-semibold text-gray-900 dark:text-white">
                        Loan #{{ $loan->id }}
                    </span>
                    @if($loan->loanTier)
                    <span class="inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                        {{ $loan->loanTier->label }}
                    </span>
                    @endif
                    @if($item['is_ready_to_settle'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-badge class="w-3.5 h-3.5" /> {{ __('Ready to Settle') }}
                    </span>
                    @endif
                    @if($item['guarantor_released'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 dark:bg-blue-900 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                        <x-heroicon-o-shield-check class="w-3.5 h-3.5" /> {{ __('Guarantor Released') }}
                    </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('SAR :approved', ['approved' => number_format($loan->amount_approved, 2)]) }} &bull;
                    {{ __('SAR :remaining principal remaining on schedule', ['remaining' => number_format($item['remaining_amount'], 2)]) }}
                </p>
            </div>
            {{-- Circular-style overall progress badge --}}
            <div class="flex-shrink-0 text-center">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-full {{ $item['paid_percent'] >= 100 ? 'bg-emerald-100 dark:bg-emerald-900' : 'bg-primary-50 dark:bg-primary-900/40' }} ring-2 {{ $item['paid_percent'] >= 100 ? 'ring-emerald-300 dark:ring-emerald-700' : 'ring-primary-200 dark:ring-primary-800' }}">
                    <span class="text-sm font-bold {{ $item['paid_percent'] >= 100 ? 'text-emerald-700 dark:text-emerald-300' : 'text-primary-700 dark:text-primary-300' }}">{{ $item['paid_percent'] }}%</span>
                </div>
                <p class="mt-1 text-xs text-gray-400">{{ __('Paid') }}</p>
            </div>
        </div>

        {{-- Three progress metrics --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- 1. Installment Progress --}}
            <div class="rounded-xl bg-gray-50 dark:bg-gray-700/40 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900">
                        <x-heroicon-o-calendar-days class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Installments Paid') }}</span>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>{{ __(':paid of :total', ['paid' => $item['paid_installments'], 'total' => $item['total_installments']]) }}</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $item['paid_percent'] }}%</span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-600 h-2">
                    <div class="h-2 rounded-full bg-emerald-500 transition-all" style="width: {{ $item['paid_percent'] }}%"></div>
                </div>
                @if($item['next_installment'])
                <p class="mt-2.5 text-xs font-medium text-primary-600 dark:text-primary-400">
                    {{ __('Next: SAR :amount', ['amount' => number_format($item['next_installment']->amount, 2)]) }}
                    &bull; {{ $item['next_installment']->due_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
                </p>
                @else
                <p class="mt-2.5 text-xs text-gray-400">{{ __('No pending installments') }}</p>
                @endif
            </div>

            {{-- 2. Fund Portion / Guarantor Release --}}
            <div class="rounded-xl bg-gray-50 dark:bg-gray-700/40 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full {{ $item['guarantor_released'] ? 'bg-blue-100 dark:bg-blue-900' : 'bg-indigo-100 dark:bg-indigo-900' }}">
                        <x-heroicon-o-shield-check class="w-3.5 h-3.5 {{ $item['guarantor_released'] ? 'text-blue-600 dark:text-blue-400' : 'text-indigo-600 dark:text-indigo-400' }}" />
                    </div>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Fund Portion Repaid') }}</span>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>{{ __('SAR') }} {{ number_format($item['repaid_to_master'], 2) }}</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $item['master_percent'] }}%</span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-600 h-2">
                    <div class="h-2 rounded-full {{ $item['guarantor_released'] ? 'bg-blue-500' : 'bg-indigo-500' }} transition-all"
                         style="width: {{ $item['master_percent'] }}%"></div>
                </div>
                <p class="mt-2.5 text-xs text-gray-400">
                    @if($item['guarantor_released'])
                        <span class="text-blue-600 dark:text-blue-400 font-medium">{{ __('Guarantor released') }}</span>
                    @else
                        {{ __('Guarantor holds until 100% — SAR :amount target', ['amount' => number_format($item['master_portion'], 2)]) }}
                    @endif
                </p>
            </div>

            {{-- 3. Fund Balance vs Settlement Threshold --}}
            <div class="rounded-xl bg-gray-50 dark:bg-gray-700/40 p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full {{ $item['fund_percent'] >= 100 ? 'bg-emerald-100 dark:bg-emerald-900' : 'bg-amber-100 dark:bg-amber-900' }}">
                        <x-heroicon-o-scale class="w-3.5 h-3.5 {{ $item['fund_percent'] >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}" />
                    </div>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Settlement Readiness') }}</span>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                    <span>{{ __('SAR') }} {{ number_format($item['fund_balance'], 2) }}</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $item['fund_percent'] }}%</span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-600 h-2">
                    <div class="h-2 rounded-full {{ $item['fund_percent'] >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }} transition-all"
                         style="width: {{ $item['fund_percent'] }}%"></div>
                </div>
                <p class="mt-2.5 text-xs text-gray-400">
                    {{ __('Need SAR :amount', ['amount' => number_format($item['settle_required'], 2)]) }}
                    ({{ __(':percent% of loan', ['percent' => round($loan->settlement_threshold * 100)]) }})
                </p>
            </div>
        </div>

        {{-- Late repayment warning --}}
        @if($loan->late_repayment_count > 0)
        <div class="mt-4 flex items-center gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 ring-1 ring-amber-200 dark:ring-amber-800 px-4 py-2.5">
            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500 flex-shrink-0" />
            <p class="text-xs text-amber-700 dark:text-amber-300">
                <strong>{{ $loan->late_repayment_count }}</strong> {{ $loan->late_repayment_count > 1 ? __('late repayments') : __('late repayment') }}
                — {{ __('SAR') }} {{ number_format($loan->late_repayment_amount, 2) }} {{ __('total') }}
            </p>
        </div>
        @endif

        @if(($item['remaining_settlement_cash'] ?? 0) > 0)
        @php $shortfall = max(0, ($item['remaining_settlement_cash'] ?? 0) - ($item['cash_balance'] ?? 0)); @endphp
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-xl bg-slate-50 dark:bg-slate-800/60 ring-1 ring-slate-200 dark:ring-slate-600 px-4 py-3">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <span class="font-semibold text-gray-900 dark:text-white">{{ __('Pay off early:') }}</span>
                {{ __('SAR :amount total from your cash account', ['amount' => number_format($item['remaining_settlement_cash'], 2)]) }}
                {{ __('(scheduled installments plus late fees if any cycle is past due).') }}
                {{ __('Cash balance: SAR :amount.', ['amount' => number_format($item['cash_balance'] ?? 0, 2)]) }}
                @if(!$item['can_early_settle_cash'] && $shortfall > 0)
                <span class="block mt-1 text-amber-700 dark:text-amber-300">{{ __('Add SAR :amount to your cash account first (e.g. bank transfer posted to cash).', ['amount' => number_format($shortfall, 2)]) }}</span>
                @endif
            </div>
            @if($item['can_early_settle_cash'])
            <button
                type="button"
                wire:click="settleEarly({{ $loan->id }})"
                wire:confirm="{{ __('Debit your cash account for the full payoff amount and close this loan?') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                <x-heroicon-o-check-badge class="w-4 h-4" />
                {{ __('Pay off early') }}
            </button>
            @endif
        </div>
        @endif

    </div>
    @endforeach
</div>
@endif
