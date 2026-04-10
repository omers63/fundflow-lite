@php
    $d = $this->getData();
    $maxDaily = max(1, collect($d['daily_posted'])->max('count'));
@endphp

<div class="w-full space-y-4">
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-2xl bg-gradient-to-br from-primary-600 to-primary-700 p-6 text-white shadow-md ring-1 ring-white/15 dark:from-primary-700 dark:to-primary-900">
            <p class="text-xs uppercase tracking-[0.14em] text-white/85">Master Cash</p>
            <p class="mt-2 text-3xl font-semibold text-white">SAR {{ number_format($d['master_cash'], 2) }}</p>
            <p class="mt-3 text-xs text-white/85">Live ledger balance</p>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-emerald-700 to-cyan-900 p-6 text-white shadow-md ring-1 ring-white/15">
            <p class="text-xs uppercase tracking-[0.14em] text-white/85">Master Fund</p>
            <p class="mt-2 text-3xl font-semibold text-white">SAR {{ number_format($d['master_fund'], 2) }}</p>
            <p class="mt-3 text-xs text-white/85">Funding pool snapshot</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Posting Coverage</p>
            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($d['posted_rate'], 1) }}%</p>
            <div class="mt-4 space-y-3 text-xs">
                <div>
                    <div class="mb-1 flex items-center justify-between text-gray-500 dark:text-gray-400">
                        <span>Bank</span>
                        <span>{{ number_format($d['bank_posted_rate'], 1) }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-primary-500" style="width: {{ $d['bank_posted_rate'] }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between text-gray-500 dark:text-gray-400">
                        <span>SMS</span>
                        <span>{{ number_format($d['sms_posted_rate'], 1) }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-teal-500" style="width: {{ $d['sms_posted_rate'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending Queue</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Bank rows</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['bank_pending']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">SAR {{ number_format($d['bank_pending_amount'], 2) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                    <p class="text-xs text-gray-500 dark:text-gray-400">SMS rows</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ number_format($d['sms_pending']) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">SAR {{ number_format($d['sms_pending_amount'], 2) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Loan Reconciliation</p>
            <div class="mt-3 space-y-3">
                <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                    <p class="text-xs text-emerald-700 dark:text-emerald-300">Linked debit postings</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-800 dark:text-emerald-200">{{ number_format($d['loan_linked_debits']) }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <p class="text-xs text-amber-700 dark:text-amber-300">Needs link</p>
                    <p class="mt-1 text-xl font-semibold text-amber-800 dark:text-amber-200">{{ number_format($d['unlinked_debits']) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Posted Activity (Last 7 Days)</p>
            <div class="mt-4 flex h-24 items-end gap-2">
                @foreach ($d['daily_posted'] as $day)
                    @php $h = max(8, (int) round(($day['count'] / $maxDaily) * 100)); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <span class="text-[10px] text-gray-400">{{ $day['count'] > 0 ? $day['count'] : '' }}</span>
                        <div class="w-full rounded-md bg-primary-500/85 dark:bg-primary-400/80" style="height: {{ $h }}%"></div>
                        <span class="text-[10px] text-gray-400">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

