<x-filament-panels::page>

    {{-- ── Tabs ───────────────────────────────────────────────────────────── --}}
    <x-filament::tabs class="mb-6">
        <x-filament::tabs.item
            :active="$activeTab === 'overview'"
            icon="heroicon-o-chart-bar-square"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'overview')"
        >
            Overview
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'bank-templates'"
            icon="heroicon-o-document-text"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'bank-templates')"
        >
            Bank CSV templates
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'sms-templates'"
            icon="heroicon-o-chat-bubble-left-right"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'sms-templates')"
        >
            SMS templates
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{-- ── Tab content ─────────────────────────────────────────────────────── --}}

    @if ($activeTab === 'overview')
        {{-- Stats widget --}}
        @livewire(\App\Filament\Admin\Widgets\BankingStatsWidget::class, [], key('banking-stats'))

        {{-- Help card --}}
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 ring-1 ring-gray-200 dark:ring-gray-700 px-5 py-4 flex items-start gap-3 mt-2">
            <div class="flex-shrink-0 mt-0.5">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
            </div>
            <div class="space-y-1">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">How the import pipeline works</p>
                <ol class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-decimal list-inside">
                    <li>Register a <strong class="text-gray-700 dark:text-gray-300">Bank</strong> and create a <strong class="text-gray-700 dark:text-gray-300">Bank CSV template</strong> or <strong class="text-gray-700 dark:text-gray-300">SMS template</strong> that describes how your bank's export files are structured.</li>
                    <li>Use the template to <strong class="text-gray-700 dark:text-gray-300">import a CSV / SMS export</strong> from your internet banking. The system parses each row, detects duplicates, and maps members automatically.</li>
                    <li>Review flagged transactions, <strong class="text-gray-700 dark:text-gray-300">post</strong> them to the ledger accounts, and reconcile contributions or loan repayments.</li>
                </ol>
            </div>
        </div>

    @elseif ($activeTab === 'bank-templates')
        <div class="mb-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                CSV import templates tell the system how to parse your bank's statement exports — column mapping, date format, amount structure, and duplicate-detection rules.
            </p>
        </div>
        @livewire(\App\Filament\Admin\Widgets\BankImportTemplatesTableWidget::class, [], key('bank-templates-table'))

    @else
        <div class="mb-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                SMS import templates define regex patterns used to extract transaction amounts, dates, references, and member identifiers from raw bank SMS notification exports.
            </p>
        </div>
        @livewire(\App\Filament\Admin\Widgets\SmsImportTemplatesTableWidget::class, [], key('sms-templates-table'))
    @endif

</x-filament-panels::page>
