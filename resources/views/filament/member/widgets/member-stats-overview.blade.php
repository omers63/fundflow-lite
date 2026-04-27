@php $d = $this->getData(); @endphp

@if(!$d['hasMember'])
    <div
        class="rounded-2xl bg-amber-50 dark:bg-amber-900/20 ring-1 ring-amber-200 dark:ring-amber-800 px-6 py-5 text-amber-700 dark:text-amber-300">
        {{ __('No member record found for your account.') }}
    </div>
@else
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

        {{-- Header --}}
        <div
            class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
            <div class="flex items-center gap-2">
                <x-heroicon-o-identification class="w-5 h-5 text-primary-500" />
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Member Overview') }}</h3>
            </div>
            <div class="flex items-center gap-2">
                @if($d['paid_this_month'])
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> {{ __('Paid this month') }}
                    </span>
                @else
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900/50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-clock class="w-3.5 h-3.5" /> {{ __('Contribution due') }}
                    </span>
                @endif
                @if($d['overdue_count'] > 0)
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/50 px-2.5 py-1 text-xs font-semibold text-red-700 dark:text-red-300">
                        <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                        {{ __(':count overdue', ['count' => $d['overdue_count']]) }}
                    </span>
                @endif
            </div>
        </div>

        {{-- 4 KPI cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700">

            {{-- Member ID / Since --}}
            <div class="flex flex-col gap-2.5 p-5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 dark:bg-primary-900/40 ring-1 ring-primary-200 dark:ring-primary-800">
                    <x-heroicon-o-identification class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $d['member_number'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Member Number') }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ __('Since :date', ['date' => $d['joined_at']]) }}</p>
                </div>
            </div>

            {{-- Total Contributions --}}
            <div class="flex flex-col gap-2.5 p-5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40 ring-1 ring-emerald-200 dark:ring-emerald-800">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ \App\Support\UiNumber::sar($d['total_contributions']) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Total Contributions') }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ __(':count payment(s)', ['count' => $d['contrib_count']]) }}
                    </p>
                </div>
            </div>

            {{-- Compliance / Streak --}}
            <div class="flex flex-col gap-2.5 p-5">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl
                    {{ $d['compliance_rate'] >= 90 ? 'bg-emerald-100 dark:bg-emerald-900/40 ring-1 ring-emerald-200 dark:ring-emerald-800'
            : ($d['compliance_rate'] >= 70 ? 'bg-amber-100 dark:bg-amber-900/40 ring-1 ring-amber-200 dark:ring-amber-800'
                : 'bg-red-100 dark:bg-red-900/40 ring-1 ring-red-200 dark:ring-red-800') }}">
                    <x-heroicon-o-chart-bar
                        class="w-5 h-5 {{ $d['compliance_rate'] >= 90 ? 'text-emerald-600 dark:text-emerald-400' : ($d['compliance_rate'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}" />
                </div>
                <div>
                    <p
                        class="text-lg font-bold {{ $d['compliance_rate'] >= 90 ? 'text-emerald-600 dark:text-emerald-400' : ($d['compliance_rate'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                        {{ $d['compliance_rate'] }}%
                    </p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Payment Compliance') }}</p>
                    @if($d['streak'] > 1)
                        <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">🔥
                            {{ __(':count-month streak', ['count' => $d['streak']]) }}</p>
                    @elseif($d['streak'] === 1)
                        <p class="mt-1 text-xs text-gray-400">{{ __('Started a streak!') }}</p>
                    @else
                        <p class="mt-1 text-xs text-red-400">{{ __('No active streak') }}</p>
                    @endif
                </div>
            </div>

            {{-- Loan / Next Due --}}
            <div class="flex flex-col gap-2.5 p-5 {{ $d['overdue_count'] > 0 ? 'bg-red-50/40 dark:bg-red-900/5' : '' }}">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl
                    {{ $d['overdue_count'] > 0 ? 'bg-red-100 dark:bg-red-900/40 ring-1 ring-red-200 dark:ring-red-800'
            : ($d['active_loan'] ? 'bg-sky-100 dark:bg-sky-900/40 ring-1 ring-sky-200 dark:ring-sky-800'
                : 'bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600') }}">
                    <x-heroicon-o-credit-card
                        class="w-5 h-5 {{ $d['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : ($d['active_loan'] ? 'text-sky-600 dark:text-sky-400' : 'text-gray-400') }}" />
                </div>
                <div>
                    @if($d['active_loan'])
                        <p
                            class="text-lg font-bold {{ $d['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-sky-600 dark:text-sky-400' }}">
                            {{ \App\Support\UiNumber::sar($d['active_loan']->amount_approved) }}
                        </p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Active Loan') }}</p>
                        @if($d['next_installment'])
                            <p class="mt-1 text-xs text-gray-400">
                                {{ __('Next: :amount due :date', ['amount' => \App\Support\UiNumber::sar($d['next_installment']->amount), 'date' => $d['next_installment']->due_date->locale(app()->getLocale())->translatedFormat('d M')]) }}
                            </p>
                        @endif
                    @else
                        <p class="text-lg font-bold text-gray-400">{{ __('None') }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Active Loan') }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('No outstanding loan') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif