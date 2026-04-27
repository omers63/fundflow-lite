@php $d = $d ?? $this->getData(); @endphp

@if(!($d['hasRecord'] ?? false))
    <div class="p-4 text-gray-400 text-sm">{{ __('No member selected.') }}</div>
@else
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- ── Left: Contribution heatmap + chart ────────────────────────────── --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center">
                        <x-heroicon-o-currency-dollar class="w-4 h-4 text-white" />
                    </div>
                    <h3 class="font-semibold text-white">{{ __('Contribution History') }}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/80">
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm bg-emerald-300 inline-block"></span> {{ __('Paid') }}
                    </span>
                    <span class="flex items-center gap-1" title="Flagged is_late on the contribution (after deadline)">
                        <span class="w-3 h-3 rounded-sm bg-amber-300 inline-block"></span> {{ __('Late') }}
                    </span>
                    <span class="flex items-center gap-1" title="Paid but below monthly allocation">
                        <span class="w-3 h-3 rounded-sm bg-orange-300 inline-block"></span> {{ __('Short') }}
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm bg-red-300/70 inline-block"></span> {{ __('Missed') }}
                    </span>
                </div>
            </div>

            {{-- Summary chips --}}
            <div class="px-5 pt-4 flex flex-wrap gap-2">
                <div
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700">
                    <x-heroicon-o-check-circle class="w-3.5 h-3.5 text-emerald-600" />
                    <span
                        class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ __(':count paid (last 12 months)', ['count' => $d['paid_count']]) }}</span>
                </div>
                @if($d['missed_count'] > 0)
                    <div
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700">
                        <x-heroicon-o-x-circle class="w-3.5 h-3.5 text-red-600" />
                        <span
                            class="text-xs font-semibold text-red-700 dark:text-red-300">{{ __(':count missed', ['count' => $d['missed_count']]) }}</span>
                    </div>
                @endif
                <div
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <x-heroicon-o-currency-dollar class="w-3.5 h-3.5 text-gray-500" />
                    <span
                        class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __(':amount/month', ['amount' => \App\Support\UiNumber::sar($d['monthly_contrib'])]) }}</span>
                </div>
            </div>

            {{-- Month grid --}}
            <div class="px-5 pt-3 pb-2 grid grid-cols-6 gap-1.5">
                @foreach($d['grid'] as $cell)
                    @php
                        $tip = $cell['label'] . ': ';
                        if ($cell['future'] ?? false) {
                            $tip .= __('Future');
                        } elseif (!($cell['paid'] ?? false)) {
                            $tip .= __('Missed');
                        } elseif ($cell['late'] ?? false) {
                            $tip .= __('Late (after deadline) — SAR :amount', ['amount' => number_format($cell['amount'], 2)]);
                        } elseif (!empty($cell['underpaid'])) {
                            $tip .= __('Short — SAR :amount / :monthly', ['amount' => number_format($cell['amount'], 2), 'monthly' => number_format($d['monthly_contrib'])]);
                        } else {
                            $tip .= __('Paid — SAR :amount', ['amount' => number_format($cell['amount'], 2)]);
                        }
                    @endphp
                    <div class="group relative" title="{{ $tip }}">
                        <div class="h-8 rounded-md cursor-default
                                    @if($cell['future'] ?? false) bg-gray-100 dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-700
                                    @elseif(!($cell['paid'] ?? false)) bg-red-200 dark:bg-red-900/50 border border-red-300 dark:border-red-700
                                    @elseif($cell['late'] ?? false) bg-amber-200 dark:bg-amber-900/50 border border-amber-300 dark:border-amber-600
                                    @elseif(!empty($cell['underpaid'])) bg-orange-200 dark:bg-orange-900/40 border border-orange-300 dark:border-orange-700
                                    @else bg-emerald-200 dark:bg-emerald-900/50 border border-emerald-300 dark:border-emerald-700
                                    @endif">
                        </div>
                        <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-0.5 leading-none">{{ $cell['label'] }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Chart-free fallback bars (works even when Chart.js is not bundled) --}}
            @php
                $values = $d['chart_data'] ?? [];
                $labels = $d['chart_labels'] ?? [];
                $max = max(1, (float) max($values ?: [0]));
            @endphp
            <div class="px-5 pb-5">
                <div class="h-36 mt-3 gap-1.5 items-end"
                    style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr));">
                    @foreach($values as $idx => $value)
                        @php
                            $amount = (float) $value;
                            $height = $max > 0 ? max(6, (int) round(($amount / $max) * 100)) : 6;
                            $isMissed = $amount <= 0.0;
                        @endphp
                        <div class="flex flex-col items-center justify-end gap-1 h-full"
                            title="{{ ($labels[$idx] ?? '') . ' — SAR ' . number_format($amount, 2) }}">
                            <div class="w-full h-full bg-gray-100/70 dark:bg-gray-700/40 rounded-sm relative overflow-hidden">
                                <div class="absolute bottom-0 left-0 right-0 rounded-t-sm {{ $isMissed ? 'bg-red-300/70 dark:bg-red-900/40' : 'bg-emerald-500/70' }}"
                                    style="height: {{ $height }}%;">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="gap-1.5 mt-2" style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr));">
                    @foreach($labels as $label)
                        <div class="text-[10px] text-gray-400 text-center"
                            style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $label }}">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Right: Loans + Installments ────────────────────────────────────── --}}
        <div class="flex flex-col gap-4">

            {{-- Loans summary --}}
            @if(count($d['loans']) > 0)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 px-5 py-3 flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center">
                            <x-heroicon-o-credit-card class="w-3.5 h-3.5 text-white" />
                        </div>
                        <h3 class="font-semibold text-white text-sm">{{ __('Loan History') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($d['loans'] as $loan)
                            @php
                                $statusClasses = match ($loan['status']) {
                                    'active', 'disbursed' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300',
                                    'paid' => 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
                                    'pending', 'approved' => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
                                    default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                                };
                            @endphp
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                                        <span
                                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400">#{{ $loan['id'] }}</span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ \App\Support\UiNumber::sar($loan['amount']) }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ __('Applied :date', ['date' => $loan['applied_at']]) }}
                                            @if($loan['fully_paid_at']) ·
                                            {{ __('Paid :date', ['date' => $loan['fully_paid_at']]) }}@endif
                                        </p>
                                    </div>
                                </div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $statusClasses }} flex-shrink-0">
                                    {{ ucfirst($loan['status']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recent installments --}}
            @if(count($d['installments']) > 0)
                <div
                    class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden flex-1">
                    <div class="bg-gradient-to-r from-cyan-600 to-blue-700 px-5 py-3 flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center">
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-white" />
                        </div>
                        <h3 class="font-semibold text-white text-sm">{{ __('Recent Installments') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($d['installments'] as $inst)
                            @php
                                $ic = match ($inst['status']) {
                                    'paid' => $inst['is_late'] ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300'
                                    : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
                                    'overdue' => 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300',
                                    default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                                };
                                $iLabel = $inst['status'] === 'paid' && $inst['is_late'] ? __('Paid Late') : __(ucfirst($inst['status']));
                            @endphp
                            <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        {{ \App\Support\UiNumber::sar($inst['amount']) }}
                                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">Loan
                                            #{{ $inst['loan_id'] }}</span>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ __('Due :date', ['date' => $inst['due_date']]) }}
                                        @if($inst['paid_at']) · {{ __('Paid :date', ['date' => $inst['paid_at']]) }}@endif
                                    </p>
                                </div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $ic }} flex-shrink-0">
                                    {{ $iLabel }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div
                    class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 flex flex-col items-center justify-center text-center gap-2">
                    <x-heroicon-o-arrow-path class="w-10 h-10 text-gray-200 dark:text-gray-700" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">{{ __('No loan installments yet.') }}</p>
                </div>
            @endif
        </div>

    </div>
@endif