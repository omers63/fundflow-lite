<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-6 text-white shadow-md ring-1 ring-white/20 dark:from-primary-700 dark:to-primary-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-white/90">{{ __('Operations Hub') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">{{ __('Banking Command Center') }}</h2>
                    <p class="mt-2 max-w-3xl text-sm text-white/90">
                        {{ __('Unified visibility into imports, posting throughput, duplicate control, and debit-to-disbursement reconciliation.') }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-xl bg-white/10 px-3 py-2 font-medium text-white shadow-sm ring-1 ring-white/20 backdrop-blur-sm">{{ __('Pipeline health') }}</div>
                    <div class="rounded-xl bg-white/10 px-3 py-2 font-medium text-white shadow-sm ring-1 ring-white/20 backdrop-blur-sm">{{ __('Reconciliation focus') }}</div>
                </div>
            </div>
        </section>

        @livewire(\App\Filament\Admin\Widgets\BankingStatsWidget::class, [], 'banking-stats')
        @livewire(\App\Filament\Admin\Widgets\BankingHealthWidget::class, [], 'banking-health')
        @livewire(\App\Filament\Admin\Widgets\BankingActionCenterWidget::class, [], 'banking-action-center')

        <section class="rounded-2xl border border-gray-200 bg-white px-5 py-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Workspace') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Switch between Bank and SMS channels, then open templates, transactions, or import history.') }}
                    </p>
                </div>
            </div>

            <x-filament::tabs class="mb-2 justify-center">
                <x-filament::tabs.item :active="$activeTab === 'banks'" icon="heroicon-o-building-office-2" tag="button" type="button" wire:click="$set('activeTab', 'banks')">
                    {{ __('Banks') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$activeTab === 'sms'" icon="heroicon-o-device-phone-mobile" tag="button" type="button" wire:click="$set('activeTab', 'sms')">
                    {{ __('SMS') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            @if ($activeTab === 'banks')
                <x-filament::tabs class="mb-4 mt-0 justify-center">
                    <x-filament::tabs.item :active="$banksSubTab === 'banks'" icon="heroicon-o-building-office-2" tag="button" type="button" wire:click="$set('banksSubTab', 'banks')">
                        {{ __('Banks') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item :active="$banksSubTab === 'templates'" icon="heroicon-o-document-text" tag="button" type="button" wire:click="$set('banksSubTab', 'templates')">
                        {{ __('Templates') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item :active="$banksSubTab === 'transactions'" icon="heroicon-o-arrows-right-left" tag="button" type="button" wire:click="$set('banksSubTab', 'transactions')">
                        {{ __('Transactions') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item :active="$banksSubTab === 'history'" icon="heroicon-o-clock" tag="button" type="button" wire:click="$set('banksSubTab', 'history')">
                        {{ __('History') }}
                    </x-filament::tabs.item>
                </x-filament::tabs>

                @if ($banksSubTab === 'banks')
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Register financial institutions and manage integration metadata.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\BanksTableWidget::class, [], 'banking-banks-table')
                @elseif ($banksSubTab === 'templates')
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Define parsing rules, amount/date mapping, and duplicate detection logic.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\BankImportTemplatesTableWidget::class, [], 'banking-bank-templates-table')
                @elseif ($banksSubTab === 'transactions')
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Review statement rows, post to ledger accounts, and reconcile debit disbursements.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\BankTransactionsTableWidget::class, [], 'banking-bank-tx-table')
                @else
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Track import runs by status, filename, and data quality outcomes.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\BankImportSessionsTableWidget::class, [], 'banking-bank-import-history-table')
                @endif
            @else
                <x-filament::tabs class="mb-4 mt-0 justify-center">
                    <x-filament::tabs.item :active="$smsSubTab === 'templates'" icon="heroicon-o-chat-bubble-bottom-center-text" tag="button" type="button" wire:click="$set('smsSubTab', 'templates')">
                        {{ __('Templates') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item :active="$smsSubTab === 'transactions'" icon="heroicon-o-arrows-right-left" tag="button" type="button" wire:click="$set('smsSubTab', 'transactions')">
                        {{ __('Transactions') }}
                    </x-filament::tabs.item>
                    <x-filament::tabs.item :active="$smsSubTab === 'history'" icon="heroicon-o-clock" tag="button" type="button" wire:click="$set('smsSubTab', 'history')">
                        {{ __('History') }}
                    </x-filament::tabs.item>
                </x-filament::tabs>

                @if ($smsSubTab === 'templates')
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Maintain SMS parsing patterns and member matching rules for exports.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\SmsImportTemplatesTableWidget::class, [], 'banking-sms-templates-table')
                @elseif ($smsSubTab === 'transactions')
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Review parsed SMS transactions and post verified rows to accounts.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\SmsTransactionsTableWidget::class, [], 'banking-sms-tx-table')
                @else
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Monitor SMS import batches with counts, errors, and completion state.') }}</p>
                    @livewire(\App\Filament\Admin\Widgets\SmsImportSessionsTableWidget::class, [], 'banking-sms-import-history-table')
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
