<x-filament-panels::page>
    @php
        $fundTiers        = $this->getFundTiers();
        $pendingApps      = $this->getPendingApplications();
        $totalPending     = $pendingApps->count();
        $totalQueued      = 0;
        $totalAvailable   = 0;
        foreach ($fundTiers as $__ft) {
            $totalQueued    += $this->getQueueForTier($__ft->id)->count();
            $totalAvailable += $__ft->available_amount;
        }
    @endphp

    {{-- ── Hero summary bar ── --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 dark:text-amber-400">Pending requests</p>
            <p class="mt-2 text-4xl font-extrabold tabular-nums text-amber-700 dark:text-amber-300">{{ $totalPending }}</p>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm dark:border-sky-500/30 dark:bg-sky-500/10">
            <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 dark:text-sky-400">In-tier queue</p>
            <p class="mt-2 text-4xl font-extrabold tabular-nums text-sky-700 dark:text-sky-300">{{ $totalQueued }}</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-white/40">Active fund tiers</p>
            <p class="mt-2 text-4xl font-extrabold tabular-nums text-gray-900 dark:text-white">{{ $fundTiers->count() }}</p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Section 1: Incoming Requests (pending + not fully disbursed)
         ══════════════════════════════════════════════════════════════════ --}}
    <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm dark:border-amber-500/30 dark:bg-gray-900">

        {{-- Section header --}}
        <div class="flex items-center justify-between border-b border-amber-100 bg-amber-50/80 px-6 py-5 dark:border-amber-500/10 dark:bg-amber-500/[0.04]">
            <div class="flex items-center gap-3">
                {{-- inbox icon --}}
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-amber-700 dark:text-amber-400">
                        <path d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z" />
                        <path d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Incoming Requests</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Loan applications awaiting review or final disbursement completion</p>
                </div>
            </div>
            @if($totalPending > 0)
                <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                    {{ $totalPending }} incoming
                </span>
            @endif
        </div>

        @if($pendingApps->isEmpty())
            <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
                <div class="rounded-full bg-emerald-50 p-4 dark:bg-emerald-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-emerald-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">No pending applications</p>
                    <p class="mt-0.5 text-sm text-gray-400 dark:text-gray-500">All loan requests have been reviewed.</p>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Member</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Purpose</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Requested</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Loan / Fund Tier</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Emergency</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Disbursement</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Applied</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingApps as $index => $loan)
                            @php
                                $loanUrl          = $this->loanViewUrl($loan);
                                $expectedLoanTier = \App\Models\LoanTier::forAmount((float) $loan->amount_requested);
                                $expectedFundTier = $loan->is_emergency
                                    ? \App\Models\FundTier::emergency()
                                    : ($expectedLoanTier ? \App\Models\FundTier::forLoanTier($expectedLoanTier->id) : null);
                                $displayLoanTier  = $loan->loanTier ?? $expectedLoanTier;
                                $displayFundTier  = $loan->fundTier ?? $expectedFundTier;
                                $approved         = (float) ($loan->amount_approved ?? 0);
                                $disbursed        = (float) ($loan->amount_disbursed ?? 0);
                                $disbursementPct  = $approved > 0 ? min(100, round($disbursed / $approved * 100)) : 0;
                            @endphp
                            <tr class="{{ $index % 2 === 0 ? '' : 'bg-amber-50/40 dark:bg-amber-500/[0.03]' }} border-b border-gray-100 transition-colors last:border-0 hover:bg-amber-50/60 dark:border-white/5 dark:hover:bg-amber-500/5">
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 text-xs font-bold">
                                        {{ $index + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $loan->member->user->name }}</p>
                                    <p class="text-xs text-gray-400 dark:text-white/40">{{ $loan->member->member_number }}</p>
                                </td>
                                <td class="max-w-[16rem] px-4 py-3.5 text-gray-600 dark:text-gray-300">
                                    <span class="line-clamp-2" title="{{ $loan->purpose }}">{{ $loan->purpose }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-white">
                                        SAR {{ number_format($loan->amount_requested, 0) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($displayLoanTier)
                                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $displayLoanTier->label }}</p>
                                        <p class="text-xs text-gray-400 dark:text-white/40">
                                            {{ $displayFundTier ? $displayFundTier->label : '⚠ No fund tier' }}
                                        </p>
                                    @else
                                        <span class="text-xs text-red-500 dark:text-red-400 font-medium">⚠ Out of tier range</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($loan->is_emergency)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/20 dark:text-red-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                <path d="M11.983 1.907a.75.75 0 0 0-1.292-.657l-8.5 9.5A.75.75 0 0 0 2.75 12h6.572l-1.305 6.093a.75.75 0 0 0 1.292.657l8.5-9.5A.75.75 0 0 0 17.25 8h-6.572l1.305-6.093Z" />
                                            </svg>
                                            Emergency
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-300 dark:text-white/20">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($approved <= 0)
                                        <span class="text-xs text-gray-400 dark:text-white/40">Pending</span>
                                    @else
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                                    <div class="h-full rounded-full {{ $disbursementPct >= 100 ? 'bg-emerald-500' : 'bg-amber-400' }}" style="width: {{ $disbursementPct }}%"></div>
                                                </div>
                                                <span class="text-xs {{ $disbursementPct >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} font-semibold whitespace-nowrap">{{ $disbursementPct }}%</span>
                                            </div>
                                            <span class="text-xs text-gray-400 dark:text-white/40 whitespace-nowrap tabular-nums">
                                                SAR {{ number_format($disbursed, 0) }} / {{ number_format($approved, 0) }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-xs text-gray-400 dark:text-white/40">
                                    {{ $loan->applied_at->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if($loanUrl)
                                        <a href="{{ $loanUrl }}"
                                           class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                                            Review
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3 w-3">
                                                <path fill-rule="evenodd" d="M6.22 4.22a.75.75 0 0 1 1.06 0l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06L8.94 8 6.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Section 2: Per-fund-tier queue (approved + active loans)
         ══════════════════════════════════════════════════════════════════ --}}
    @forelse($fundTiers as $ft)
        @php
            $queue       = $this->getQueueForTier($ft->id);
            $isEmergency = $ft->isEmergency();
            $exposure    = $ft->active_exposure;
            $available   = $ft->available_amount;
            $pct         = $exposure + $available > 0
                ? round($exposure / ($exposure + $available) * 100)
                : 0;
        @endphp

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">

            {{-- Card header --}}
            <div class="flex items-start justify-between border-b border-gray-100 bg-gray-50/80 px-6 py-5 dark:border-white/5 dark:bg-white/[0.03]">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $ft->label }}</h2>

                        @if($isEmergency)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                    <path d="M11.983 1.907a.75.75 0 0 0-1.292-.657l-8.5 9.5A.75.75 0 0 0 2.75 12h6.572l-1.305 6.093a.75.75 0 0 0 1.292.657l8.5-9.5A.75.75 0 0 0 17.25 8h-6.572l1.305-6.093Z" />
                                </svg>
                                Emergency
                            </span>
                        @endif

                        @if($queue->count() > 0)
                            <span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-800 dark:bg-primary-500/20 dark:text-primary-300">
                                {{ $queue->count() }} in queue
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $ft->percentage }}% of master fund
                    </p>
                </div>

                {{-- Availability stats --}}
                <div class="flex shrink-0 items-center gap-6 text-right">
                    <div>
                        <p class="text-xs font-medium text-gray-400 dark:text-white/40">Available</p>
                        <p class="mt-0.5 text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            SAR {{ number_format($available, 0) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400 dark:text-white/40">Active exposure</p>
                        <p class="mt-0.5 text-sm font-bold text-gray-700 dark:text-gray-200">
                            SAR {{ number_format($exposure, 0) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Capacity progress bar --}}
            <div class="h-1 bg-gray-100 dark:bg-white/10">
                <div
                    class="h-full {{ $isEmergency ? 'bg-amber-400' : 'bg-primary-500' }} transition-all"
                    style="width: {{ $pct }}%"
                ></div>
            </div>

            {{-- Queue table or empty state --}}
            @if($queue->isEmpty())
                <div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
                    <div class="rounded-full bg-emerald-50 p-4 dark:bg-emerald-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-8 w-8 text-emerald-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Queue is clear</p>
                        <p class="mt-0.5 text-sm text-gray-400 dark:text-gray-500">No approved or active loans in this tier.</p>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Pos.</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Member</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Purpose</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Loan tier</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Disbursement</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-white/40">Applied</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($queue as $index => $loan)
                                @php $loanUrl = $this->loanViewUrl($loan); @endphp
                                <tr class="{{ $index % 2 === 0 ? '' : 'bg-gray-50/60 dark:bg-white/[0.02]' }} border-b border-gray-100 transition-colors last:border-0 hover:bg-primary-50/40 dark:border-white/5 dark:hover:bg-primary-500/5">
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full {{ $isEmergency ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-primary-100 text-primary-800 dark:bg-primary-500/20 dark:text-primary-300' }} text-xs font-bold">
                                            {{ $loan->queue_position ?? $index + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $loan->member->user->name }}</p>
                                        <p class="text-xs text-gray-400 dark:text-white/40">{{ $loan->member->member_number }}</p>
                                    </td>
                                    <td class="max-w-[16rem] px-4 py-3.5 text-gray-600 dark:text-gray-300">
                                        <span class="line-clamp-2" title="{{ $loan->purpose }}">{{ $loan->purpose }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-500 dark:text-gray-400">
                                        {{ $loan->loanTier?->label ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="font-mono font-semibold tabular-nums text-gray-900 dark:text-white">
                                            SAR {{ number_format($loan->amount_approved ?? $loan->amount_requested, 0) }}
                                        </span>
                                    </td>
                                    {{-- Disbursement progress --}}
                                    <td class="px-4 py-3.5">
                                        @php
                                            $approved   = (float) ($loan->amount_approved ?? 0);
                                            $disbursed  = (float) ($loan->amount_disbursed ?? 0);
                                            $pct        = $approved > 0 ? min(100, round($disbursed / $approved * 100)) : 0;
                                            $isPartial  = $disbursed > 0 && $disbursed < $approved;
                                        @endphp
                                        @if($loan->status === 'active')
                                            <div class="flex items-center gap-2">
                                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                                    <div class="h-full rounded-full bg-emerald-500" style="width: 100%"></div>
                                                </div>
                                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold whitespace-nowrap">Full</span>
                                            </div>
                                        @elseif($isPartial)
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                    <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold whitespace-nowrap">{{ $pct }}%</span>
                                                </div>
                                                <span class="text-xs text-gray-400 dark:text-white/40 whitespace-nowrap tabular-nums">
                                                    SAR {{ number_format($disbursed, 0) }} / {{ number_format($approved, 0) }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-white/40">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($loan->status === 'approved')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-500/20 dark:text-sky-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                                Approved
                                            </span>
                                        @elseif($loan->status === 'active')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                {{ ucfirst($loan->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs text-gray-400 dark:text-white/40">
                                        {{ $loan->applied_at->format('d M Y') }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        @if($loanUrl)
                                            <a href="{{ $loanUrl }}"
                                               class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                                                View
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3 w-3">
                                                    <path fill-rule="evenodd" d="M6.22 4.22a.75.75 0 0 1 1.06 0l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06L8.94 8 6.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @empty
        <div class="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-20 text-center dark:border-white/10 dark:bg-white/[0.02]">
            <div class="rounded-full bg-gray-100 p-5 dark:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-gray-400 dark:text-white/30">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                </svg>
            </div>
            <div>
                <p class="text-base font-semibold text-gray-700 dark:text-gray-300">No active fund tiers configured</p>
                <p class="mt-1 max-w-sm text-sm text-gray-400 dark:text-gray-500">
                    Configure fund tiers in settings before loans can be queued and disbursed.
                </p>
            </div>
        </div>
    @endforelse

</x-filament-panels::page>
