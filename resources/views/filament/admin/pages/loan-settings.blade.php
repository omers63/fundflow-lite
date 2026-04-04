<x-filament-panels::page>
    {{-- ── Tabs ───────────────────────────────────────────────────────────── --}}
    <x-filament::tabs class="mb-6">
        <x-filament::tabs.item
            :active="$activeTab === 'settings'"
            icon="heroicon-o-adjustments-horizontal"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'settings')"
        >
            Loan rules
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'loan-tiers'"
            icon="heroicon-o-squares-2x2"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'loan-tiers')"
        >
            Loan tiers
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'fund-tiers'"
            icon="heroicon-o-chart-pie"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'fund-tiers')"
        >
            Fund tiers
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($activeTab === 'settings')
        @php
            $settings = [
                [
                    'key'         => 'eligibility_months',
                    'label'       => 'Eligibility Period',
                    'value'       => \App\Models\Setting::loanEligibilityMonths() . ' months',
                    'description' => 'Minimum membership duration before applying for a loan',
                    'icon'        => 'heroicon-o-calendar-days',
                    'color'       => 'text-primary-600 dark:text-primary-400',
                    'accent'      => 'bg-primary-500',
                ],
                [
                    'key'         => 'min_fund_balance',
                    'label'       => 'Min Fund Balance',
                    'value'       => 'SAR ' . number_format(\App\Models\Setting::loanMinFundBalance()),
                    'description' => 'Minimum fund account balance required for eligibility',
                    'icon'        => 'heroicon-o-building-library',
                    'color'       => 'text-teal-600 dark:text-teal-400',
                    'accent'      => 'bg-teal-500',
                ],
                [
                    'key'         => 'max_borrow_multiplier',
                    'label'       => 'Max Borrow Multiplier',
                    'value'       => \App\Models\Setting::loanMaxBorrowMultiplier() . '× Fund Balance',
                    'description' => 'Maximum loan amount as a multiple of the member\'s fund balance',
                    'icon'        => 'heroicon-o-arrows-up-down',
                    'color'       => 'text-indigo-600 dark:text-indigo-400',
                    'accent'      => 'bg-indigo-500',
                ],
                [
                    'key'         => 'settlement_threshold',
                    'label'       => 'Settlement Threshold',
                    'value'       => (\App\Models\Setting::loanSettlementThreshold() * 100) . '% of Loan',
                    'description' => 'Fund balance required relative to loan amount to trigger early settlement',
                    'icon'        => 'heroicon-o-scale',
                    'color'       => 'text-amber-600 dark:text-amber-400',
                    'accent'      => 'bg-amber-500',
                ],
                [
                    'key'         => 'default_grace_cycles',
                    'label'       => 'Grace Cycles',
                    'value'       => \App\Models\Setting::loanDefaultGraceCycles() . ' cycles',
                    'description' => 'Missed repayment cycles before the guarantor is held liable',
                    'icon'        => 'heroicon-o-shield-exclamation',
                    'color'       => 'text-rose-600 dark:text-rose-400',
                    'accent'      => 'bg-rose-500',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-6">
            @foreach($settings as $s)
            <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm group">
                <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $s['accent'] }}"></div>
                <div class="pl-2">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                            <x-dynamic-component :component="$s['icon']" class="w-4 h-4 {{ $s['color'] }}" />
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $s['label'] }}</span>
                    </div>
                    <p class="text-xl font-bold {{ $s['color'] }}">{{ $s['value'] }}</p>
                    <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500 leading-relaxed">{{ $s['description'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 ring-1 ring-gray-200 dark:ring-gray-700 px-5 py-4 flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Modify loan rules</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Use the <strong class="text-gray-700 dark:text-gray-300">Save settings</strong> action in the header to update these values.
                    Changes apply to new loan applications and eligibility checks.
                </p>
            </div>
        </div>
    @elseif ($activeTab === 'loan-tiers')
        <div class="space-y-2">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Amount bands and minimum installments for borrower-facing loan tiers. Create and edit rows here; use drag handles to reorder tier numbers.
            </p>
            @livewire(\App\Filament\Admin\Widgets\LoanTiersTableWidget::class, [], key('loan-tiers-table'))
        </div>
    @else
        <div class="space-y-2">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                How the master fund is split across queues (including emergency). Linked loan tiers drive which queue a standard loan joins.
            </p>
            @livewire(\App\Filament\Admin\Widgets\FundTiersTableWidget::class, [], key('fund-tiers-table'))
        </div>
    @endif
</x-filament-panels::page>
