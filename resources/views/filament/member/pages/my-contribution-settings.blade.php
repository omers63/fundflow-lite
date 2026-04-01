<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">My Contribution Allocation</x-slot>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
            <x-filament::card class="col-span-1">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Monthly Allocation</div>
                <div class="mt-1 text-3xl font-bold text-primary-600 dark:text-primary-400">
                    SAR {{ number_format($this->monthly_contribution_amount) }}
                </div>
                <p class="mt-2 text-xs text-gray-400">
                    Use the <strong>Update My Allocation</strong> button above to change your amount.
                </p>
            </x-filament::card>

            <x-filament::card class="col-span-2">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Available Amounts</div>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Member::contributionAmountOptions() as $value => $label)
                        <span @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                            'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 ring-2 ring-primary-500' => $value == $this->monthly_contribution_amount,
                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $value != $this->monthly_contribution_amount,
                        ])>
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-400">
                    Amounts are in multiples of SAR 500. Your parent member (if any) may also update your allocation.
                </p>
            </x-filament::card>
        </div>
    </x-filament::section>
</x-filament-panels::page>
