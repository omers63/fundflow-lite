@php $d = $this->getData(); @endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-chart-bar-square class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Fund Overview') }}</h3>
        </div>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ now()->format('F Y') }}</span>
    </div>

    {{-- KPI cards: 2 rows × 4 cols --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-y divide-gray-100 dark:divide-gray-700">

        {{-- Row 1: Members / Apps / Fund --}}

        {{-- Active Members --}}
        <a href="{{ $d['members_url'] }}"
           class="group flex flex-col gap-3 p-5 hover:bg-emerald-50/60 dark:hover:bg-emerald-900/10 transition-colors border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40 ring-1 ring-emerald-200 dark:ring-emerald-800">
                    <x-heroicon-o-users class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                @if($d['new_this_month'] > 0)
                <span class="inline-flex items-center rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    +{{ $d['new_this_month'] }}
                </span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['active_members']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Active Members') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full bg-emerald-500" style="width: 100%"></div>
            </div>
        </a>

        {{-- Pending Applications --}}
        <a href="{{ $d['applications_url'] }}"
           class="group flex flex-col gap-3 p-5 hover:bg-amber-50/60 dark:hover:bg-amber-900/10 transition-colors border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40 ring-1 ring-amber-200 dark:ring-amber-800">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                @if($d['pending_apps'] > 0)
                <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                    {{ __('Review →') }}
                </span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold {{ $d['pending_apps'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($d['pending_apps']) }}
                </p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Pending Applications') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full {{ $d['pending_apps'] > 0 ? 'bg-amber-400' : 'bg-emerald-500' }}" style="width: 100%"></div>
            </div>
        </a>

        {{-- Total Fund --}}
        <div class="flex flex-col gap-3 p-5 border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/40 ring-1 ring-indigo-200 dark:ring-indigo-800">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <span class="text-xs font-medium text-indigo-500 dark:text-indigo-400">{{ __('cumulative') }}</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">﷼ {{ number_format($d['total_fund'], 0) }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Total Fund (Cumulative)') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full bg-indigo-500" style="width: 100%"></div>
            </div>
        </div>

        {{-- Contribution This Month (row 1, 4th col) --}}
        <div class="flex flex-col gap-3 p-5">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/40 ring-1 ring-violet-200 dark:ring-violet-800">
                    <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-violet-600 dark:text-violet-400" />
                </div>
                <span class="text-xs font-medium text-violet-600 dark:text-violet-400">{{ __('this month') }}</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">﷼ {{ number_format($d['contrib_this_month'], 0) }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Contributions (Month)') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full bg-violet-500" style="width: 100%"></div>
            </div>
        </div>

        {{-- Row 2: Loans / Overdue / Delinquent / Suspended --}}

        {{-- Active Loans --}}
        <a href="{{ $d['loans_url'] }}"
           class="group flex flex-col gap-3 p-5 hover:bg-sky-50/60 dark:hover:bg-sky-900/10 transition-colors border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 dark:bg-sky-900/40 ring-1 ring-sky-200 dark:ring-sky-800">
                    <x-heroicon-o-credit-card class="w-5 h-5 text-sky-600 dark:text-sky-400" />
                </div>
                @if($d['loans_this_month'] > 0)
                <span class="inline-flex items-center rounded-full bg-sky-100 dark:bg-sky-900/50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:text-sky-300">
                    {{ __('+:count new', ['count' => $d['loans_this_month']]) }}
                </span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($d['active_loans']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Active Loans') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full bg-sky-500" style="width: 100%"></div>
            </div>
        </a>

        {{-- Overdue Installments --}}
        <div class="flex flex-col gap-3 p-5 {{ $d['overdue_count'] > 0 ? 'bg-red-50/40 dark:bg-red-900/5' : '' }} border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $d['overdue_count'] > 0 ? 'bg-red-100 dark:bg-red-900/40 ring-1 ring-red-200 dark:ring-red-800' : 'bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600' }}">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 {{ $d['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}" />
                </div>
                @if($d['overdue_amount'] > 0)
                <span class="text-xs font-medium text-red-600 dark:text-red-400">﷼ {{ number_format($d['overdue_amount'], 0) }}</span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold {{ $d['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($d['overdue_count']) }}
                </p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Overdue Installments') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full {{ $d['overdue_count'] > 0 ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: 100%"></div>
            </div>
        </div>

        {{-- Delinquent Members --}}
        <div class="flex flex-col gap-3 p-5 {{ $d['delinquent'] > 0 ? 'bg-rose-50/40 dark:bg-rose-900/5' : '' }} border-r border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $d['delinquent'] > 0 ? 'bg-rose-100 dark:bg-rose-900/40 ring-1 ring-rose-200 dark:ring-rose-800' : 'bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600' }}">
                    <x-heroicon-o-user-minus class="w-5 h-5 {{ $d['delinquent'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}" />
                </div>
                @if($d['delinquent'] > 0)
                <span class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __('3+ overdue') }}</span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold {{ $d['delinquent'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($d['delinquent']) }}
                </p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Delinquent Members') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full {{ $d['delinquent'] > 0 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: 100%"></div>
            </div>
        </div>

        {{-- Suspended Members --}}
        <div class="flex flex-col gap-3 p-5 {{ $d['suspended'] > 0 ? 'bg-orange-50/40 dark:bg-orange-900/5' : '' }}">
            <div class="flex items-center justify-between">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $d['suspended'] > 0 ? 'bg-orange-100 dark:bg-orange-900/40 ring-1 ring-orange-200 dark:ring-orange-800' : 'bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600' }}">
                    <x-heroicon-o-no-symbol class="w-5 h-5 {{ $d['suspended'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-400' }}" />
                </div>
                @if($d['suspended'] > 0)
                <span class="text-xs font-medium text-orange-600 dark:text-orange-400">{{ __('portal blocked') }}</span>
                @endif
            </div>
            <div>
                <p class="text-2xl font-bold {{ $d['suspended'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($d['suspended']) }}
                </p>
                <p class="mt-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Suspended Members') }}</p>
            </div>
            <div class="h-1 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1 rounded-full {{ $d['suspended'] > 0 ? 'bg-orange-500' : 'bg-emerald-500' }}" style="width: 100%"></div>
            </div>
        </div>
    </div>
</div>
