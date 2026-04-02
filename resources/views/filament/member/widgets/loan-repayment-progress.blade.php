@php $loans = $this->getLoans(); @endphp

@if($loans->isNotEmpty())
<div class="fi-wi-stats-overview rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6 bg-white dark:bg-gray-900">

    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
            Active Loan Repayment Progress
        </h3>
        <span class="text-xs text-gray-500">{{ $loans->count() }} active loan{{ $loans->count() > 1 ? 's' : '' }}</span>
    </div>

    @foreach($loans as $item)
    @php $loan = $item['loan']; @endphp

    <div class="@if(!$loop->last) mb-8 pb-8 border-b border-gray-200 dark:border-gray-700 @endif">

        {{-- Loan header --}}
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    Loan #{{ $loan->id }}
                    @if($loan->loanTier) <span class="ml-1 text-xs text-gray-500">({{ $loan->loanTier->label }})</span> @endif
                </span>
                <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">
                    SAR {{ number_format($loan->amount_approved, 2) }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                @if($item['is_ready_to_settle'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                        <x-heroicon-o-check-badge class="w-3.5 h-3.5" /> Ready to Settle
                    </span>
                @endif
                @if($item['guarantor_released'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        <x-heroicon-o-shield-check class="w-3.5 h-3.5" /> Guarantor Released
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">

            {{-- 1. Installment Progress --}}
            <div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>Installments Paid</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ $item['paid_installments'] }} / {{ $item['total_installments'] }}
                    </span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-700 h-2.5">
                    <div class="h-2.5 rounded-full bg-emerald-500 transition-all"
                         style="width: {{ $item['paid_percent'] }}%"></div>
                </div>
                <div class="mt-1 text-xs text-gray-400">{{ $item['paid_percent'] }}% complete</div>
                @if($item['next_installment'])
                <div class="mt-2 text-xs font-medium text-primary-600 dark:text-primary-400">
                    Next: SAR {{ number_format($item['next_installment']->amount, 2) }}
                    due {{ $item['next_installment']->due_date->format('d M Y') }}
                </div>
                @endif
            </div>

            {{-- 2. Master Fund Repayment (Guarantor Release) --}}
            <div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>Fund Portion Repaid</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        SAR {{ number_format($item['repaid_to_master'], 2) }} / {{ number_format($item['master_portion'], 2) }}
                    </span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-700 h-2.5">
                    <div class="h-2.5 rounded-full {{ $item['guarantor_released'] ? 'bg-blue-500' : 'bg-indigo-500' }} transition-all"
                         style="width: {{ $item['master_percent'] }}%"></div>
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ $item['master_percent'] }}%
                    @if($item['guarantor_released'])
                        — <span class="text-blue-600 dark:text-blue-400">Guarantor released ✓</span>
                    @else
                        — guarantor holds until 100%
                    @endif
                </div>
            </div>

            {{-- 3. Fund Balance vs Settlement Threshold --}}
            <div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>Fund Balance vs Settlement</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        SAR {{ number_format($item['fund_balance'], 2) }} / {{ number_format($item['settle_required'], 2) }}
                    </span>
                </div>
                <div class="w-full rounded-full bg-gray-200 dark:bg-gray-700 h-2.5">
                    <div class="h-2.5 rounded-full {{ $item['fund_percent'] >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }} transition-all"
                         style="width: {{ $item['fund_percent'] }}%"></div>
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ $item['fund_percent'] }}%
                    — need {{ round($loan->settlement_threshold * 100) }}% of loan for settlement
                </div>
            </div>

        </div>

        {{-- Remaining amount + late count --}}
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Remaining balance: <strong class="text-gray-900 dark:text-white">SAR {{ number_format($item['remaining_amount'], 2) }}</strong></span>
            @if($loan->late_repayment_count > 0)
            <span class="text-amber-600 dark:text-amber-400">
                ⚠ {{ $loan->late_repayment_count }} late repayment(s) — SAR {{ number_format($loan->late_repayment_amount, 2) }}
            </span>
            @endif
        </div>

    </div>
    @endforeach

</div>
@endif
