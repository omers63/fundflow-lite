@php $d = $d ?? $this->getData(); @endphp

@if(!($d['hasRecord'] ?? false))
    <div class="p-4 text-gray-400 text-sm">No member selected.</div>
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
                <h3 class="font-semibold text-white">Contribution History</h3>
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/80">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm bg-emerald-300 inline-block"></span> Paid
                </span>
                <span class="flex items-center gap-1" title="Flagged is_late on the contribution (after deadline)">
                    <span class="w-3 h-3 rounded-sm bg-amber-300 inline-block"></span> Late
                </span>
                <span class="flex items-center gap-1" title="Paid but below monthly allocation">
                    <span class="w-3 h-3 rounded-sm bg-orange-300 inline-block"></span> Short
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm bg-red-300/70 inline-block"></span> Missed
                </span>
            </div>
        </div>

        {{-- Summary chips --}}
        <div class="px-5 pt-4 flex flex-wrap gap-2">
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700">
                <x-heroicon-o-check-circle class="w-3.5 h-3.5 text-emerald-600" />
                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ $d['paid_count'] }} paid (last 12 months)</span>
            </div>
            @if($d['missed_count'] > 0)
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700">
                <x-heroicon-o-x-circle class="w-3.5 h-3.5 text-red-600" />
                <span class="text-xs font-semibold text-red-700 dark:text-red-300">{{ $d['missed_count'] }} missed</span>
            </div>
            @endif
            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <x-heroicon-o-currency-dollar class="w-3.5 h-3.5 text-gray-500" />
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">SAR {{ number_format($d['monthly_contrib']) }}/month</span>
            </div>
        </div>

        {{-- Month grid --}}
        <div class="px-5 pt-3 pb-2 grid grid-cols-6 gap-1.5">
            @foreach($d['grid'] as $cell)
            @php
                $tip = $cell['label'].': ';
                if ($cell['future'] ?? false) { $tip .= 'Future'; }
                elseif (!($cell['paid'] ?? false)) { $tip .= 'Missed'; }
                elseif ($cell['late'] ?? false) { $tip .= 'Late (after deadline) — SAR '.number_format($cell['amount'], 2); }
                elseif (!empty($cell['underpaid'])) { $tip .= 'Short — SAR '.number_format($cell['amount'], 2).' / '.number_format($d['monthly_contrib']); }
                else { $tip .= 'Paid — SAR '.number_format($cell['amount'], 2); }
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
                <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-0.5 leading-none">{{ $cell['label'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Bar chart: guard Chart.js; wire:ignore avoids morph issues (see contribution-trend widget) --}}
        <div
            class="px-5 pb-5"
            wire:ignore
            x-data="{
                chart: null,
                init() {
                    this.$nextTick(() => {
                        if (typeof Chart === 'undefined') return;
                        const isDark = document.documentElement.classList.contains('dark');
                        const gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
                        const textColor = isDark ? '#9ca3af' : '#6b7280';
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: {
                                labels: {{ json_encode($d['chart_labels']) }},
                                datasets: [{
                                    label: 'Amount',
                                    data: {{ json_encode($d['chart_data']) }},
                                    backgroundColor: function(ctx) {
                                        const val = ctx.dataset.data[ctx.dataIndex];
                                        return val > 0 ? 'rgba(16,185,129,0.7)' : 'rgba(239,68,68,0.5)';
                                    },
                                    borderRadius: 4,
                                    borderSkipped: false,
                                }]
                            },
                            options: {
                                responsive: true, maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 } } },
                                    y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 }, callback: v => 'SAR '+v } }
                                }
                            }
                        });
                    });
                },
                destroy() { if (this.chart) { this.chart.destroy(); this.chart = null; } }
            }">
            <div class="h-36 mt-3">
                <canvas x-ref="canvas"></canvas>
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
                <h3 class="font-semibold text-white text-sm">Loan History</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($d['loans'] as $loan)
                @php
                    $statusClasses = match($loan['status']) {
                        'active','disbursed' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300',
                        'paid' => 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
                        'pending','approved' => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                    };
                @endphp
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">#{{ $loan['id'] }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">SAR {{ $loan['amount'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Applied {{ $loan['applied_at'] }}
                                @if($loan['fully_paid_at']) · Paid {{ $loan['fully_paid_at'] }}@endif
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
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden flex-1">
            <div class="bg-gradient-to-r from-cyan-600 to-blue-700 px-5 py-3 flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-white/20 flex items-center justify-center">
                    <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-white" />
                </div>
                <h3 class="font-semibold text-white text-sm">Recent Installments</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($d['installments'] as $inst)
                @php
                    $ic = match($inst['status']) {
                        'paid' => $inst['is_late'] ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300'
                                                   : 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300',
                        'overdue' => 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300',
                        default  => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                    };
                    $iLabel = $inst['status'] === 'paid' && $inst['is_late'] ? 'Paid Late' : ucfirst($inst['status']);
                @endphp
                <div class="px-4 py-2.5 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-900 dark:text-white">
                            SAR {{ $inst['amount'] }}
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">Loan #{{ $inst['loan_id'] }}</span>
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Due {{ $inst['due_date'] }}
                            @if($inst['paid_at']) · Paid {{ $inst['paid_at'] }}@endif
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
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 flex flex-col items-center justify-center text-center gap-2">
            <x-heroicon-o-arrow-path class="w-10 h-10 text-gray-200 dark:text-gray-700" />
            <p class="text-sm text-gray-400 dark:text-gray-500">No loan installments yet.</p>
        </div>
        @endif
    </div>

</div>
@endif
