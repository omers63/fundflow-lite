<div>
{{-- ═══════════════════════════════════════════════════════════════════════
     QUICK-POST ACTION CARD
══════════════════════════════════════════════════════════════════════════ --}}
<div class="rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 shadow-xl ring-1 ring-indigo-800 overflow-hidden">
    <div class="px-6 py-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">

        {{-- Title & subtitle --}}
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-white/10 ring-2 ring-white/20">
                <x-heroicon-o-bolt class="w-8 h-8 text-white" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-white">{{ __('Quick Post') }}</h3>
                <p class="mt-0.5 text-sm text-indigo-200">{{ __('Create a transaction and run the full accounting workflow in one step') }}</p>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex flex-wrap gap-3 sm:flex-shrink-0">
            <button
                wire:click="openModal('bank')"
                class="inline-flex items-center gap-2 rounded-xl bg-white/15 hover:bg-white/25 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 hover:ring-white/40 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-white/50"
            >
                <x-heroicon-o-building-office-2 class="w-4 h-4" />
                {{ __('Bank Transaction') }}
            </button>
            <button
                wire:click="openModal('sms')"
                class="inline-flex items-center gap-2 rounded-xl bg-white/15 hover:bg-white/25 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 hover:ring-white/40 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-white/50"
            >
                <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4" />
                {{ __('SMS Transaction') }}
            </button>
        </div>
    </div>

    {{-- Workflow steps legend --}}
    <div class="px-6 pb-5">
        <div class="flex flex-wrap gap-x-6 gap-y-1.5">
            @foreach([
                ['icon' => 'heroicon-o-plus-circle',          'label' => __('Create transaction')],
                ['icon' => 'heroicon-o-building-library',      'label' => __('Post to Master Bank')],
                ['icon' => 'heroicon-o-user-circle',           'label' => __('Credit member cash')],
                ['icon' => 'heroicon-o-users',                 'label' => __('Allocate dependents')],
                ['icon' => 'heroicon-o-banknotes',             'label' => __('Settle contributions')],
                ['icon' => 'heroicon-o-credit-card',           'label' => __('Settle installments')],
            ] as $step)
            <div class="flex items-center gap-1.5 text-xs text-indigo-200">
                <x-dynamic-component :component="$step['icon']" class="w-3.5 h-3.5 text-indigo-300" />
                <span>{{ $step['label'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     MODAL OVERLAY
══════════════════════════════════════════════════════════════════════════ --}}
@if($showModal)
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-data
    x-transition.opacity
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-xl max-h-screen overflow-y-auto rounded-2xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-t-2xl">
            <div class="flex items-center gap-3">
                <x-heroicon-o-bolt class="w-5 h-5 text-white" />
                <h2 class="text-base font-bold text-white">
                    {{ __('Quick Post') }} — {{ $txType === 'bank' ? __('Bank Transaction') : __('SMS Transaction') }}
                </h2>
            </div>
            <button
                wire:click="closeModal"
                class="rounded-lg p-1.5 text-white/70 hover:text-white hover:bg-white/10 transition-colors"
            >
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        <div class="px-6 py-5">

            @if(!$showResult)
            {{-- ─── FORM ─────────────────────────────────────────────────── --}}
            <form wire:submit.prevent="submit" class="space-y-4">

                @if($txType === 'bank')
                {{-- Bank selector --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Bank') }} <span class="text-red-500">*</span></label>
                    <select wire:model.live="bankId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                        <option value="">{{ __('— Select bank —') }}</option>
                        @foreach($this->banks as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('bankId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                @else
                {{-- Bank optional for SMS --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Bank (optional)') }}</label>
                    <select wire:model.live="bankId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach($this->banks as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Member --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Member') }} <span class="text-red-500">*</span></label>
                    <select wire:model.live="memberId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                        <option value="">{{ __('— Select member —') }}</option>
                        @foreach($this->members as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('memberId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Date + type row --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Transaction Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" wire:model.live="transactionDate" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30" />
                        @error('transactionDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Type') }} <span class="text-red-500">*</span></label>
                        <select wire:model.live="transactionType" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30">
                            <option value="credit">{{ __('Credit (Deposit)') }}</option>
                            <option value="debit">{{ __('Debit (Disbursement)') }}</option>
                        </select>
                    </div>
                </div>

                {{-- Amount --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Amount (SAR)') }} <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">{{ __('SAR') }}</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="amount"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 pl-12 pr-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                               placeholder="0.00" />
                    </div>
                    @error('amount') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Reference --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Reference') }}</label>
                    <input type="text" wire:model.live="reference"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                           placeholder="{{ __('Transaction reference…') }}" />
                </div>

                @if($txType === 'bank')
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Description') }}</label>
                    <textarea wire:model.live="description" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                              placeholder="{{ __('Optional description…') }}"></textarea>
                </div>
                @else
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ __('Raw SMS Text') }}</label>
                    <textarea wire:model.live="rawSms" rows="3"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30"
                              placeholder="{{ __('Paste the raw SMS text here…') }}"></textarea>
                </div>
                @endif

                {{-- Workflow preview --}}
                <div class="rounded-xl bg-indigo-50 dark:bg-indigo-900/20 ring-1 ring-indigo-200 dark:ring-indigo-800 px-4 py-3">
                    <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-2">{{ __('Workflow to be executed:') }}</p>
                    <ol class="space-y-1">
                        @foreach([
                            __('Create :type transaction', ['type' => $txType === 'bank' ? __('bank') : __('SMS')]),
                            __('Post to Master Cash Account'),
                            $transactionType === 'credit' ? __('Credit member\'s Cash Account') : __('— Stop (debit = no further action)'),
                            $transactionType === 'credit' ? __('Allocate dependents\' cash accounts') : null,
                            $transactionType === 'credit' ? __('Settle contributions (member + dependents)') : null,
                            $transactionType === 'credit' ? __('Settle loan installments (member + dependents)') : null,
                        ] as $i => $step)
                        @if($step !== null)
                        <li class="flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400">
                            <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-indigo-200 dark:bg-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-bold">{{ $i + 1 }}</span>
                            {{ $step }}
                        </li>
                        @endif
                        @endforeach
                    </ol>
                </div>

                {{-- Submit --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeModal"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <span wire:loading.remove wire:target="submit">
                            <x-heroicon-o-bolt class="w-4 h-4 inline" />
                            {{ __('Run Workflow') }}
                        </span>
                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('Processing…') }}
                        </span>
                    </button>
                </div>
            </form>

            @else
            {{-- ─── RESULT ───────────────────────────────────────────────── --}}
            @if($resultError)
            <div class="rounded-xl bg-red-50 dark:bg-red-900/20 ring-1 ring-red-200 dark:ring-red-800 px-5 py-4 mb-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-semibold text-red-800 dark:text-red-200">{{ __('Workflow failed') }}</p>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-300">{{ $resultErrorMessage }}</p>
                    </div>
                </div>
            </div>
            @else
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 ring-1 ring-emerald-200 dark:ring-emerald-800 px-5 py-4 mb-4">
                <div class="flex items-center gap-3 mb-3">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                        {{ __('Workflow completed — Transaction #:id', ['id' => $resultTxId]) }}
                    </p>
                </div>
                <ol class="space-y-2">
                    @foreach($resultSteps as $i => $step)
                    <li class="flex items-start gap-3">
                        <span class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-emerald-200 dark:bg-emerald-800' : 'bg-gray-200 dark:bg-gray-600' }}">
                            @if($step['done'])
                                <x-heroicon-o-check class="w-3 h-3 text-emerald-700 dark:text-emerald-300" />
                            @else
                                <x-heroicon-o-clock class="w-3 h-3 text-gray-500" />
                            @endif
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $step['label'] }}</p>
                            @if($step['note'])
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $step['note'] }}</p>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ol>
            </div>
            @endif

            <div class="flex gap-3 justify-end">
                <button wire:click="$set('showResult', false)"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    {{ __('New Transaction') }}
                </button>
                <button wire:click="closeModal"
                        class="px-4 py-2 text-sm font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                    {{ __('Close') }}
                </button>
            </div>
            @endif

        </div>
    </div>
</div>
@endif
</div>
