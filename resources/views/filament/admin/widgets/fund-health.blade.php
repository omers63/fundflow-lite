@php $d = $this->getData(); @endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-heart class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('Financial Health') }}</h3>
        </div>
        <div class="flex items-center gap-2">
            @php
                $health = $d['coverage_status'] === 'success' && $d['compliance_status'] === 'success' && $d['overdue_amount'] === 0.0;
                $warn   = $d['coverage_status'] !== 'danger' && $d['compliance_status'] !== 'danger';
            @endphp
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold
                {{ $health ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300'
                           : ($warn ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300'
                                    : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300') }}">
                @if($health)
                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> {{ __('Healthy') }}
                @elseif($warn)
                    <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" /> {{ __('Monitor') }}
                @else
                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" /> {{ __('Action needed') }}
                @endif
            </span>
        </div>
    </div>

    {{-- Top row: 3 balance tiles --}}
    <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">

        {{-- Master Fund --}}
        <div class="p-5">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                    <x-heroicon-o-building-library class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Master Fund') }}</span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-white">SAR {{ number_format($d['master_fund'], 0) }}</p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-emerald-500 transition-all" style="width: {{ $d['fund_pct'] }}%"></div>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ __(':pct% of total assets', ['pct' => $d['fund_pct']]) }}</p>
        </div>

        {{-- Cash on Hand --}}
        <div class="p-5">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40">
                    <x-heroicon-o-banknotes class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Cash on Hand') }}</span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-white">SAR {{ number_format($d['master_cash'], 0) }}</p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-sky-500 transition-all" style="width: {{ $d['cash_pct'] }}%"></div>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ __(':pct% of total assets', ['pct' => $d['cash_pct']]) }}</p>
        </div>

        {{-- Loan Exposure --}}
        <div class="p-5">
            <div class="flex items-center gap-2 mb-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                    <x-heroicon-o-credit-card class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                </div>
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Loan Exposure') }}</span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-white">SAR {{ number_format($d['loan_exposure'], 0) }}</p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-amber-500 transition-all" style="width: {{ min(100, $d['exposure_pct']) }}%"></div>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ __(':count active / approved loans', ['count' => $d['loan_count']]) }}</p>
        </div>
    </div>

    {{-- Bottom row: 3 health indicators --}}
    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">

        {{-- Coverage Ratio --}}
        @php
            $cPalette = [
                'success' => ['bar' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'ring' => 'ring-emerald-200 dark:ring-emerald-800'],
                'warning' => ['bar' => 'bg-amber-500',   'text' => 'text-amber-600 dark:text-amber-400',   'bg' => 'bg-amber-50 dark:bg-amber-900/20',   'ring' => 'ring-amber-200 dark:ring-amber-800'],
                'danger'  => ['bar' => 'bg-red-500',     'text' => 'text-red-600 dark:text-red-400',       'bg' => 'bg-red-50 dark:bg-red-900/20',       'ring' => 'ring-red-200 dark:ring-red-800'],
                'gray'    => ['bar' => 'bg-gray-400',    'text' => 'text-gray-500 dark:text-gray-400',     'bg' => 'bg-gray-50 dark:bg-gray-700',        'ring' => 'ring-gray-200 dark:ring-gray-600'],
            ];
            $cp = $cPalette[$d['coverage_status']] ?? $cPalette['gray'];
            $ip = $cPalette[$d['compliance_status']] ?? $cPalette['gray'];
        @endphp
        <div class="rounded-xl {{ $cp['bg'] }} ring-1 {{ $cp['ring'] }} p-4">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-o-scale class="w-4 h-4 {{ $cp['text'] }}" />
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Fund Coverage Ratio') }}</span>
            </div>
            <p class="text-2xl font-bold {{ $cp['text'] }}">{{ $d['coverage_label'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __('Fund ÷ loan exposure') }}</p>
            <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-gray-600">
                <div class="h-2 rounded-full {{ $cp['bar'] }} transition-all" style="width: {{ $d['coverage_pct'] }}%"></div>
            </div>
            <p class="mt-1.5 text-xs {{ $cp['text'] }} font-medium">
                @if($d['coverage_status'] === 'success') {{ __('Strong — fund well-covers exposure') }}
                @elseif($d['coverage_status'] === 'warning') {{ __('Moderate — monitor closely') }}
                @else {{ __('Low — exposure exceeds fund') }}
                @endif
            </p>
        </div>

        {{-- Contribution Compliance --}}
        <div class="rounded-xl {{ $ip['bg'] }} ring-1 {{ $ip['ring'] }} p-4">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-o-chart-pie class="w-4 h-4 {{ $ip['text'] }}" />
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Contribution Compliance') }}</span>
            </div>
            <p class="text-2xl font-bold {{ $ip['text'] }}">{{ $d['compliance_rate'] }}%</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __(':paid of :active members paid this month', ['paid' => $d['paid_this_month'], 'active' => $d['active_count']]) }}</p>
            <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-gray-600">
                <div class="h-2 rounded-full {{ $ip['bar'] }} transition-all" style="width: {{ $d['compliance_rate'] }}%"></div>
            </div>
            <p class="mt-1.5 text-xs {{ $ip['text'] }} font-medium">
                @if($d['compliance_rate'] >= 90) {{ __('Excellent compliance') }}
                @elseif($d['compliance_rate'] >= 70) {{ __('Good — room to improve') }}
                @else {{ __('Low — follow up needed') }}
                @endif
            </p>
        </div>

        {{-- Overdue Loan Amount --}}
        @php
            $op = $d['overdue_amount'] > 0 ? $cPalette['danger'] : $cPalette['success'];
        @endphp
        <div class="rounded-xl {{ $op['bg'] }} ring-1 {{ $op['ring'] }} p-4">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 {{ $op['text'] }}" />
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Overdue Exposure') }}</span>
            </div>
            <p class="text-2xl font-bold {{ $op['text'] }}">SAR {{ number_format($d['overdue_amount'], 0) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ __(':count installments overdue', ['count' => $d['overdue_count']]) }}</p>
            <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-gray-600">
                @php
                    $opPct = $d['total_assets'] > 0 ? min(100, round($d['overdue_amount'] / max(1, $d['total_assets']) * 100)) : 0;
                @endphp
                <div class="h-2 rounded-full {{ $op['bar'] }} transition-all" style="width: {{ $opPct }}%"></div>
            </div>
            <p class="mt-1.5 text-xs {{ $op['text'] }} font-medium">
                @if($d['overdue_amount'] === 0.0) {{ __('All installments current') }}
                @else {{ __('Action required') }}
                @endif
            </p>
        </div>
    </div>
</div>
