<x-filament-panels::page>

    {{-- Always visible: summary + guidance --}}
    @livewire(\App\Filament\Admin\Widgets\BankingStatsWidget::class, [], key('banking-stats'))

    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 ring-1 ring-gray-200 dark:ring-gray-700 px-5 py-4 flex items-start gap-3 mt-2 mb-8">
        <div class="flex-shrink-0 mt-0.5">
            <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
        </div>
        <div class="space-y-1">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">How the import pipeline works</p>
            <ol class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-decimal list-inside">
                <li>Under <strong class="text-gray-700 dark:text-gray-300">Banks</strong>, use <strong class="text-gray-700 dark:text-gray-300">Banks</strong> to register institutions and <strong class="text-gray-700 dark:text-gray-300">Templates</strong> to map CSV statement columns and duplicate rules.</li>
                <li>Under <strong class="text-gray-700 dark:text-gray-300">SMS</strong>, open <strong class="text-gray-700 dark:text-gray-300">Templates</strong> for SMS parsing rules, then <strong class="text-gray-700 dark:text-gray-300">Transactions</strong> and <strong class="text-gray-700 dark:text-gray-300">History</strong> for parsed rows and import runs.</li>
                <li>Post cleared transactions to the ledger and reconcile contributions or loan repayments from each transaction view.</li>
            </ol>
        </div>
    </div>

    {{-- Main tabs: Banks | SMS (kept close to sub-tabs below) --}}
    <x-filament::tabs class="mb-1">
        <x-filament::tabs.item
            :active="$activeTab === 'banks'"
            icon="heroicon-o-building-office-2"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'banks')"
        >
            Banks
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'sms'"
            icon="heroicon-o-device-phone-mobile"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'sms')"
        >
            SMS
        </x-filament::tabs.item>
    </x-filament::tabs>

    <p class="mb-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400 max-w-3xl">
        @if ($activeTab === 'banks')
            <span class="font-medium text-gray-700 dark:text-gray-300">Bank CSV imports</span>
            — pick a subsection: register banks, define how each CSV is parsed, work individual statement lines, or review past import runs.
        @else
            <span class="font-medium text-gray-700 dark:text-gray-300">SMS imports</span>
            — pick a subsection: parsing templates for exports, parsed transaction rows, or history of each SMS batch import.
        @endif
    </p>

    @if ($activeTab === 'banks')
        {{-- Sub-tabs: Banks | Templates | Transactions | History --}}
        <x-filament::tabs class="mb-4 mt-0">
            <x-filament::tabs.item
                :active="$banksSubTab === 'banks'"
                icon="heroicon-o-building-office-2"
                tag="button"
                type="button"
                wire:click="$set('banksSubTab', 'banks')"
            >
                Banks
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$banksSubTab === 'templates'"
                icon="heroicon-o-document-text"
                tag="button"
                type="button"
                wire:click="$set('banksSubTab', 'templates')"
            >
                Templates
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$banksSubTab === 'transactions'"
                icon="heroicon-o-arrows-right-left"
                tag="button"
                type="button"
                wire:click="$set('banksSubTab', 'transactions')"
            >
                Transactions
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$banksSubTab === 'history'"
                icon="heroicon-o-clock"
                tag="button"
                type="button"
                wire:click="$set('banksSubTab', 'history')"
            >
                History
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if ($banksSubTab === 'banks')
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Register each bank or financial institution you import from. CSV templates and import sessions are linked to these records.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\BanksTableWidget::class, [], key('banking-banks-table'))
        @elseif ($banksSubTab === 'templates')
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    CSV import templates: column mapping, date and amount structure, and duplicate-detection rules per bank.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\BankImportTemplatesTableWidget::class, [], key('banking-bank-templates-table'))
        @elseif ($banksSubTab === 'transactions')
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rows imported from bank CSV files. Open a record to review, match members, and post to the ledger.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\BankTransactionsTableWidget::class, [], key('banking-bank-tx-table'))
        @else
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Each row is one CSV import run: filename, counts, status, and a drill-down into imported transactions.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\BankImportSessionsTableWidget::class, [], key('banking-bank-import-history-table'))
        @endif

    @else
        {{-- SMS: sub-tabs Templates | Transactions | History --}}
        <x-filament::tabs class="mb-4 mt-0">
            <x-filament::tabs.item
                :active="$smsSubTab === 'templates'"
                icon="heroicon-o-chat-bubble-bottom-center-text"
                tag="button"
                type="button"
                wire:click="$set('smsSubTab', 'templates')"
            >
                Templates
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$smsSubTab === 'transactions'"
                icon="heroicon-o-arrows-right-left"
                tag="button"
                type="button"
                wire:click="$set('smsSubTab', 'transactions')"
            >
                Transactions
            </x-filament::tabs.item>
            <x-filament::tabs.item
                :active="$smsSubTab === 'history'"
                icon="heroicon-o-clock"
                tag="button"
                type="button"
                wire:click="$set('smsSubTab', 'history')"
            >
                History
            </x-filament::tabs.item>
        </x-filament::tabs>

        @if ($smsSubTab === 'templates')
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    SMS import templates: regex patterns for amounts, dates, references, member matching, and duplicate rules.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\SmsImportTemplatesTableWidget::class, [], key('banking-sms-templates-table'))
        @elseif ($smsSubTab === 'transactions')
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Parsed SMS export rows. Open a record to review, match members, and post to the ledger.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\SmsTransactionsTableWidget::class, [], key('banking-sms-tx-table'))
        @else
            <div class="mb-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Each row is one SMS batch import: filename, counts, status, and related messages.
                </p>
            </div>
            @livewire(\App\Filament\Admin\Widgets\SmsImportSessionsTableWidget::class, [], key('banking-sms-import-history-table'))
        @endif
    @endif

</x-filament-panels::page>
