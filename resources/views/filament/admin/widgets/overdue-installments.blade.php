@php $d = $this->getData(); @endphp

@if($d['total_count'] === 0)
    {{-- Nothing overdue — quiet success state --}}
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/10 ring-1 ring-emerald-200 dark:ring-emerald-800 p-5 flex items-center gap-3">
        <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-500 flex-shrink-0" />
        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">No overdue installments — all repayments are on track.</p>
    </div>
@else
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-red-50/60 dark:bg-red-900/10">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500" />
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Overdue Installments</h3>
                <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/50 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:text-red-300">
                    {{ number_format($d['total_count']) }} total
                </span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">
                    Principal: <strong class="text-red-600 dark:text-red-400">﷼ {{ number_format($d['total_overdue_amount'], 0) }}</strong>
                </span>
                @if($d['total_overdue_fees'] > 0)
                <span class="text-gray-500 dark:text-gray-400">
                    Late fees: <strong class="text-orange-600 dark:text-orange-400">﷼ {{ number_format($d['total_overdue_fees'], 0) }}</strong>
                </span>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/80">
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Borrower</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Loan / Tier</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Inst #</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Due Date</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-right">Amount</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-right">Late Fee</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-center">Days Late</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($d['items'] as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['borrower'] }}</p>
                            <p class="text-xs text-gray-400">{{ $item['member_number'] }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if($item['loan_url'])
                            <a href="{{ $item['loan_url'] }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                Loan #{{ $item['loan_id'] }}
                            </a>
                            @else
                            <span class="text-gray-500">—</span>
                            @endif
                            <p class="text-xs text-gray-400">{{ $item['loan_tier'] }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300">#{{ $item['installment_no'] }}</td>
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $item['due_date'] }}</td>
                        <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-white">
                            ﷼ {{ number_format($item['amount'], 0) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($item['late_fee'] > 0)
                            <span class="font-medium text-orange-600 dark:text-orange-400">﷼ {{ number_format($item['late_fee'], 0) }}</span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $item['days_overdue'] >= 30 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' :
                                   ($item['days_overdue'] >= 10 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300' :
                                   'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300') }}">
                                {{ $item['days_overdue'] }}d
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($d['total_count'] > 15)
        <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500 text-center">
            Showing 15 of {{ number_format($d['total_count']) }} overdue installments — open Finance → Loans to view all.
        </div>
        @endif
    </div>
@endif
