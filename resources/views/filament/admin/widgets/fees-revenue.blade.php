@php $d = $this->getData(); @endphp

<div x-data="{ selectedFee: 'late' }"
    class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div
        class="flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/40">
            <x-heroicon-o-banknotes class="w-5 h-5 text-violet-600 dark:text-violet-400" />
        </div>
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Fee Revenue') }}</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500">
                {{ __('Late fees, membership fees, and annual subscriptions') }}
            </p>
        </div>
        <span
            class="ms-auto inline-flex items-center gap-1.5 rounded-full bg-violet-100 dark:bg-violet-900/40 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:text-violet-300">
            {{ __('This year') }}:
            {{ \App\Support\UiNumber::sar($d['late_fee_this_year'] + $d['membership_fee_this_year'] + $d['subscription_fee_this_year']) }}
        </span>
    </div>

    {{-- Three fee cards --}}
    <div
        class="grid grid-cols-1 gap-0 divide-y sm:grid-cols-3 sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">

        {{-- Late Fees --}}
        <button type="button" @click="selectedFee = 'late'"
            :class="selectedFee === 'late' ? 'bg-red-50/40 dark:bg-red-900/10' : ''"
            class="w-full appearance-none border-0 bg-transparent p-6 text-start transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 focus:outline-none">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/40">
                        <x-heroicon-o-clock class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Late Fees') }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('Contributions & repayments') }}</p>
                    </div>
                </div>
                @if($d['late_fee_count_this_year'] > 0)
                    <span
                        class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">
                        +{{ $d['late_fee_count_this_year'] }} {{ __('this year') }}
                    </span>
                @endif
            </div>

            {{-- All-time total --}}
            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                {{ \App\Support\UiNumber::sar($d['late_fee_all_time']) }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">{{ __('All-time') }} · {{ $d['late_fee_count'] }}
                {{ __('late records') }}
            </p>

            {{-- This year bar --}}
            @php
                $latePct = $d['late_fee_all_time'] > 0 ? min(100, round($d['late_fee_this_year'] / $d['late_fee_all_time'] * 100)) : 0;
            @endphp
            <div class="space-y-1">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ __('This year') }}</span>
                    <span
                        class="font-semibold text-red-600 dark:text-red-400">{{ \App\Support\UiNumber::sar($d['late_fee_this_year']) }}</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full bg-red-500 transition-all" style="width: {{ $latePct }}%"></div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $latePct }}% {{ __('of all-time') }}</p>
            </div>
            <p class="mt-4 text-xs font-medium text-red-600 dark:text-red-400">{{ __('Click to view details') }}</p>
        </button>

        {{-- Membership Fees --}}
        <button type="button" @click="selectedFee = 'membership'"
            :class="selectedFee === 'membership' ? 'bg-sky-50/40 dark:bg-sky-900/10' : ''"
            class="w-full appearance-none border-0 bg-transparent p-6 text-start transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 focus:outline-none">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/40">
                        <x-heroicon-o-identification class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Membership Fees') }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('Application fees (posted)') }}</p>
                    </div>
                </div>
                @if($d['membership_fee_count_this_year'] > 0)
                    <span
                        class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/30 px-2 py-0.5 text-xs font-medium text-sky-700 dark:text-sky-300">
                        +{{ $d['membership_fee_count_this_year'] }} {{ __('this year') }}
                    </span>
                @endif
            </div>

            <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                {{ \App\Support\UiNumber::sar($d['membership_fee_all_time']) }}
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">{{ __('All-time') }} ·
                {{ $d['membership_fee_count'] }} {{ __('applications') }}
            </p>

            @php
                $memberPct = $d['membership_fee_all_time'] > 0 ? min(100, round($d['membership_fee_this_year'] / $d['membership_fee_all_time'] * 100)) : 0;
            @endphp
            <div class="space-y-1">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ __('This year') }}</span>
                    <span
                        class="font-semibold text-sky-600 dark:text-sky-400">{{ \App\Support\UiNumber::sar($d['membership_fee_this_year']) }}</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                    <div class="h-2 rounded-full bg-sky-500 transition-all" style="width: {{ $memberPct }}%"></div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $memberPct }}% {{ __('of all-time') }}</p>
            </div>
            <p class="mt-4 text-xs font-medium text-sky-600 dark:text-sky-400">{{ __('Click to view details') }}</p>
        </button>

        {{-- Annual Subscription Fees --}}
        <button type="button" @click="selectedFee = 'subscription'"
            :class="selectedFee === 'subscription' ? 'bg-emerald-50/40 dark:bg-emerald-900/10' : ''"
            class="w-full appearance-none border-0 bg-transparent p-6 text-start transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/30 focus:outline-none">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40">
                        <x-heroicon-o-calendar-days class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ __('Annual Subscription Fees') }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('Anniversary-based charges') }}</p>
                    </div>
                </div>
                @if($d['subscription_fee_count_this_year'] > 0)
                    <span
                        class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        +{{ $d['subscription_fee_count_this_year'] }} {{ __('this year') }}
                    </span>
                @endif
            </div>

            @if($d['subscription_fee_all_time'] > 0)
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                            {{ \App\Support\UiNumber::sar($d['subscription_fee_all_time']) }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-3">{{ __('All-time') }} ·
                            {{ $d['subscription_fee_count'] }} {{ __('charges') }}
                        </p>

                @php
                    $subPct = $d['subscription_fee_all_time'] > 0 ? min(100, round($d['subscription_fee_this_year'] / $d['subscription_fee_all_time'] * 100)) : 0;
                @endphp
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>{{ __('This year') }}</span>
                                <span
                                    class="font-semibold text-emerald-600 dark:text-emerald-400">{{ \App\Support\UiNumber::sar($d['subscription_fee_this_year']) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-2 rounded-full bg-emerald-500 transition-all" style="width: {{ $subPct }}%"></div>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $subPct }}% {{ __('of all-time') }}</p>
                        </div>
                        <p class="mt-4 text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Click to view details') }}
                        </p>
            @else
                <div class="flex flex-col items-center justify-center py-4 text-center">
                    <x-heroicon-o-calendar-days class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No fees recorded yet') }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {{ __('Configure the annual fee in System Settings → Cycle Settings') }}
                    </p>
                </div>
            @endif
        </button>

    </div>

    <div class="border-t border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ __('Fee details') }}</h4>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Showing latest 10 records') }}</p>
        </div>

        <div x-show="selectedFee === 'late'" x-cloak>
            <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                {{ __('Late Fees from contributions and repayments') }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr
                            class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th class="py-2 text-start">{{ __('Member') }}</th>
                            <th class="py-2 text-start">{{ __('Source') }}</th>
                            <th class="py-2 text-start">{{ __('Date') }}</th>
                            <th class="py-2 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($d['late_fee_recent'] as $row)
                            <tr>
                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ $row['member'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['source'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['date'] ?? '—' }}</td>
                                <td class="py-2 text-end font-medium text-gray-800 dark:text-gray-100">
                                    {{ \App\Support\UiNumber::sar((float) $row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No late fee records found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="selectedFee === 'membership'" x-cloak>
            <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Posted application membership fees') }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr
                            class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th class="py-2 text-start">{{ __('Applicant') }}</th>
                            <th class="py-2 text-start">{{ __('Source') }}</th>
                            <th class="py-2 text-start">{{ __('Date') }}</th>
                            <th class="py-2 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($d['membership_fee_recent'] as $row)
                            <tr>
                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ $row['member'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['source'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['date'] ?? '—' }}</td>
                                <td class="py-2 text-end font-medium text-gray-800 dark:text-gray-100">
                                    {{ \App\Support\UiNumber::sar((float) $row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No membership fee records found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="selectedFee === 'subscription'" x-cloak>
            <div class="mb-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Annual subscription fee postings') }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr
                            class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th class="py-2 text-start">{{ __('Member') }}</th>
                            <th class="py-2 text-start">{{ __('Year') }}</th>
                            <th class="py-2 text-start">{{ __('Date') }}</th>
                            <th class="py-2 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($d['subscription_fee_recent'] as $row)
                            <tr>
                                <td class="py-2 text-gray-700 dark:text-gray-200">{{ $row['member'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['source'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['date'] ?? '—' }}</td>
                                <td class="py-2 text-end font-medium text-gray-800 dark:text-gray-100">
                                    {{ \App\Support\UiNumber::sar((float) $row['amount']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('No annual subscription fee records found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>