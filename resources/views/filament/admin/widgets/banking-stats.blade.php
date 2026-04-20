@php
    $d = $this->getData();
    $fmt = fn(int $v) => $v >= 1_000 ? number_format($v / 1_000, 1) . 'k' : (string) $v;
    $statusColors = [
        'completed'           => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'partially_completed' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'processing'          => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'failed'              => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        'pending'             => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    ];
@endphp

<div class="w-full max-w-none space-y-4 mb-2">

    {{-- ── KPI row ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">

        {{-- Registered banks --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-building-library class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Banks') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['banks'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':count active', ['count' => $d['active_banks']]) }}</p>
            </div>
        </div>

        {{-- Templates --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-indigo-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-document-text class="w-4 h-4 text-indigo-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Templates') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['bank_templates'] + $d['sms_templates'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':bank bank · :sms SMS', ['bank' => $d['bank_templates'], 'sms' => $d['sms_templates']]) }}</p>
            </div>
        </div>

        {{-- Total transactions --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-teal-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-arrows-right-left class="w-4 h-4 text-teal-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Transactions') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $fmt($d['total_tx']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':bank bank · :sms SMS', ['bank' => $fmt($d['bank_tx_total']), 'sms' => $fmt($d['sms_tx_total'])]) }}</p>
            </div>
        </div>

        {{-- Posted --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Posted') }}</p>
                </div>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $fmt($d['total_posted']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% of total', ['pct' => $d['post_rate']]) }}</p>
            </div>
        </div>

        {{-- Duplicates --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl {{ $d['total_dupes'] > 0 ? 'bg-amber-400' : 'bg-gray-200 dark:bg-gray-600' }}"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-document-duplicate class="w-4 h-4 {{ $d['total_dupes'] > 0 ? 'text-amber-500' : 'text-gray-400' }}" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Duplicates') }}</p>
                </div>
                <p class="text-2xl font-bold {{ $d['total_dupes'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $fmt($d['total_dupes']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ __(':pct% dupe rate', ['pct' => $d['dupe_rate']]) }}</p>
            </div>
        </div>

        {{-- Imports this month --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-sky-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4 text-sky-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Imports (mo.)') }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $d['bank_sessions_month'] + $d['sms_sessions_month'] }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ now()->format('F Y') }}</p>
            </div>
        </div>

    </div>

    {{-- ── Bottom row: 6-month trend + recent sessions ───────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        {{-- 6-month import trend --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('6-Month Import Activity') }}</h4>
            </div>
            <div class="px-5 py-4">
                @php $maxTotal = max(1, collect($d['trend'])->max('total')); @endphp
                <div class="flex items-end gap-3 h-20">
                    @foreach($d['trend'] as $t)
                    @php
                        $bankH = $maxTotal > 0 ? round($t['bank'] / $maxTotal * 100) : 0;
                        $smsH  = $maxTotal > 0 ? round($t['sms'] / $maxTotal * 100) : 0;
                    @endphp
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $t['total'] > 0 ? $t['total'] : '' }}</span>
                        <div class="w-full flex flex-col justify-end gap-0.5" style="height: {{ max(4, $bankH + $smsH) }}%">
                            @if($t['bank'] > 0)
                            <div class="w-full rounded-t-sm bg-indigo-500" style="height: {{ $bankH }}%"></div>
                            @endif
                            @if($t['sms'] > 0)
                            <div class="w-full {{ $t['bank'] > 0 ? '' : 'rounded-t-sm' }} rounded-b-sm bg-teal-400" style="height: {{ $smsH }}%"></div>
                            @endif
                            @if($t['total'] === 0)
                            <div class="w-full rounded-sm bg-gray-100 dark:bg-gray-700 h-1"></div>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400">{{ $t['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3 flex gap-4 text-xs text-gray-400">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>{{ __('Bank CSV') }}</span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-teal-400 inline-block"></span>{{ __('SMS') }}</span>
                </div>
            </div>
        </div>

        {{-- Recent import sessions --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
                <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Recent Bank Import Sessions') }}</h4>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($d['recent_sessions'] as $session)
                @php $sc = $statusColors[$session['status']] ?? $statusColors['pending']; @endphp
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $session['bank'] }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $session['filename'] }} · {{ $session['date'] }}</p>
                    </div>
                    <div class="text-right flex-shrink-0 space-y-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sc }}">
                            {{ str_replace('_', ' ', $session['status']) }}
                        </span>
                        <p class="text-xs text-gray-400">{{ __(':imported imported · :dupes dupes', ['imported' => $session['imported'], 'dupes' => $session['duplicates']]) }}</p>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center">
                    <x-heroicon-o-inbox class="mx-auto w-8 h-8 text-gray-300 dark:text-gray-600 mb-1" />
                    <p class="text-sm text-gray-400">{{ __('No import sessions yet.') }}</p>
                </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
