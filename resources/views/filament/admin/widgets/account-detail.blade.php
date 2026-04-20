@php
    $d = $this->getData();
    $pollingInterval = $this->getPollingInterval();
@endphp

<div
    class="account-detail-widget w-full min-w-0 space-y-4 mb-2"
    @if (filled($pollingInterval))
        wire:poll.{{ $pollingInterval }}
    @endif
>
    @if(empty($d))
        <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Select an account to see balance and ledger summary.
        </div>
    @else
    @php
        $record  = $d['record'];
        $balance = $d['balance'];
        $net30   = $d['credits30'] - $d['debits30'];
    @endphp

    {{-- ── KPI row (four matching cards) ─────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Current / outstanding balance (same shell as Credits / Debits / Ledger) --}}
        <div class="relative overflow-hidden col-span-2 sm:col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div @class([
                'absolute inset-y-0 left-0 w-1 rounded-l-xl',
                'bg-amber-500' => $d['isLoan'],
                'bg-emerald-500' => ! $d['isLoan'] && $balance >= 0,
                'bg-red-500' => ! $d['isLoan'] && $balance < 0,
            ])></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    @if($d['isLoan'])
                        <x-heroicon-o-banknotes class="w-4 h-4 text-amber-500" />
                    @elseif($balance >= 0)
                        <x-heroicon-o-banknotes class="w-4 h-4 text-emerald-500" />
                    @else
                        <x-heroicon-o-banknotes class="w-4 h-4 text-red-500" />
                    @endif
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $d['isLoan'] ? 'Outstanding Balance' : 'Current Balance' }}
                    </p>
                </div>
                @if($d['isLoan'])
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">
                        SAR {{ number_format($d['outstanding'], 2) }}
                    </p>
                @elseif($balance >= 0)
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        SAR {{ number_format($balance, 2) }}
                    </p>
                @else
                    <p class="text-xl font-bold text-red-600 dark:text-red-400">
                        SAR {{ number_format($balance, 2) }}
                    </p>
                @endif
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                    {{ $d['isLoan'] ? 'Remaining to be repaid' : ($balance >= 0 ? 'Available balance' : 'Overdrawn') }}
                </p>
            </div>
        </div>

        {{-- Credits 30d --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4 text-emerald-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits (30d)</p>
                </div>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">SAR {{ number_format($d['credits30'], 2) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">{{ $d['txCount30'] }} entries this period</p>
            </div>
        </div>

        {{-- Debits 30d --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-red-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4 text-red-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Debits (30d)</p>
                </div>
                <p class="text-xl font-bold text-red-600 dark:text-red-400">SAR {{ number_format($d['debits30'], 2) }}</p>
                <p class="mt-0.5 text-xs {{ $net30 >= 0 ? 'text-emerald-500' : 'text-red-400' }}">
                    Net: {{ $net30 >= 0 ? '+' : '' }}SAR {{ number_format($net30, 2) }}
                </p>
            </div>
        </div>

        {{-- Total ledger entries --}}
        <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
            <div class="pl-2">
                <div class="flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-document-text class="w-4 h-4 text-primary-500" />
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ledger Entries</p>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($d['totalTx']) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">All-time transactions</p>
            </div>
        </div>

    </div>

    {{-- ── Recent transactions mini-table ─────────────────────────────────── --}}
    @if($d['recent']->isNotEmpty())
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
            <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent Ledger Entries</h4>
            <span class="ml-auto text-xs text-gray-400">Latest {{ $d['recent']->count() }}</span>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Date</th>
                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Type</th>
                    <th class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-400">Amount</th>
                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 hidden sm:table-cell">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                @foreach($d['recent'] as $tx)
                <tr class="transition-colors odd:bg-white even:bg-gray-50/90 dark:odd:bg-gray-800 dark:even:bg-gray-900/35 hover:bg-gray-100 dark:hover:bg-gray-700/40">
                    <td class="px-5 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {{ $tx->transacted_at?->format('d M Y H:i') ?? '—' }}
                    </td>
                    <td class="px-5 py-3">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300' => $tx->entry_type === 'credit',
                            'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'                => $tx->entry_type === 'debit',
                        ])>
                            {{ ucfirst($tx->entry_type) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-semibold {{ $tx->entry_type === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        SAR {{ number_format($tx->amount, 2) }}
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300 truncate max-w-xs hidden sm:table-cell">
                        {{ $tx->description ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @endif
</div>
