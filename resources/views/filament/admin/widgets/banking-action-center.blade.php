@php
    $d = $this->getData();
    $locale = app()->getLocale();
@endphp

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Action Center') }}</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Prioritized operational checks') }}</span>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-3 py-2 dark:border-red-500/30 dark:bg-red-500/10">
                <span class="text-sm text-red-700 dark:text-red-300">{{ __('Failed import sessions (Bank + SMS)') }}</span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-white/10 dark:text-red-300">
                    {{ $d['failed_bank_sessions'] + $d['failed_sms_sessions'] }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-500/30 dark:bg-amber-500/10">
                <span class="text-sm text-amber-700 dark:text-amber-300">{{ __('Duplicate bank rows waiting review') }}</span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-white/10 dark:text-amber-300">
                    {{ $d['bank_duplicate_queue'] }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 dark:border-sky-500/30 dark:bg-sky-500/10">
                <span class="text-sm text-sky-700 dark:text-sky-300">{{ __('Posted debits missing reconciliation link') }}</span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-white/10 dark:text-sky-300">
                    {{ $d['debit_needs_link'] }}
                </span>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{{ __('High-Value Unposted Bank Transactions') }}</h3>

        <div class="space-y-2">
            @forelse ($d['high_value_unposted'] as $tx)
                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $tx->bank?->name ?? __('Bank') }} · {{ $tx->transaction_date?->locale($locale)->translatedFormat('d M Y') ?? __('—') }}
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $tx->reference ?: ($tx->description ?: __('No reference')) }}
                        </p>
                    </div>
                    <span class="ml-3 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('SAR :amount', ['amount' => number_format((float) $tx->amount, 2)]) }}
                    </span>
                </div>
            @empty
                <div class="rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    {{ __('No unposted high-value bank transactions.') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60 xl:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{{ __('Latest Linked Debit Reconciliations') }}</h3>
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($d['latest_linked_debits'] as $tx)
                <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/5">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('SAR :amount', ['amount' => number_format((float) $tx->amount, 2)]) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $tx->posted_at?->locale($locale)->translatedFormat('d M Y H:i') ?? __('—') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                        {{ $tx->member?->user?->name ?? __('—') }} · {{ __('Loan #:id', ['id' => $tx->loan_id ?? __('—')]) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Disbursement #:id', ['id' => $tx->loan_disbursement_id ?? __('—')]) }}
                    </p>
                </div>
            @empty
                <div class="rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400 xl:col-span-3">
                    {{ __('No linked debit reconciliations yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>

