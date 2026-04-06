@php
    $d = $this->getData();
    $total = max(1, $d['total']);
    $pollingInterval = $this->getPollingInterval();
@endphp

<div
    class="w-full max-w-none space-y-4 mb-2"
    @if (filled($pollingInterval))
        wire:poll.{{ $pollingInterval }}
    @endif
>

    {{-- ── KPI row ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">

        {{-- Pending review --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['pending'] > 0 ? 'bg-amber-500' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-clock class="w-4 h-4 {{ $d['pending'] > 0 ? 'text-amber-500' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending Review</p>
                </div>
                <p class="text-2xl font-bold {{ $d['pending'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $d['pending'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">Awaiting decision</p>
            </div>
        </div>

        {{-- Approved --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Approved</p>
                </div>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $d['approved'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $d['approved_this_month'] }} this month</p>
            </div>
        </div>

        {{-- Rejected --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['rejected'] > 0 ? 'bg-red-500' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-x-circle class="w-4 h-4 {{ $d['rejected'] > 0 ? 'text-red-500' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Rejected</p>
                </div>
                <p class="text-2xl font-bold {{ $d['rejected'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $d['rejected'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $d['rejected_this_month'] }} this month</p>
            </div>
        </div>

        {{-- New this month --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-plus-circle class="w-4 h-4 text-indigo-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">New This Month</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['new_this_month'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ now()->format('F Y') }}</p>
            </div>
        </div>

        {{-- Avg review time --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-teal-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Avg. Review Time</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['avg_review_days'] }}<span class="text-sm font-normal text-gray-400 ml-1">days</span></p>
                <p class="mt-0.5 text-xs text-gray-400">From submission to decision</p>
            </div>
        </div>

    </div>

    {{-- ── Bottom row: oldest pending + 6-month trend ────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- Oldest pending applications --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Oldest Pending — Need Attention</h4>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($d['recent_pending'] as $app)
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <x-heroicon-o-user class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $app['name'] }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $app['email'] }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $app['days_ago'] > 7 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                            {{ $app['days_ago'] }}d waiting
                        </span>
                        <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ $app['type'] }}</p>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center">
                    <x-heroicon-o-check-circle class="mx-auto w-8 h-8 text-emerald-400 mb-1" />
                    <p class="text-sm text-gray-400">No pending applications — all clear!</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- 6-month volume trend --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">6-Month Application Volume</h4>
            </div>
            <div class="px-5 py-4">
                @php $maxTotal = max(1, collect($d['trend'])->max('total')); @endphp
                <div class="flex items-end gap-3 h-20">
                    @foreach($d['trend'] as $t)
                    @php $barH = round($t['total'] / $maxTotal * 100); @endphp
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $t['total'] }}</span>
                        <div class="w-full flex flex-col justify-end" style="height: {{ max(4, $barH) }}%">
                            <div class="w-full rounded-t-md bg-indigo-500" style="height: 100%"></div>
                        </div>
                        <span class="text-xs text-gray-400">{{ $t['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                    @foreach($d['trend'] as $t)
                    <div class="text-center">
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $t['approved'] }}✓</span>
                        <span class="text-gray-300 dark:text-gray-600 mx-0.5">/</span>
                        <span class="text-red-500 dark:text-red-400">{{ $t['rejected'] }}✗</span>
                    </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-4 text-xs text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>Approved</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>Rejected</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>Total</span>
                </div>
            </div>
        </div>

    </div>

</div>
