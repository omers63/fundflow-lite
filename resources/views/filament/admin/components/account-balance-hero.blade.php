@php
    $isLoan     = $record->type === \App\Models\Account::TYPE_LOAN;
    $balanceAbs = abs($balance);
    $balanceFmt = number_format($balance, 2);
    $net30      = $credits30 - $debits30;
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-1">

    {{-- Current balance / outstanding --}}
    <div class="relative overflow-hidden rounded-2xl {{ $isLoan ? 'bg-gradient-to-br from-amber-600 to-amber-700' : ($balance >= 0 ? 'bg-gradient-to-br from-emerald-600 to-emerald-700' : 'bg-gradient-to-br from-red-600 to-red-700') }} p-6 text-white shadow-lg sm:col-span-1">
        <div class="absolute inset-0 opacity-10">
            <x-heroicon-o-banknotes class="absolute -bottom-4 -right-4 w-32 h-32" />
        </div>
        <p class="text-sm font-medium text-white/80 uppercase tracking-wide">
            {{ $isLoan ? 'Outstanding Balance' : 'Current Balance' }}
        </p>
        <p class="mt-2 text-4xl font-extrabold tracking-tight">
            SAR {{ $isLoan ? number_format($outstanding, 2) : $balanceFmt }}
        </p>
        <p class="mt-2 text-sm text-white/70">
            {{ $isLoan ? 'Remaining loan to be repaid' : ($balance >= 0 ? 'Available balance' : 'Overdrawn') }}
        </p>
    </div>

    {{-- 30-day credits --}}
    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-emerald-500"></div>
        <div class="pl-2">
            <div class="flex items-center gap-1.5 mb-2">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4 text-emerald-500" />
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Credits (30d)</p>
            </div>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">SAR {{ number_format($credits30, 2) }}</p>
            <p class="mt-0.5 text-xs text-gray-400">{{ $txCount30 }} transaction{{ $txCount30 !== 1 ? 's' : '' }} total</p>
        </div>
    </div>

    {{-- 30-day debits --}}
    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-red-500"></div>
        <div class="pl-2">
            <div class="flex items-center gap-1.5 mb-2">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4 text-red-500" />
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Debits (30d)</p>
            </div>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">SAR {{ number_format($debits30, 2) }}</p>
            <p class="mt-0.5 text-xs {{ $net30 >= 0 ? 'text-emerald-500' : 'text-red-400' }}">
                Net: {{ $net30 >= 0 ? '+' : '' }}SAR {{ number_format($net30, 2) }}
            </p>
        </div>
    </div>

    {{-- Total lifetime transactions --}}
    <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 p-5 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm">
        <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl bg-primary-500"></div>
        <div class="pl-2">
            <div class="flex items-center gap-1.5 mb-2">
                <x-heroicon-o-document-text class="w-4 h-4 text-primary-500" />
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ledger Entries</p>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalTx) }}</p>
            <p class="mt-0.5 text-xs text-gray-400">All-time transactions</p>
        </div>
    </div>

</div>

{{-- Recent transactions mini-table --}}
@if($recent->isNotEmpty())
<div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden mb-1">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/70">
        <x-heroicon-o-clock class="w-4 h-4 text-gray-400" />
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent Ledger Entries</h4>
        <span class="ml-auto text-xs text-gray-400">Last {{ $recent->count() }}</span>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 w-36">Date</th>
                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 w-20">Type</th>
                <th class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-400 w-32">Amount</th>
                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Description</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
            @foreach($recent as $tx)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
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
                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300 truncate max-w-xs">
                    {{ $tx->description ?? '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
