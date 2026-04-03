<x-filament-panels::page>
@php
    $member  = auth()->user()?->member;
    $current = $this->monthly_contribution_amount;
    $options = \App\Models\Member::contributionAmountOptions();
    $minOpt  = array_key_first($options);
    $maxOpt  = array_key_last($options);

    // Progress position (how far along the available range)
    $range    = $maxOpt - $minOpt;
    $progress = $range > 0 ? round(($current - $minOpt) / $range * 100) : 0;

    // Contributions this calendar month (for context)
    $paidThisMonth = $member
        ? \App\Models\Contribution::where('member_id', $member->id)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount')
        : 0;

    // Total contributed ever
    $totalEver = $member
        ? \App\Models\Contribution::where('member_id', $member->id)->sum('amount')
        : 0;
@endphp

{{-- ── Hero allocation card ─────────────────────────────────────────────── --}}
<div class="rounded-2xl bg-gradient-to-br from-primary-600 to-primary-700 dark:from-primary-700 dark:to-primary-900 p-6 text-white shadow-lg mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-primary-100 uppercase tracking-wide">Monthly Contribution</p>
            <p class="mt-1 text-5xl font-extrabold tracking-tight">SAR {{ number_format($current) }}</p>
            <p class="mt-2 text-sm text-primary-200">
                Deducted automatically from your cash account each cycle
            </p>
        </div>
        <div class="flex-shrink-0 hidden sm:flex h-20 w-20 items-center justify-center rounded-full bg-white/10 ring-2 ring-white/20">
            <x-heroicon-o-banknotes class="w-10 h-10 text-white" />
        </div>
    </div>

    {{-- Range progress --}}
    <div class="mt-5">
        <div class="flex justify-between text-xs text-primary-200 mb-1.5">
            <span>SAR {{ number_format($minOpt) }}</span>
            <span class="font-medium text-white">Your level: {{ $progress }}% of max</span>
            <span>SAR {{ number_format($maxOpt) }}</span>
        </div>
        <div class="w-full rounded-full bg-white/20 h-2">
            <div class="h-2 rounded-full bg-white transition-all" style="width: {{ $progress }}%"></div>
        </div>
    </div>
</div>

{{-- ── Context stats ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Paid This Month</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">
            @if($paidThisMonth > 0)
                SAR {{ number_format($paidThisMonth) }}
                <span class="ml-2 inline-flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> Paid
                </span>
            @else
                <span class="text-amber-600 dark:text-amber-400">Pending</span>
            @endif
        </p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">{{ now()->format('F Y') }}</p>
    </div>

    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 pl-2">Total Contributed</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white pl-2">SAR {{ number_format($totalEver) }}</p>
        <p class="mt-0.5 text-xs text-gray-400 pl-2">Lifetime cumulative</p>
    </div>
</div>

{{-- ── Amount options grid ───────────────────────────────────────────────── --}}
<x-filament::section>
    <x-slot name="heading">Available Amounts</x-slot>
    <x-slot name="description">
        Multiples of SAR 500, from SAR {{ number_format($minOpt) }} to SAR {{ number_format($maxOpt) }}.
        Use the <strong>Update My Allocation</strong> button above to change your amount.
    </x-slot>

    <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
        @foreach($options as $value => $label)
        @php $isActive = $value == $current; @endphp
        <div @class([
            'relative flex flex-col items-center justify-center rounded-xl p-3 text-center transition-all',
            'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900/30' => $isActive,
            'ring-1 ring-gray-200 dark:ring-gray-700 bg-white dark:bg-gray-800 hover:ring-primary-300 dark:hover:ring-primary-700' => !$isActive,
        ])>
            @if($isActive)
            <div class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500">
                <x-heroicon-o-check class="w-3 h-3 text-white" />
            </div>
            @endif
            <p @class([
                'text-xs font-semibold uppercase tracking-wide mb-1',
                'text-primary-600 dark:text-primary-400' => $isActive,
                'text-gray-400 dark:text-gray-500' => !$isActive,
            ])>SAR</p>
            <p @class([
                'text-lg font-bold',
                'text-primary-700 dark:text-primary-300' => $isActive,
                'text-gray-700 dark:text-gray-300' => !$isActive,
            ])>{{ number_format($value) }}</p>
        </div>
        @endforeach
    </div>
</x-filament::section>

</x-filament-panels::page>
