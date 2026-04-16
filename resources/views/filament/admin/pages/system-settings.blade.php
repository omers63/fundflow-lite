<x-filament-panels::page>
    {{-- Top-level sections --}}
    <x-filament::tabs class="mb-4">
        <x-filament::tabs.item
            :active="$activeTab === 'loans'"
            icon="heroicon-o-banknotes"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'loans')"
        >
            Loans
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'contribution-cycles'"
            icon="heroicon-o-arrow-path-rounded-square"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'contribution-cycles')"
        >
            Contribution cycles
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'public-membership'"
            icon="heroicon-o-globe-alt"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'public-membership')"
        >
            Public membership
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'roles'"
            icon="heroicon-o-shield-check"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'roles')"
        >
            Roles & permissions
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($activeTab === 'loans')
        <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-white/10 dark:bg-white/5 mb-6">
            <p class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Loan configuration
            </p>
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'loan-rules'"
                    icon="heroicon-o-adjustments-horizontal"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'loan-rules')"
                >
                    Loan rules
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'loan-tiers'"
                    icon="heroicon-o-squares-2x2"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'loan-tiers')"
                >
                    Loan tiers
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'fund-tiers'"
                    icon="heroicon-o-chart-pie"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'fund-tiers')"
                >
                    Fund tiers
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        @if ($loanSubTab === 'loan-rules')
            @php
                $settings = [
                    [
                        'label' => 'Eligibility Period',
                        'value' => \App\Models\Setting::loanEligibilityMonths() . ' months',
                        'description' => 'Minimum membership duration before applying for a loan',
                        'icon' => 'heroicon-o-calendar-days',
                        'color' => 'text-primary-600 dark:text-primary-400',
                        'accent' => 'bg-primary-500',
                    ],
                    [
                        'label' => 'Min Fund Balance',
                        'value' => 'SAR ' . number_format(\App\Models\Setting::loanMinFundBalance()),
                        'description' => 'Minimum fund account balance required for eligibility',
                        'icon' => 'heroicon-o-building-library',
                        'color' => 'text-teal-600 dark:text-teal-400',
                        'accent' => 'bg-teal-500',
                    ],
                    [
                        'label' => 'Max Borrow Multiplier',
                        'value' => \App\Models\Setting::loanMaxBorrowMultiplier() . '× Fund Balance',
                        'description' => 'Maximum loan amount as a multiple of member fund balance',
                        'icon' => 'heroicon-o-arrows-up-down',
                        'color' => 'text-indigo-600 dark:text-indigo-400',
                        'accent' => 'bg-indigo-500',
                    ],
                    [
                        'label' => 'Settlement Threshold',
                        'value' => (\App\Models\Setting::loanSettlementThreshold() * 100) . '% of Loan',
                        'description' => 'Fund balance required relative to loan amount to trigger early settlement',
                        'icon' => 'heroicon-o-scale',
                        'color' => 'text-amber-600 dark:text-amber-400',
                        'accent' => 'bg-amber-500',
                    ],
                    [
                        'label' => 'Grace Cycles',
                        'value' => \App\Models\Setting::loanDefaultGraceCycles() . ' cycles',
                        'description' => 'Missed repayment cycles before guarantor liability begins',
                        'icon' => 'heroicon-o-shield-exclamation',
                        'color' => 'text-rose-600 dark:text-rose-400',
                        'accent' => 'bg-rose-500',
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-6">
                @foreach($settings as $s)
                    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
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
        @elseif ($loanSubTab === 'loan-tiers')
            <div class="space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Amount bands and minimum installments for borrower-facing loan tiers.
                </p>
                @livewire(\App\Filament\Admin\Widgets\LoanTiersTableWidget::class, [], key('loan-tiers-table'))
            </div>
        @else
            <div class="space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    How the master fund is split across queues (including emergency).
                </p>
                @livewire(\App\Filament\Admin\Widgets\FundTiersTableWidget::class, [], key('fund-tiers-table'))
            </div>
        @endif
    @elseif ($activeTab === 'contribution-cycles')
        <div class="space-y-6">
            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">How cycles work</h3>
                <ul class="list-disc pl-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>Each cycle is tied to a calendar month.</li>
                    <li>It starts on the configured day of that month.</li>
                    <li>It ends the day before the same numbered day in the next month.</li>
                    <li>The due date is the last day of that window (end of day).</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
                    <div class="pl-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Example (June, current year)</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->exampleJuneCycleLine() }}</p>
                    </div>
                </div>
                <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
                    <div class="pl-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Current open period (today)</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->currentOpenPeriodLine() }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-amber-50/80 dark:bg-amber-950/20 ring-1 ring-amber-200/80 dark:ring-amber-800/50 p-5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">Delinquency policy (automated)</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    The scheduled command <code class="text-xs bg-white/60 dark:bg-black/20 px-1 rounded">fund:check-delinquency</code> runs daily.
                    It evaluates missed contributions (when your cycle rules require them) and unpaid loan installments on active loans.
                </p>
                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Consecutive threshold</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\Setting::delinquencyConsecutiveMissThreshold() }} months</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Total misses (rolling)</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\Setting::delinquencyTotalMissThreshold() }} in {{ \App\Models\Setting::delinquencyTotalMissLookbackMonths() }} months</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">On breach</dt>
                        <dd class="text-gray-700 dark:text-gray-300">Member suspended (portal blocked); active loan repayments collected via guarantor when configured.</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">Late fees (tiered)</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">SAR per tier after the cycle due (highest matching non-zero tier applies).</p>
                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">Contributions</p>
                        <dl class="space-y-1">
                            @foreach([1 => '1+ days', 10 => '10+ days', 20 => '20+ days', 30 => '30+ days'] as $d => $label)
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">SAR {{ number_format(\App\Models\Setting::lateFeeContributionTier($d), 2) }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">Repayments</p>
                        <dl class="space-y-1">
                            @foreach([1 => '1+ days', 10 => '10+ days', 20 => '20+ days', 30 => '30+ days'] as $d => $label)
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">SAR {{ number_format(\App\Models\Setting::lateFeeRepaymentTier($d), 2) }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Cash-account debits bundle principal and late fee; late fees credit master cash only (not master fund).
                </p>
            </div>
        </div>
    @elseif ($activeTab === 'public-membership')
        <x-filament::section
            heading="Public apply page"
            description="Limits apply only to new submissions on the public membership wizard (not admin-created applications)."
        >
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-gray-500 dark:text-gray-400">Current applications (total)</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ number_format($this->getTotalApplicationsCount()) }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-gray-500 dark:text-gray-400">Configured maximum</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        @if(\App\Models\Setting::maxPublicApplications() === 0)
                            <span class="text-primary-600 dark:text-primary-400">No limit</span>
                        @else
                            {{ number_format(\App\Models\Setting::maxPublicApplications()) }}
                        @endif
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10 sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400">Membership application fee</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        @if(\App\Models\Setting::membershipApplicationFee() > 0)
                            SAR {{ number_format(\App\Models\Setting::membershipApplicationFee(), 2) }} (credited to master cash on submit)
                        @else
                            <span class="text-gray-500 dark:text-gray-400">Disabled (0 SAR)</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    @else
        <x-filament::section
            heading="Roles and permissions"
            description="Manage admin roles and permission assignments used across resources and pages."
        >
            @if ($this->canViewRolesPage())
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Use the dedicated roles manager for creating/updating roles and granting capabilities.
                    </p>
                    <a
                        href="{{ $this->getRolesPageUrl() }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        <x-heroicon-o-shield-check class="h-4 w-4" />
                        Open Roles
                    </a>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    You do not currently have permission to view role management.
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
