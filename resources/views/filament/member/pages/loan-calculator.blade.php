<x-filament-panels::page>

<div class="space-y-6">

    {{-- ── Intro card ─────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-200 dark:ring-primary-700 p-5">
        <div class="flex items-start gap-3">
            <x-heroicon-o-calculator class="w-6 h-6 text-primary-600 dark:text-primary-400 mt-0.5 flex-shrink-0" />
            <div>
                <p class="text-sm font-semibold text-primary-800 dark:text-primary-300">{{ __('Estimate your loan repayment') }}</p>
                <p class="text-sm text-primary-700 dark:text-primary-400 mt-1">
                    {{ __('Enter an amount to see how many monthly installments you would need to repay it.') }}
                    Calculations use your current fund balance ({{ __('SAR') }} {{ number_format($this->memberFundBalance, 2) }})
                    {{ __('and the active loan tier settings.') }}
                    {{ __('The :percent% settlement threshold is included.', ['percent' => round($this->settlementPct * 100)]) }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Amount input ────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl bg-gradient-to-br from-sky-100 via-white to-indigo-50 dark:from-slate-800 dark:via-sky-950/35 dark:to-indigo-950/30 ring-1 ring-sky-200/80 dark:ring-sky-600/40 p-5 shadow-md">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Loan Amount (SAR)') }}</label>
        <div class="flex gap-3 items-center">
            <input
                type="number"
                wire:model.live.debounce.400ms="loanAmount"
                min="0"
                step="500"
                placeholder="{{ __('e.g. 20000') }}"
                class="block w-full sm:w-64 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 text-base px-4 py-2.5"
            />
            @if($this->loanAmount > 0)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('SAR') }} {{ number_format($this->loanAmount, 2) }}
                </span>
            @endif
        </div>

        {{-- Quick-select tiers --}}
        @if($this->activeTiers->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($this->activeTiers as $tier)
            <button
                type="button"
                wire:click="$set('loanAmount', {{ (float) $tier->min_amount }})"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-primary-100 dark:hover:bg-primary-900 hover:text-primary-700 dark:hover:text-primary-300 transition-colors"
            >
                {{ $tier->label }} ({{ __('SAR') }} {{ number_format($tier->min_amount) }})
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── Results ─────────────────────────────────────────────────────────────── --}}
    @if($this->loanAmount > 0)
        @if(count($this->calculations) > 0)
            @foreach($this->calculations as $calc)
            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
                {{-- Header --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div>
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $calc['tier']->label }}</span>
                        <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">{{ $calc['tier']->range }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $calc['installments'] }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-1">{{ __('months') }}</span>
                    </div>
                </div>

                {{-- Body --}}
                <div class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Monthly Installment') }}</p>
                        <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                            {{ __('SAR') }} {{ number_format($calc['min_installment'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __('minimum') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Your Fund Portion') }}</p>
                        <p class="mt-1 text-base font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ __('SAR') }} {{ number_format($calc['member_portion'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __('from your fund account') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Fund Contribution') }}</p>
                        <p class="mt-1 text-base font-semibold text-amber-600 dark:text-amber-400">
                            {{ __('SAR') }} {{ number_format($calc['master_portion'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __('from master fund') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Settlement Amount') }}</p>
                        <p class="mt-1 text-base font-semibold text-gray-700 dark:text-gray-300">
                            {{ __('SAR') }} {{ number_format($calc['settlement_amt'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __(':percent% of loan', ['percent' => round($this->settlementPct * 100)]) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Total to Repay') }}</p>
                        <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                            {{ __('SAR') }} {{ number_format($calc['total_repay'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __('master portion + settlement') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Duration') }}</p>
                        <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                            {{ __('~:years years', ['years' => number_format($calc['installments'] / 12, 1)]) }}
                        </p>
                        <p class="text-xs text-gray-400">{{ __(':count monthly payments', ['count' => $calc['installments']]) }}</p>
                    </div>
                </div>

                {{-- Progress visual --}}
                @php
                    $memberPct = $this->loanAmount > 0 ? min(100, $calc['member_portion'] / $this->loanAmount * 100) : 0;
                    $masterPct = 100 - $memberPct;
                @endphp
                <div class="px-5 pb-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Loan funding split') }}</p>
                    <div class="w-full h-3 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden flex">
                        @if($memberPct > 0)
                        <div class="h-full bg-emerald-500 transition-all" style="width: {{ $memberPct }}%"></div>
                        @endif
                        @if($masterPct > 0)
                        <div class="h-full bg-amber-400 transition-all" style="width: {{ $masterPct }}%"></div>
                        @endif
                    </div>
                    <div class="flex gap-4 mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('Your fund (:percent%)', ['percent' => round($memberPct)]) }}</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span> {{ __('Master fund (:percent%)', ['percent' => round($masterPct)]) }}</span>
                    </div>
                </div>
            </div>
            @endforeach

            <p class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
                {{ __('* These are estimates based on current tier settings and your fund balance. Actual terms may vary upon approval.') }}
            </p>

        @else
            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-8 text-center shadow-sm">
                <x-heroicon-o-exclamation-triangle class="w-10 h-10 text-amber-400 mx-auto mb-3" />
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('No matching loan tier') }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ __(':currency :amount does not fall within any active loan tier range.', ['currency' => __('SAR'), 'amount' => number_format($this->loanAmount, 2)]) }}
                </p>
                @if($this->activeTiers->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2 justify-center">
                    @foreach($this->activeTiers as $tier)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                        {{ $tier->label }}: {{ $tier->range }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        @endif
    @else
        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-10 text-center shadow-sm">
            <x-heroicon-o-calculator class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Enter a loan amount above to see your repayment estimate.') }}</p>
        </div>
    @endif

</div>

</x-filament-panels::page>
