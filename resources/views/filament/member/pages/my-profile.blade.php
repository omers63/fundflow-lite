<x-filament-panels::page>
@php
    $user   = auth()->user();
    $member = $user?->member;
@endphp

<div class="space-y-6">

    {{-- ── Identity card ───────────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-gradient-to-br from-sky-700 via-sky-800 to-indigo-900 p-6 text-white shadow-lg ring-1 ring-sky-400/30">
        <div class="flex flex-col sm:flex-row sm:items-center gap-5">
            {{-- Avatar initials --}}
            <div class="flex-shrink-0 h-20 w-20 rounded-full bg-white/20 ring-2 ring-white/30 flex items-center justify-center text-3xl font-bold select-none">
                {{ strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-2xl font-bold leading-tight">{{ $user?->name }}</p>
                @if($member)
                <p class="mt-1 text-white/90 text-sm font-mono">{{ $member->member_number }}</p>
                @endif
                <div class="mt-2 flex flex-wrap gap-3 text-sm">
                    <span class="flex items-center gap-1 text-white/90">
                        <x-heroicon-o-envelope class="w-4 h-4" /> {{ $user?->email }}
                    </span>
                    @if($user?->phone)
                    <span class="flex items-center gap-1 text-white/90">
                        <x-heroicon-o-phone class="w-4 h-4" />
                        <span class="phone-ltr"><span class="phone-digits">{{ $user?->phone }}</span></span>
                    </span>
                    @endif
                </div>
            </div>
            @if($member)
            <div class="flex-shrink-0">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1
                    {{ $member->status === 'active' ? 'bg-emerald-400/25 text-white ring-emerald-200/40'
                        : 'bg-amber-400/25 text-white ring-amber-200/40' }}">
                    {{ ucfirst($member->status) }}
                </span>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Membership details ───────────────────────────────────────────────── --}}
    @if($member)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        <div class="rounded-xl bg-gradient-to-br from-indigo-100 via-sky-50 to-white dark:from-indigo-950/60 dark:via-sky-950/40 dark:to-slate-900 ring-1 ring-indigo-200/80 dark:ring-indigo-600/40 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Member Number</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white font-mono">{{ $member->member_number }}</p>
        </div>

        <div class="rounded-xl bg-gradient-to-br from-sky-100 via-white to-indigo-50 dark:from-slate-800 dark:via-sky-950/35 dark:to-indigo-950/30 ring-1 ring-sky-200/80 dark:ring-sky-600/40 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Member Since</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white">
                {{ $member->joined_at ? $member->joined_at->format('d M Y') : '—' }}
            </p>
            @if($member->joined_at)
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $member->joined_at->diffForHumans() }}</p>
            @endif
        </div>

        <div class="rounded-xl bg-gradient-to-br from-primary-100/90 via-white to-sky-50 dark:from-primary-950/50 dark:via-slate-900 dark:to-sky-950/30 ring-1 ring-primary-200/80 dark:ring-primary-700/40 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Monthly Contribution</p>
            <p class="text-xl font-bold text-primary-700 dark:text-primary-300">{{ __('SAR') }} {{ number_format($member->monthly_contribution_amount) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">per cycle</p>
        </div>

        @php
            $cashBalance = (float) ($member->cashAccount()?->balance ?? 0);
            $fundBalance = (float) ($member->fundAccount()?->balance ?? 0);
        @endphp
        <div class="rounded-xl bg-gradient-to-br from-emerald-100/80 via-white to-sky-50 dark:from-emerald-950/40 dark:via-slate-900 dark:to-sky-950/25 ring-1 ring-emerald-200/80 dark:ring-emerald-700/35 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Cash Balance</p>
            <p class="text-xl font-bold {{ $cashBalance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                {{ __('SAR') }} {{ number_format($cashBalance, 2) }}
            </p>
        </div>

        <div class="rounded-xl bg-gradient-to-br from-indigo-100/80 via-white to-violet-50 dark:from-indigo-950/45 dark:via-slate-900 dark:to-violet-950/25 ring-1 ring-indigo-200/80 dark:ring-indigo-600/40 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Fund Balance</p>
            <p class="text-xl font-bold text-indigo-700 dark:text-indigo-300">{{ __('SAR') }} {{ number_format($fundBalance, 2) }}</p>
        </div>

        <div class="rounded-xl bg-gradient-to-br from-amber-100/70 via-white to-slate-50 dark:from-amber-950/35 dark:via-slate-900 dark:to-slate-950/40 ring-1 ring-amber-200/70 dark:ring-amber-700/30 p-5 shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-300 mb-1">Compliance</p>
            @php
                $lateCount = $member->late_contributions_count ?? 0;
            @endphp
            @if($lateCount === 0)
            <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Good standing</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">No late contributions</p>
            @else
            <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ $lateCount }} late</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">contributions marked late</p>
            @endif
        </div>
    </div>

    {{-- ── Suspension notice ────────────────────────────────────────────────── --}}
    @if($member->delinquency_suspended_at)
    <div class="rounded-xl bg-orange-50 dark:bg-orange-900/20 ring-1 ring-orange-200 dark:ring-orange-800 p-5 flex gap-3">
        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" />
        <div>
            <p class="text-sm font-semibold text-orange-800 dark:text-orange-200">Account suspended due to delinquency</p>
            <p class="text-xs text-orange-700 dark:text-orange-300 mt-0.5">
                Suspended on {{ $member->delinquency_suspended_at->format('d M Y') }}.
                Contact your fund administrator to restore access.
            </p>
        </div>
    </div>
    @endif
    @endif

    {{-- ── Compliance & Standing ───────────────────────────────────────────── --}}
    @if($member)
    @php
        $totalContrib  = $member->contributions()->count();
        $lateContrib   = (int) ($member->late_contributions_count ?? 0);
        $lateRepay     = (int) ($member->late_repayment_count ?? 0);
        $onTime        = max(0, $totalContrib - $lateContrib);
        $compliancePct = $totalContrib > 0 ? (int) round($onTime / $totalContrib * 100) : 100;
        $scoreColor    = $compliancePct >= 90 ? 'emerald' : ($compliancePct >= 70 ? 'amber' : 'red');
    @endphp
    <div class="rounded-xl bg-gradient-to-br from-slate-50 via-white to-sky-50 dark:from-gray-900 dark:via-slate-900 dark:to-sky-950/25 ring-1 ring-slate-200/90 dark:ring-slate-600/40 shadow-md overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200/80 dark:border-slate-600/50 bg-white/40 dark:bg-white/5">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Compliance & Standing History</h3>
        </div>
        <div class="p-5">
            <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr class="py-2">
                        <td class="py-3 text-gray-500 dark:text-gray-400 w-48">Compliance Score</td>
                        <td class="py-3 font-semibold text-{{ $scoreColor }}-600 dark:text-{{ $scoreColor }}-400">{{ $compliancePct }}%</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Total Contributions</td>
                        <td class="py-3 font-semibold text-gray-900 dark:text-white">{{ $totalContrib }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Late Contributions</td>
                        <td class="py-3 font-semibold {{ $lateContrib > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $lateContrib }}
                            @if($lateContrib > 0)
                                <span class="text-xs text-gray-500 ml-1">({{ __('SAR') }} {{ number_format((float)$member->late_contributions_amount, 2) }} in fees)</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Late Loan Repayments</td>
                        <td class="py-3 font-semibold {{ $lateRepay > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $lateRepay }}
                            @if($lateRepay > 0)
                                <span class="text-xs text-gray-500 ml-1">({{ __('SAR') }} {{ number_format((float)$member->late_repayment_amount, 2) }} in fees)</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Account Status</td>
                        <td class="py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                {{ $member->status === 'active' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' :
                                   'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}">
                                {{ ucfirst($member->status) }}
                            </span>
                        </td>
                    </tr>
                    @if($member->delinquency_suspended_at)
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Suspended On</td>
                        <td class="py-3 font-semibold text-red-600 dark:text-red-400">{{ $member->delinquency_suspended_at->format('d F Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="py-3 text-gray-500 dark:text-gray-400">Member Since</td>
                        <td class="py-3 font-semibold text-gray-900 dark:text-white">
                            {{ $member->joined_at?->format('d F Y') ?? '—' }}
                            @if($member->joined_at)
                                <span class="text-xs text-gray-400 ml-1">({{ $member->joined_at->diffForHumans() }})</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Account security ────────────────────────────────────────────────── --}}
    <div class="rounded-xl bg-gradient-to-br from-slate-100/90 via-white to-sky-50 dark:from-slate-800 dark:via-gray-900 dark:to-sky-950/20 ring-1 ring-slate-200/80 dark:ring-slate-600/40 p-5 shadow-md">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Account Security</h3>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Email address</dt>
                <dd class="font-medium text-gray-900 dark:text-white">{{ $user?->email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Phone number</dt>
                <dd class="font-medium text-gray-900 dark:text-white">
                    @if($user?->phone)
                        <span class="phone-ltr"><span class="phone-digits">{{ $user->phone }}</span></span>
                    @else
                        — not set
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Password</dt>
                <dd class="font-medium text-gray-500">●●●●●●●● <span class="text-xs">(use "Change Password" above to update)</span></dd>
            </div>
        </dl>
    </div>

</div>
</x-filament-panels::page>
