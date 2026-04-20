@php
    $d = $this->getData();
    $fmt = fn(float $v) => $v >= 1_000_000
        ? number_format($v / 1_000_000, 2) . 'M'
        : ($v >= 1_000 ? number_format($v / 1_000, 1) . 'k' : number_format($v, 0));
@endphp

<div class="w-full max-w-none space-y-4 mb-2">

    {{-- ── Top KPI row ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">

        {{-- Active loans --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-credit-card class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Active Loans') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['active_count'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('SAR :amount outstanding', ['amount' => $fmt($d['active_amount'])]) }}</p>
            </div>
        </div>

        {{-- Awaiting disbursement --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-amber-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-queue-list class="w-4 h-4 text-amber-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('In Queue') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['pending_queue_count'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('SAR :amount requested', ['amount' => $fmt($d['pending_queue_amount'])]) }}</p>
            </div>
        </div>

        {{-- Overdue installments --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['overdue_count'] > 0 ? 'bg-red-500' : 'bg-emerald-500' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 {{ $d['overdue_count'] > 0 ? 'text-red-500' : 'text-emerald-500' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Overdue') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $d['overdue_count'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $d['overdue_count'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('SAR :amount overdue', ['amount' => $fmt($d['overdue_amount'])]) }}</p>
            </div>
        </div>

        {{-- New this month --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-plus-circle class="w-4 h-4 text-indigo-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('New This Month') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['new_this_month'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __('Applications received') }}</p>
            </div>
        </div>

        {{-- Disbursed this month --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-teal-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Disbursed (mo.)') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('SAR') }} {{ $fmt($d['disbursed_this_month']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ now()->format('F Y') }}</p>
            </div>
        </div>

        {{-- Completed / settled --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-check-badge class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Completed') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['by_status']['completed']['count'] + $d['by_status']['early_settled']['count'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">
                    {{ __(':completed complete · :early early settled', ['completed' => $d['by_status']['completed']['count'], 'early' => $d['by_status']['early_settled']['count']]) }}
                </p>
            </div>
        </div>

    </div>

    {{-- ── Status breakdown bar ───────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
            <x-heroicon-o-chart-bar class="w-4 h-4 text-gray-400" />
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Portfolio Breakdown by Status') }}</h4>
        </div>
        @php
            $statusMeta = [
                'pending'       => ['label' => __('Pending'),       'color' => 'bg-amber-400',    'text' => 'text-amber-700 dark:text-amber-300'],
                'approved'      => ['label' => __('Approved'),      'color' => 'bg-indigo-500',   'text' => 'text-indigo-700 dark:text-indigo-300'],
                'active'        => ['label' => __('Active'),        'color' => 'bg-emerald-500',  'text' => 'text-emerald-700 dark:text-emerald-300'],
                'completed'     => ['label' => __('Completed'),     'color' => 'bg-gray-400',     'text' => 'text-gray-600 dark:text-gray-400'],
                'early_settled' => ['label' => __('Early Settled'), 'color' => 'bg-teal-400',     'text' => 'text-teal-700 dark:text-teal-300'],
                'rejected'      => ['label' => __('Rejected'),      'color' => 'bg-red-400',      'text' => 'text-red-700 dark:text-red-300'],
                'cancelled'     => ['label' => __('Cancelled'),     'color' => 'bg-slate-300',    'text' => 'text-slate-600 dark:text-slate-400'],
            ];
            $grandTotal = collect($d['by_status'])->sum('count');
        @endphp
        <div class="px-5 py-4">
            {{-- Stacked progress bar --}}
            <div class="flex w-full rounded-full overflow-hidden h-3 mb-4 bg-gray-100 dark:bg-gray-700">
                @foreach($statusMeta as $key => $meta)
                @php $pct = $grandTotal > 0 ? round($d['by_status'][$key]['count'] / $grandTotal * 100, 1) : 0; @endphp
                @if($pct > 0)
                <div class="h-3 {{ $meta['color'] }} transition-all" style="width: {{ $pct }}%" title="{{ $meta['label'] }}: {{ $d['by_status'][$key]['count'] }}"></div>
                @endif
                @endforeach
            </div>
            {{-- Legend --}}
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($statusMeta as $key => $meta)
                @php $pct = $grandTotal > 0 ? round($d['by_status'][$key]['count'] / $grandTotal * 100) : 0; @endphp
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full {{ $meta['color'] }}"></span>
                    <span class="text-xs {{ $meta['text'] }} font-medium">{{ $meta['label'] }}</span>
                    <span class="text-xs text-gray-400">({{ $d['by_status'][$key]['count'] }}{{ $pct > 0 ? ' · ' . $pct . '%' : '' }})</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
