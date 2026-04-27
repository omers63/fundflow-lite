@php $d = $this->getData(); @endphp

@if(!$d['hasMember'])
    {{-- No member record, nothing to show --}}
@else

@php
    $score = $d['complianceScore'];
    $scoreColor = $score >= 90 ? 'emerald' : ($score >= 70 ? 'amber' : 'red');
    $scoreLabel = $score >= 90 ? __('Excellent') : ($score >= 70 ? __('Fair') : __('Poor'));
@endphp

{{-- ── Delinquency / Suspension alert ─────────────────────────────────── --}}
@if($d['isDelinquent'])
<div class="rounded-xl bg-red-50 dark:bg-red-950/30 ring-2 ring-red-300 dark:ring-red-700 p-5 mb-4">
    <div class="flex items-start gap-3">
        <x-heroicon-o-exclamation-circle class="w-6 h-6 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
        <div>
            <p class="text-sm font-bold text-red-800 dark:text-red-300">{{ __('Your account is flagged as Delinquent') }}</p>
            <p class="text-sm text-red-700 dark:text-red-400 mt-1">
                {{ __('Delinquency means you have missed multiple contribution or repayment obligations. You are currently ineligible to apply for a new loan. Please contact the fund administrators immediately.') }}
            </p>
            @if($d['isSuspended'])
            <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-semibold">
                {{ __('Account suspended on:') }} {{ $d['suspendedAt']?->locale(app()->getLocale())->translatedFormat('d F Y') }}
            </p>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ── Status grid ─────────────────────────────────────────────────────── --}}
<div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
        <div class="flex items-center gap-2">
            <x-heroicon-o-shield-check class="w-5 h-5 text-gray-400" />
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ __('Compliance & Standing') }}</span>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
            {{ $d['isDelinquent'] ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' :
               ($d['lateContribCount'] > 0 || $d['lateRepayCount'] > 0 ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
               'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300') }}">
            {{ $d['isDelinquent'] ? __('Delinquent') : ($d['lateContribCount'] > 0 || $d['lateRepayCount'] > 0 ? __('Attention Needed') : __('Good Standing')) }}
        </span>
    </div>

    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Compliance score --}}
        <div class="flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="relative w-20 h-20 mb-2">
                <svg class="w-20 h-20 -rotate-90" viewBox="0 0 36 36">
                    <path class="text-gray-200 dark:text-gray-600" stroke="currentColor" stroke-width="3" fill="none"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                    <path class="text-{{ $scoreColor }}-500" stroke="currentColor" stroke-width="3" fill="none"
                        stroke-dasharray="{{ $score }}, 100"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-lg font-bold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">{{ $score }}%</span>
                </div>
            </div>
            <p class="text-xs font-semibold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">{{ $scoreLabel }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 text-center">{{ __('Compliance Score') }}</p>
        </div>

        {{-- Contribution standing --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Contributions') }}</p>
                @if($d['lateContribCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-exclamation-triangle class="w-3 h-3" />
                        {{ __(':count late', ['count' => $d['lateContribCount']]) }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('On time') }}
                    </span>
                @endif
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $d['totalContrib'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('total recorded') }}</p>
            @if($d['lateContribCount'] > 0)
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">
                {{ __(':amount in late fees', ['amount' => \App\Support\UiNumber::sar($d['lateContribAmount'])]) }}
            </p>
            @endif
            @if($d['streak'] > 0)
            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                {{ __(':count month streak', ['count' => $d['streak']]) }}
            </p>
            @endif
        </div>

        {{-- Loan repayment standing --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Repayments') }}</p>
                @if($d['lateRepayCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                        {{ __(':count late', ['count' => $d['lateRepayCount']]) }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        {{ __('On time') }}
                    </span>
                @endif
            </div>
            @if($d['lateRepayCount'] > 0)
                <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $d['lateRepayCount'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('late installments recorded') }}</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-2">
                    {{ __(':amount in late fees', ['amount' => \App\Support\UiNumber::sar($d['lateRepayAmount'])]) }}
                </p>
            @else
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">0</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('late repayments') }}</p>
            @endif
        </div>

        {{-- Overdue installments --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Overdue Now') }}</p>
                @if($d['overdueCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                        {{ __('Action needed') }}
                    </span>
                @endif
            </div>
            @if($d['overdueCount'] > 0)
                <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $d['overdueCount'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $d['overdueCount'] > 1 ? __('overdue installments') : __('overdue installment') }}</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ __(':amount owed', ['amount' => \App\Support\UiNumber::sar($d['overdueAmount'])]) }}</p>
                @if($d['overdueLateFees'] > 0)
                <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">{{ __('+ SAR :amount late fees', ['amount' => number_format($d['overdueLateFees'], 2)]) }}</p>
                @endif
            @else
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ __('None') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('All installments current') }}</p>
            @endif
        </div>

    </div>
</div>

@endif
