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
            {{ __('Loans') }}
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'contribution-cycles'"
            icon="heroicon-o-arrow-path-rounded-square"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'contribution-cycles')"
        >
            {{ __('Contribution cycles') }}
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'public-membership'"
            icon="heroicon-o-globe-alt"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'public-membership')"
        >
            {{ __('Public membership') }}
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'statements'"
            icon="heroicon-o-document-chart-bar"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'statements')"
        >
            {{ __('Statements') }}
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'communication'"
            icon="heroicon-o-chat-bubble-left-right"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'communication')"
        >
            {{ __('Communication') }}
        </x-filament::tabs.item>
        <x-filament::tabs.item
            :active="$activeTab === 'roles'"
            icon="heroicon-o-shield-check"
            tag="button"
            type="button"
            wire:click="$set('activeTab', 'roles')"
        >
            {{ __('Roles & permissions') }}
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($activeTab === 'loans')
        <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-white/10 dark:bg-white/5 mb-6">
            <p class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('Loan configuration') }}
            </p>
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'loan-rules'"
                    icon="heroicon-o-adjustments-horizontal"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'loan-rules')"
                >
                    {{ __('Loan rules') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'loan-tiers'"
                    icon="heroicon-o-squares-2x2"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'loan-tiers')"
                >
                    {{ __('Loan tiers') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item
                    :active="$loanSubTab === 'fund-tiers'"
                    icon="heroicon-o-chart-pie"
                    tag="button"
                    type="button"
                    wire:click="$set('loanSubTab', 'fund-tiers')"
                >
                    {{ __('Fund tiers') }}
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        @if ($loanSubTab === 'loan-rules')
            @php
                $settings = [
                    [
                        'label' => __('Eligibility Period'),
                        'value' => \App\Models\Setting::loanEligibilityMonths() . ' ' . __('months'),
                        'description' => __('Minimum membership duration before applying for a loan'),
                        'icon' => 'heroicon-o-calendar-days',
                        'color' => 'text-primary-600 dark:text-primary-400',
                        'accent' => 'bg-primary-500',
                    ],
                    [
                        'label' => __('Min Fund Balance'),
                        'value' => __('SAR') . ' ' . number_format(\App\Models\Setting::loanMinFundBalance()),
                        'description' => __('Minimum fund account balance required for eligibility'),
                        'icon' => 'heroicon-o-building-library',
                        'color' => 'text-teal-600 dark:text-teal-400',
                        'accent' => 'bg-teal-500',
                    ],
                    [
                        'label' => __('Max Borrow Multiplier'),
                        'value' => \App\Models\Setting::loanMaxBorrowMultiplier() . '× ' . __('Fund Balance'),
                        'description' => __('Maximum loan amount as a multiple of member fund balance'),
                        'icon' => 'heroicon-o-arrows-up-down',
                        'color' => 'text-indigo-600 dark:text-indigo-400',
                        'accent' => 'bg-indigo-500',
                    ],
                    [
                        'label' => __('Settlement Threshold'),
                        'value' => (\App\Models\Setting::loanSettlementThreshold() * 100) . '% ' . __('of Loan'),
                        'description' => __('Fund balance required relative to loan amount to trigger early settlement'),
                        'icon' => 'heroicon-o-scale',
                        'color' => 'text-amber-600 dark:text-amber-400',
                        'accent' => 'bg-amber-500',
                    ],
                    [
                        'label' => __('Grace Cycles'),
                        'value' => \App\Models\Setting::loanDefaultGraceCycles() . ' ' . __('cycles'),
                        'description' => __('Missed repayment cycles before guarantor liability begins'),
                        'icon' => 'heroicon-o-shield-exclamation',
                        'color' => 'text-rose-600 dark:text-rose-400',
                        'accent' => 'bg-rose-500',
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-6">
                @foreach($settings as $s)
                    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
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
                    {{ __('Amount bands and minimum installments for borrower-facing loan tiers.') }}
                </p>
                @livewire(\App\Filament\Admin\Widgets\LoanTiersTableWidget::class, [], key('loan-tiers-table'))
            </div>
        @else
            <div class="space-y-2">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    {{ __('How the master fund is split across queues (including emergency).') }}
                </p>
                @livewire(\App\Filament\Admin\Widgets\FundTiersTableWidget::class, [], key('fund-tiers-table'))
            </div>
        @endif
    @elseif ($activeTab === 'contribution-cycles')
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">{{ __('How cycles work') }}</h3>
                <ul class="list-disc pl-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>{{ __('Each cycle is tied to a calendar month.') }}</li>
                    <li>{{ __('It starts on the configured day of that month.') }}</li>
                    <li>{{ __('It ends the day before the same numbered day in the next month.') }}</li>
                    <li>{{ __('The due date is the last day of that window (end of day).') }}</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
                    <div class="pl-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">{{ __('Example (June, current year)') }}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->exampleJuneCycleLine() }}</p>
                    </div>
                </div>
                <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
                    <div class="pl-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">{{ __('Current open period (today)') }}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $this->currentOpenPeriodLine() }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-5 dark:border-amber-800/50 dark:bg-amber-950/20">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">{{ __('Delinquency policy (automated)') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                    {{ __('The scheduled command') }} <code class="text-xs bg-white/60 dark:bg-black/20 px-1 rounded">fund:check-delinquency</code> {{ __('runs daily.') }}
                    {{ __('It evaluates missed contributions (when your cycle rules require them) and unpaid loan installments on active loans.') }}
                </p>
                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Consecutive threshold') }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\Setting::delinquencyConsecutiveMissThreshold() }} {{ __('months') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Total misses (rolling)') }}</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ \App\Models\Setting::delinquencyTotalMissThreshold() }} {{ __('in') }} {{ \App\Models\Setting::delinquencyTotalMissLookbackMonths() }} {{ __('months') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('On breach') }}</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ __('Member suspended (portal blocked); active loan repayments collected via guarantor when configured.') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">{{ __('Late fees (tiered)') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ __('SAR per tier after the cycle due (highest matching non-zero tier applies).') }}</p>
                <div class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Contributions') }}</p>
                        <dl class="space-y-1">
                            @foreach([1 => __('1+ days'), 10 => __('10+ days'), 20 => __('20+ days'), 30 => __('30+ days')] as $d => $label)
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ __('SAR') }} {{ number_format(\App\Models\Setting::lateFeeContributionTier($d), 2) }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('Repayments') }}</p>
                        <dl class="space-y-1">
                            @foreach([1 => __('1+ days'), 10 => __('10+ days'), 20 => __('20+ days'), 30 => __('30+ days')] as $d => $label)
                            <div class="flex justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">{{ __('SAR') }} {{ number_format(\App\Models\Setting::lateFeeRepaymentTier($d), 2) }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    {{ __('Cash-account debits bundle principal and late fee; late fees credit master cash only (not master fund).') }}
                </p>
            </div>
        </div>
    @elseif ($activeTab === 'public-membership')
        <x-filament::section
            :heading="__('Public apply page')"
            :description="__('Limits apply only to new submissions on the public membership wizard (not admin-created applications).')"
        >
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Current applications (total)') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        {{ number_format($this->getTotalApplicationsCount()) }}
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Configured maximum') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                        @if(\App\Models\Setting::maxPublicApplications() === 0)
                            <span class="text-primary-600 dark:text-primary-400">{{ __('No limit') }}</span>
                        @else
                            {{ number_format(\App\Models\Setting::maxPublicApplications()) }}
                        @endif
                    </dd>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10 sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Membership application fees (by type)') }}</dt>
                    <dd class="mt-1 space-y-1 text-sm font-medium text-gray-950 dark:text-white">
                        @php
                            $types = [
                                'new' => __('New'),
                                'resume' => __('Resume'),
                                'renew' => __('Renew'),
                            ];
                        @endphp
                        @foreach($types as $key => $label)
                            <div class="flex justify-between gap-4 border-b border-gray-100 pb-1 last:border-0 dark:border-white/10">
                                <span class="text-gray-600 dark:text-gray-400">{{ $label }}</span>
                                <span>
                                    @if(\App\Models\Setting::membershipApplicationFeeForType($key) > 0)
                                        {{ __('SAR') }} {{ number_format(\App\Models\Setting::membershipApplicationFeeForType($key), 2) }}
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">{{ __('No fee') }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                        @if(\App\Models\Setting::membershipApplicationFee() <= 0)
                            <p class="pt-1 text-xs font-normal text-gray-500 dark:text-gray-400">{{ __('Payment step is hidden on /apply when all fees are 0.') }}</p>
                        @else
                            <p class="pt-1 text-xs font-normal text-gray-500 dark:text-gray-400">{{ __('Credited to master cash on submit (per selected type).') }}</p>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-filament::section>
    @elseif ($activeTab === 'statements')
        @php
            $stmtSettings = [
                ['label' => __('Organization Name'),     'value' => \App\Models\Setting::statementBrandName(),         'icon' => 'heroicon-o-building-office-2',    'color' => 'text-emerald-600 dark:text-emerald-400', 'accent' => 'bg-emerald-500'],
                ['label' => __('Tagline'),               'value' => \App\Models\Setting::statementTagline() ?: '—',    'icon' => 'heroicon-o-tag',                 'color' => 'text-sky-600 dark:text-sky-400',        'accent' => 'bg-sky-500'],
                ['label' => __('Accent Color'),          'value' => \App\Models\Setting::statementAccentColor(),       'icon' => 'heroicon-o-swatch',              'color' => 'text-purple-600 dark:text-purple-400',  'accent' => 'bg-purple-500'],
                ['label' => __('Auto-Email'),            'value' => \App\Models\Setting::statementAutoEmail() ? __('Enabled') : __('Disabled'), 'icon' => 'heroicon-o-envelope', 'color' => \App\Models\Setting::statementAutoEmail() ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500', 'accent' => \App\Models\Setting::statementAutoEmail() ? 'bg-emerald-500' : 'bg-gray-400'],
                ['label' => __('Include Transactions'),  'value' => \App\Models\Setting::statementIncludeTransactions() ? __('Yes') : __('No'), 'icon' => 'heroicon-o-list-bullet',     'color' => 'text-indigo-600 dark:text-indigo-400',  'accent' => 'bg-indigo-500'],
                ['label' => __('Include Loan Section'),  'value' => \App\Models\Setting::statementIncludeLoanSection() ? __('Yes') : __('No'),  'icon' => 'heroicon-o-document-text',   'color' => 'text-amber-600 dark:text-amber-400',    'accent' => 'bg-amber-500'],
            ];
        @endphp

        <div class="space-y-6">

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6 mb-2">
                @foreach($stmtSettings as $s)
                <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $s['accent'] }}"></div>
                    <div class="pl-2">
                        <div class="flex items-center gap-2 mb-2">
                            <x-dynamic-component :component="$s['icon']" class="w-4 h-4 {{ $s['color'] }}" />
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $s['label'] }}</span>
                        </div>
                        <p class="text-sm font-bold {{ $s['color'] }}">{{ $s['value'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ __('Footer Disclaimer') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ \App\Models\Setting::statementFooterDisclaimer() ?: __('(not set)') }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ __('Authorized Signature Line') }}</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ \App\Models\Setting::statementSignatureLine() ?: __('(not set)') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-5 dark:border-emerald-800/50 dark:bg-emerald-950/20">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white mb-2">{{ __('Statement generation schedule') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    {{ __('The scheduled command') }} <code class="text-xs bg-white/60 dark:bg-black/20 px-1 rounded">statements:generate --notify</code>
                    {{ __('runs on the') }} <strong>{{ __('3rd of each month at 08:00') }}</strong>, {{ __('generating the previous month\'s statements and emailing members (if auto-email is enabled).') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('You can also run it manually from Admin → Finance → Statements using the') }} <strong>"{{ __('Generate + Send') }}"</strong> {{ __('action,') }}
                    {{ __('or for a specific member/period using the') }} <strong>"{{ __('Generate for Period') }}"</strong> {{ __('action.') }}
                </p>
            </div>

        </div>
    @elseif ($activeTab === 'communication')
        @php
            $channels = \App\Models\Setting::COMM_CHANNELS;
            $enabled  = [];
            foreach ($channels as $key => $_) {
                $enabled[$key] = \App\Models\Setting::commChannelEnabled($key);
            }
            $channelColors = [
                'in_app'   => ['on' => 'bg-indigo-500',  'off' => 'bg-gray-300 dark:bg-gray-600',  'badge_on' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300', 'badge_off' => 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400'],
                'email'    => ['on' => 'bg-blue-500',    'off' => 'bg-gray-300 dark:bg-gray-600',    'badge_on' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',   'badge_off' => 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400'],
                'sms'      => ['on' => 'bg-emerald-500', 'off' => 'bg-gray-300 dark:bg-gray-600', 'badge_on' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', 'badge_off' => 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400'],
                'whatsapp' => ['on' => 'bg-green-500',   'off' => 'bg-gray-300 dark:bg-gray-600',  'badge_on' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300', 'badge_off' => 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400'],
            ];
        @endphp

        <div class="space-y-6">

            {{-- ── Summary cards ────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach($channels as $key => $meta)
                @php $on = $enabled[$key]; $colors = $channelColors[$key]; @endphp
                <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $on ? $colors['on'] : $colors['off'] }}"></div>
                    <div class="pl-3">
                        <div class="flex items-center justify-between mb-2">
                            <x-dynamic-component :component="$meta['icon']" class="w-5 h-5 {{ $on ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}" />
                            <span @class([
                                'text-xs font-semibold px-2 py-0.5 rounded-full',
                                $colors['badge_on']  => $on,
                                $colors['badge_off'] => !$on,
                            ])>{{ $on ? __('Enabled') : __('Disabled') }}</span>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ __($meta['label']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ __($meta['desc']) }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- ── Info box ──────────────────────────────────────────────────── --}}
            <div class="rounded-xl border border-blue-200 bg-blue-50/80 p-5 dark:border-blue-800/50 dark:bg-blue-950/20">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 text-blue-500 flex-shrink-0" />
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">{{ __('How channel settings work') }}</h3>
                        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                            <li>{{ __('Disabling a channel') }} <strong>{{ __('immediately') }}</strong> {{ __('stops all future notifications on that channel, regardless of individual member preferences.') }}</li>
                            <li>{{ __('Member preferences are preserved — if you re-enable a channel, members will resume receiving notifications on their previously chosen settings.') }}</li>
                            <li><strong>{{ __('In-App Inbox') }}</strong> {{ __('is the primary channel. Disabling it will prevent members from seeing any alerts in the portal.') }}</li>
                            <li><strong>SMS</strong> {{ __('and') }} <strong>WhatsApp</strong> {{ __('require valid Twilio credentials in your') }} <code class="text-xs bg-white/60 dark:bg-black/20 px-1 rounded">.env</code> {{ __('file.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ── Per-category channel matrix ────────────────────────────────── --}}
            <x-filament::section>
                <x-slot name="heading">{{ __('Notification Category Coverage') }}</x-slot>
                <x-slot name="description">{{ __('Which channels each notification category can use. Grayed channels are either unsupported by that category or disabled system-wide.') }}</x-slot>

                @php
                    $categories = \App\Services\NotificationPreferenceService::CATEGORIES;
                    $chLabels = ['in_app' => __('In-App'), 'email' => __('Email'), 'sms' => __('SMS'), 'whatsapp' => __('WhatsApp')];
                @endphp

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Category') }}</th>
                                @foreach($chLabels as $chKey => $chLabel)
                                <th class="py-2 px-3 text-center text-xs font-semibold uppercase {{ $enabled[$chKey] ? 'text-gray-500 dark:text-gray-400' : 'text-red-400 dark:text-red-500' }}">
                                    {{ $chLabel }}
                                    @if(!$enabled[$chKey])
                                    <span class="block text-xs font-normal normal-case text-red-400">{{ __('Disabled') }}</span>
                                    @endif
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($categories as $type => $cat)
                            <tr>
                                <td class="py-2.5 pr-4">
                                    <div class="flex items-center gap-2">
                                        <x-dynamic-component :component="$cat['icon']" class="w-4 h-4 text-gray-400" />
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ __($cat['label']) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __($cat['description']) }}</p>
                                </td>
                                @foreach(array_keys($chLabels) as $chKey)
                                @php
                                    $supported = in_array($chKey, $cat['supported']);
                                    $forced    = in_array($chKey, $cat['forced']);
                                    $syson     = $enabled[$chKey];
                                @endphp
                                <td class="py-2.5 px-3 text-center">
                                    @if(!$supported)
                                        <span class="text-gray-300 dark:text-gray-600 text-lg">—</span>
                                    @elseif(!$syson)
                                        <span title="{{ __('System disabled') }}" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 dark:bg-red-900/30">
                                            <x-heroicon-o-x-mark class="w-3.5 h-3.5 text-red-400" />
                                        </span>
                                    @elseif($forced)
                                        <span title="{{ __('Required — always on') }}" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                            <x-heroicon-o-lock-closed class="w-3.5 h-3.5 text-amber-500" />
                                        </span>
                                    @else
                                        <span title="{{ __('Supported') }}" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                            <x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500" />
                                        </span>
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center gap-6 mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="inline-flex w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 items-center justify-center"><x-heroicon-o-check class="w-3 h-3 text-emerald-500" /></span> {{ __('Supported') }}</span>
                    <span class="flex items-center gap-1.5"><span class="inline-flex w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/30 items-center justify-center"><x-heroicon-o-lock-closed class="w-3 h-3 text-amber-500" /></span> {{ __('Required (forced on)') }}</span>
                    <span class="flex items-center gap-1.5"><span class="inline-flex w-5 h-5 rounded-full bg-red-100 dark:bg-red-900/30 items-center justify-center"><x-heroicon-o-x-mark class="w-3 h-3 text-red-400" /></span> {{ __('Disabled system-wide') }}</span>
                    <span class="flex items-center gap-1.5"><span class="text-gray-300 dark:text-gray-600 text-base font-bold leading-none">—</span> {{ __('Not supported by category') }}</span>
                </div>
            </x-filament::section>

        </div>

    @else
        <x-filament::section
            :heading="__('Roles and permissions')"
            :description="__('Manage admin roles and permission assignments used across resources and pages.')"
        >
            @if ($this->canViewRolesPage())
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Use the dedicated roles manager for creating/updating roles and granting capabilities.') }}
                    </p>
                    <a
                        href="{{ $this->getRolesPageUrl() }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        <x-heroicon-o-shield-check class="h-4 w-4" />
                        {{ __('Open Roles') }}
                    </a>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('You do not currently have permission to view role management.') }}
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
