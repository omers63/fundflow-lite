<x-filament-panels::page>
@php
    $service     = app(\App\Services\ContributionCycleService::class);
    $summaries   = $service->periodSummaries(6);
    $activeCount = \App\Models\Member::active()->count();

    // Aggregate quick stats across all shown periods
    $totalCollected = $summaries->sum('total_amount');
    $totalLate      = $summaries->sum('late_count');
    $latestPeriod   = $summaries->first();
    $complianceRate = ($latestPeriod && $activeCount > 0)
        ? round($latestPeriod['total_count'] / $activeCount * 100)
        : 0;
@endphp

{{-- ── Hero summary bar ──────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-6">
    {{-- Active members --}}
    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Active Members</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">{{ $activeCount }}</p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">Currently enrolled</p>
    </div>

    {{-- Latest compliance --}}
    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $complianceRate >= 90 ? 'bg-emerald-500' : ($complianceRate >= 70 ? 'bg-amber-500' : 'bg-red-500') }}"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Latest Compliance</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">{{ $complianceRate }}%</p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">
            @if($latestPeriod) {{ $latestPeriod['total_count'] }} / {{ $activeCount }} paid — {{ $latestPeriod['period_label'] }} @else No data @endif
        </p>
    </div>

    {{-- Collected (6 months) --}}
    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Collected (6 mo.)</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">
            @if($totalCollected >= 1000) {{ number_format($totalCollected / 1000, 1) }}k @else {{ number_format($totalCollected) }} @endif SAR
        </p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">Across {{ $summaries->count() }} periods</p>
    </div>

    {{-- Late payments --}}
    <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $totalLate > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Late Payments</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">{{ $totalLate }}</p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">Across recent periods</p>
    </div>
</div>

{{-- ── Period history cards ───────────────────────────────────────────────── --}}
@if($summaries->isNotEmpty())
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Recent Periods</h3>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($summaries as $index => $row)
        @php
            $periodRate  = $activeCount > 0 ? round($row['total_count'] / $activeCount * 100) : 0;
            $isLatest    = $index === 0;
            $onTime      = $row['total_count'] - $row['late_count'];
        @endphp
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            {{-- Card header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $row['period_label'] }}</span>
                    @if($isLatest)
                        <span class="inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300">Latest</span>
                    @endif
                </div>
                @if($row['late_count'] > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-clock class="w-3 h-3" /> {{ $row['late_count'] }} late
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        <x-heroicon-o-check-circle class="w-3 h-3" /> On time
                    </span>
                @endif
            </div>

            {{-- Card body --}}
            <div class="px-4 py-3 space-y-3">
                {{-- Amount & count --}}
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-xl font-bold text-primary-600 dark:text-primary-400">SAR {{ number_format($row['total_amount']) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $row['total_count'] }} of {{ $activeCount }} members &bull; due {{ $row['deadline'] }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold {{ $periodRate >= 90 ? 'text-emerald-600 dark:text-emerald-400' : ($periodRate >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $periodRate }}%</span>
                        <p class="text-xs text-gray-400">compliance</p>
                    </div>
                </div>

                {{-- Compliance progress bar --}}
                <div class="w-full rounded-full bg-gray-100 dark:bg-gray-700 h-1.5 overflow-hidden">
                    <div class="h-1.5 rounded-full transition-all {{ $periodRate >= 90 ? 'bg-emerald-500' : ($periodRate >= 70 ? 'bg-amber-500' : 'bg-red-500') }}"
                         style="width: {{ min(100, $periodRate) }}%"></div>
                </div>

                {{-- On-time vs late breakdown --}}
                <div class="flex gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span> {{ $onTime }} on time
                    </span>
                    @if($row['late_count'] > 0)
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-amber-400"></span> {{ $row['late_count'] }} late
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Period summary for paid tab ────────────────────────────────────────── --}}
@php
    [$currentMonth, $currentYear] = app(\App\Services\ContributionCycleService::class)->currentOpenPeriod();
    $paidContributions = \App\Models\Contribution::where('month', $currentMonth)
        ->where('year', $currentYear)
        ->get();
    $paidCount = $paidContributions->count();
    $paidTotal = $paidContributions->sum('amount');
    $lateCount = $paidContributions->where('is_late', true)->count();
    $pendingCount = \App\Models\Member::where('status', 'active')->count() - $paidCount;
@endphp

{{-- ── Tab switcher ────────────────────────────────────────────────────────── --}}
<div class="flex items-center gap-1 mb-4 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 w-fit">
    <button
        wire:click="setContributionTab('pending')"
        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all
            {{ $this->contributionPeriodTab === 'pending'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
    >
        <x-heroicon-o-clock class="w-4 h-4" />
        Pending
        @if($pendingCount > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 text-xs font-bold">
                {{ $pendingCount }}
            </span>
        @endif
    </button>
    <button
        wire:click="setContributionTab('paid')"
        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all
            {{ $this->contributionPeriodTab === 'paid'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
    >
        <x-heroicon-o-check-circle class="w-4 h-4" />
        Paid
        @if($paidCount > 0)
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                {{ $paidCount }}
            </span>
        @endif
    </button>
</div>

{{-- ── Paid tab summary strip ──────────────────────────────────────────────── --}}
@if($this->contributionPeriodTab === 'paid' && $paidCount > 0)
<div class="grid grid-cols-3 gap-3 mb-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Collected</p>
        <p class="mt-1 text-xl font-bold text-emerald-600 dark:text-emerald-400">SAR {{ number_format($paidTotal) }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $paidCount }} payment(s)</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">On Time</p>
        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $paidCount - $lateCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">of {{ $paidCount }} paid</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Late Payments</p>
        <p class="mt-1 text-xl font-bold {{ $lateCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">{{ $lateCount }}</p>
        <p class="text-xs text-gray-400 mt-0.5">flagged as late</p>
    </div>
</div>
@endif

{{-- ── Members table (pending / paid) ──────────────────────────────────────── --}}
<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            <x-heroicon-o-users class="w-5 h-5 text-gray-400" />
            <span>{{ $this->contributionPeriodTab === 'paid' ? 'Paid Members' : 'Pending Members' }}</span>
        </div>
    </x-slot>
    {{ $this->table }}
</x-filament::section>

</x-filament-panels::page>
