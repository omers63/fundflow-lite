<x-filament-panels::page>
    @php
        $settings = [
            ['label' => 'Eligibility Period',          'value' => \App\Models\Setting::loanEligibilityMonths() . ' months'],
            ['label' => 'Min Fund Balance',             'value' => 'SAR ' . number_format(\App\Models\Setting::loanMinFundBalance())],
            ['label' => 'Max Borrow Multiplier',        'value' => \App\Models\Setting::loanMaxBorrowMultiplier() . '× Fund Balance'],
            ['label' => 'Settlement Threshold',         'value' => (\App\Models\Setting::loanSettlementThreshold() * 100) . '% of Loan Amount'],
            ['label' => 'Default Grace Cycles',         'value' => \App\Models\Setting::loanDefaultGraceCycles() . ' cycles'],
        ];
    @endphp

    <x-filament::section heading="Current Loan Settings">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($settings as $s)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $s['label'] }}</dt>
                <dd class="mt-1 text-xl font-bold text-primary-600 dark:text-primary-400">{{ $s['value'] }}</dd>
            </div>
            @endforeach
        </dl>
        <p class="mt-4 text-sm text-gray-500">Use the <strong>Save Settings</strong> button above to modify these values.</p>
    </x-filament::section>
</x-filament-panels::page>
