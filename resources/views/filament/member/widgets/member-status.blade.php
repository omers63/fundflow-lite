@php $d = $this->getData(); @endphp

@if(!$d['hasMember'])
    {{-- No member record, nothing to show --}}
@else

@php
    $score = $d['complianceScore'];
    $scoreColor = $score >= 90 ? 'emerald' : ($score >= 70 ? 'amber' : 'red');
    $scoreLabel = $score >= 90 ? 'Excellent' : ($score >= 70 ? 'Fair' : 'Poor');
@endphp

{{-- ── Delinquency / Suspension alert ─────────────────────────────────── --}}
@if($d['isDelinquent'])
<div class="rounded-xl bg-red-50 dark:bg-red-950/30 ring-2 ring-red-300 dark:ring-red-700 p-5 mb-4">
    <div class="flex items-start gap-3">
        <x-heroicon-o-exclamation-circle class="w-6 h-6 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
        <div>
            <p class="text-sm font-bold text-red-800 dark:text-red-300">Your account is flagged as Delinquent</p>
            <p class="text-sm text-red-700 dark:text-red-400 mt-1">
                Delinquency means you have missed multiple contribution or repayment obligations.
                You are currently ineligible to apply for a new loan. Please contact the fund administrators immediately.
            </p>
            @if($d['isSuspended'])
            <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-semibold">
                Account suspended on: {{ $d['suspendedAt']?->format('d F Y') }}
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
            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Compliance & Standing</span>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
            {{ $d['isDelinquent'] ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' :
               ($d['lateContribCount'] > 0 || $d['lateRepayCount'] > 0 ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
               'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300') }}">
            {{ $d['isDelinquent'] ? 'Delinquent' : ($d['lateContribCount'] > 0 || $d['lateRepayCount'] > 0 ? 'Attention Needed' : 'Good Standing') }}
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
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 text-center">Compliance Score</p>
        </div>

        {{-- Contribution standing --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contributions</p>
                @if($d['lateContribCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-exclamation-triangle class="w-3 h-3" />
                        {{ $d['lateContribCount'] }} late
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        On time
                    </span>
                @endif
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $d['totalContrib'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">total recorded</p>
            @if($d['lateContribCount'] > 0)
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">
                SAR {{ number_format($d['lateContribAmount'], 2) }} in late fees
            </p>
            @endif
            @if($d['streak'] > 0)
            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                {{ $d['streak'] }} month streak
            </p>
            @endif
        </div>

        {{-- Loan repayment standing --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Repayments</p>
                @if($d['lateRepayCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                        {{ $d['lateRepayCount'] }} late
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" />
                        On time
                    </span>
                @endif
            </div>
            @if($d['lateRepayCount'] > 0)
                <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $d['lateRepayCount'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">late installments recorded</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-2">
                    SAR {{ number_format($d['lateRepayAmount'], 2) }} in late fees
                </p>
            @else
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">0</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">late repayments</p>
            @endif
        </div>

        {{-- Overdue installments --}}
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Overdue Now</p>
                @if($d['overdueCount'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                        Action needed
                    </span>
                @endif
            </div>
            @if($d['overdueCount'] > 0)
                <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $d['overdueCount'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">overdue installment{{ $d['overdueCount'] > 1 ? 's' : '' }}</p>
                <p class="text-xs text-red-600 dark:text-red-400 mt-1">SAR {{ number_format($d['overdueAmount'], 2) }} owed</p>
                @if($d['overdueLateFees'] > 0)
                <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">+ SAR {{ number_format($d['overdueLateFees'], 2) }} late fees</p>
                @endif
            @else
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">None</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">All installments current</p>
            @endif
        </div>

    </div>
</div>

@endif
