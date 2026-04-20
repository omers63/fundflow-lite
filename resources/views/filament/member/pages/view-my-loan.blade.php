<x-filament-panels::page>
@php
    /** @var \App\Models\Loan $record */
    $loan = $this->getRecord();
    $loan->loadMissing(['loanTier', 'fundTier', 'guarantor.user', 'disbursements', 'installments']);

    // ── Timeline steps ──────────────────────────────────────────────────────
    $steps = [
        [
            'key'       => 'applied',
            'label'     => __('Applied'),
            'icon'      => 'heroicon-o-paper-airplane',
            'timestamp' => $loan->applied_at,
            'done'      => true,
            'color'     => 'primary',
        ],
        [
            'key'       => 'reviewed',
            'label'     => in_array($loan->status, ['rejected', 'cancelled']) ? __('Rejected / Cancelled') : __('Under Review'),
            'icon'      => in_array($loan->status, ['rejected', 'cancelled']) ? 'heroicon-o-x-circle' : 'heroicon-o-magnifying-glass',
            'timestamp' => null,
            'done'      => !in_array($loan->status, ['pending']),
            'color'     => in_array($loan->status, ['rejected', 'cancelled']) ? 'danger' : 'warning',
        ],
        [
            'key'       => 'approved',
            'label'     => __('Approved'),
            'icon'      => 'heroicon-o-check-circle',
            'timestamp' => $loan->approved_at,
            'done'      => in_array($loan->status, ['approved', 'active', 'completed', 'early_settled']),
            'color'     => 'success',
        ],
        [
            'key'       => 'disbursed',
            'label'     => __('Disbursed'),
            'icon'      => 'heroicon-o-banknotes',
            'timestamp' => $loan->disbursed_at,
            'done'      => in_array($loan->status, ['active', 'completed', 'early_settled']),
            'color'     => 'info',
        ],
        [
            'key'       => 'repaying',
            'label'     => __('Repaying'),
            'icon'      => 'heroicon-o-arrow-path',
            'timestamp' => null,
            'done'      => in_array($loan->status, ['active', 'completed', 'early_settled']),
            'color'     => 'info',
        ],
        [
            'key'       => 'settled',
            'label'     => in_array($loan->status, ['completed', 'early_settled']) ? __(ucfirst(str_replace('_', ' ', $loan->status))) : __('Settled'),
            'icon'      => 'heroicon-o-trophy',
            'timestamp' => $loan->settled_at,
            'done'      => in_array($loan->status, ['completed', 'early_settled']),
            'color'     => 'success',
        ],
    ];

    // Installments summary
    $totalInstallments = $loan->installments->count();
    $paidInstallments  = $loan->installments->where('status', 'paid')->count();
    $overdueCount      = $loan->installments->where('status', 'overdue')->count();
    $pendingCount      = $loan->installments->whereIn('status', ['pending', 'overdue'])->count();
    $paidPct           = $totalInstallments > 0 ? round($paidInstallments / $totalInstallments * 100) : 0;
@endphp

<div class="space-y-6">

    {{-- ── Status timeline ─────────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-6 shadow-sm overflow-x-auto">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-5">{{ __('Loan Status') }}</h3>
        <div class="flex items-start gap-0 min-w-max">
            @foreach($steps as $i => $step)
            <div class="flex flex-col items-center" style="min-width: 110px;">
                {{-- Connector line (before icon, except first) --}}
                <div class="flex items-center w-full mb-2">
                    @if($i > 0)
                    <div class="flex-1 h-0.5 {{ $step['done'] ? 'bg-primary-400 dark:bg-primary-600' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @else
                    <div class="flex-1"></div>
                    @endif

                    {{-- Step circle --}}
                    <div class="flex-shrink-0 h-9 w-9 rounded-full flex items-center justify-center ring-2
                        {{ $step['done']
                            ? 'bg-primary-500 dark:bg-primary-600 ring-primary-200 dark:ring-primary-800'
                            : 'bg-gray-100 dark:bg-gray-700 ring-gray-200 dark:ring-gray-600' }}">
                        <x-dynamic-component
                            :component="$step['icon']"
                            class="w-4 h-4 {{ $step['done'] ? 'text-white' : 'text-gray-400 dark:text-gray-500' }}" />
                    </div>

                    @if($i < count($steps) - 1)
                    <div class="flex-1 h-0.5 {{ $steps[$i+1]['done'] ? 'bg-primary-400 dark:bg-primary-600' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @else
                    <div class="flex-1"></div>
                    @endif
                </div>

                {{-- Label + timestamp --}}
                <p class="text-xs font-semibold text-center {{ $step['done'] ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ $step['label'] }}
                </p>
                @if($step['timestamp'])
                <p class="text-xs text-gray-400 dark:text-gray-500 text-center mt-0.5">
                    {{ \Carbon\Carbon::parse($step['timestamp'])->format('d M Y') }}
                </p>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Loan summary ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="rounded-xl bg-gradient-to-br from-sky-100 via-white to-indigo-50 dark:from-slate-800 dark:via-sky-950/35 dark:to-indigo-950/30 ring-1 ring-sky-200/80 dark:ring-sky-600/40 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300 mb-1">{{ __('Requested') }}</p>
            <p class="text-xl font-bold text-sky-900 dark:text-sky-100">{{ __('SAR') }} {{ number_format($loan->amount_requested, 0) }}</p>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Approved') }}</p>
            <p class="text-xl font-bold text-primary-600 dark:text-primary-400">
                {{ $loan->amount_approved ? __('SAR') . ' ' . number_format($loan->amount_approved, 0) : '—' }}
            </p>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Disbursed') }}</p>
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                {{ $loan->amount_disbursed ? __('SAR') . ' ' . number_format($loan->amount_disbursed, 0) : '—' }}
            </p>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">{{ __('Loan Tier') }}</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $loan->loanTier?->label ?? '—' }}</p>
            @if($loan->is_emergency)
            <span class="inline-flex items-center mt-1 rounded-full bg-red-100 dark:bg-red-900/40 px-2 py-0.5 text-xs font-semibold text-red-700 dark:text-red-300">
                {{ __('Emergency') }}
            </span>
            @endif
        </div>

    </div>

    {{-- ── Repayment progress (active loans only) ───────────────────────────── --}}
    @if($totalInstallments > 0)
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Repayment Progress') }}</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __(':paid / :total installments paid', ['paid' => $paidInstallments, 'total' => $totalInstallments]) }}
            </span>
        </div>

        <div class="w-full h-3 rounded-full bg-gray-100 dark:bg-gray-700 mb-3">
            <div class="h-3 rounded-full bg-primary-500 transition-all" style="width: {{ $paidPct }}%"></div>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                <x-heroicon-o-check-circle class="w-4 h-4" />
                {{ __(':count paid', ['count' => $paidInstallments]) }}
            </span>
            @if($overdueCount > 0)
            <span class="flex items-center gap-1 text-red-600 dark:text-red-400">
                <x-heroicon-o-exclamation-circle class="w-4 h-4" />
                {{ __(':count overdue', ['count' => $overdueCount]) }}
            </span>
            @endif
            @if($pendingCount > 0)
            <span class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-clock class="w-4 h-4" />
                {{ __(':count remaining', ['count' => $pendingCount]) }}
            </span>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Installments table ───────────────────────────────────────────────── --}}
    @if($loan->installments->count() > 0)
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/80">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Installments') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Due Date') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-right">{{ __('Amount') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 text-right">{{ __('Late Fee') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Paid On') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($loan->installments->sortBy('installment_number') as $inst)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors
                        {{ $inst->status === 'overdue' ? 'bg-red-50/30 dark:bg-red-900/5' : '' }}">
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300">#{{ $inst->installment_number }}</td>
                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                            {{ $inst->due_date ? \Carbon\Carbon::parse($inst->due_date)->format('d M Y') : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-white">
                            {{ __('SAR') }} {{ number_format((float)$inst->amount, 0) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if((float)$inst->late_fee_amount > 0)
                            <span class="text-orange-600 dark:text-orange-400 font-medium">
                                {{ __('SAR') }} {{ number_format((float)$inst->late_fee_amount, 2) }}
                            </span>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                {{ $inst->status === 'paid'    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' :
                                   ($inst->status === 'overdue' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' :
                                   'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300') }}">
                                {{ __(ucfirst($inst->status)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400">
                            {{ $inst->paid_at ? \Carbon\Carbon::parse($inst->paid_at)->format('d M Y') : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Loan details ────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 p-6 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">{{ __('Loan Details') }}</h3>
        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Applied on') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">
                    {{ $loan->applied_at ? $loan->applied_at->format('d M Y') : '—' }}
                </dd>
            </div>
            @if($loan->guarantor)
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Guarantor') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $loan->guarantor->user?->name ?? '—' }}</dd>
            </div>
            @endif
            @if($loan->purpose)
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Purpose') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $loan->purpose }}</dd>
            </div>
            @endif
            @if($loan->rejection_reason)
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Rejection reason') }}</dt>
                <dd class="font-medium text-red-600 dark:text-red-400">{{ $loan->rejection_reason }}</dd>
            </div>
            @endif
            @if($loan->cancellation_reason)
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Cancellation reason') }}</dt>
                <dd class="font-medium text-gray-700 dark:text-gray-300">{{ $loan->cancellation_reason }}</dd>
            </div>
            @endif
        </dl>
    </div>

</div>
</x-filament-panels::page>
