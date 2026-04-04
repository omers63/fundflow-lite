@php $d = $this->getData(); @endphp

<div class="relative overflow-hidden rounded-2xl shadow-xl">
    {{-- Background gradient --}}
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-700 via-teal-800 to-cyan-900"></div>

    {{-- Decorative blobs --}}
    <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full bg-emerald-400 opacity-20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-12 -left-12 h-48 w-48 rounded-full bg-teal-300 opacity-20 blur-3xl"></div>
    <div class="pointer-events-none absolute top-6 left-1/3 h-28 w-28 rounded-full bg-cyan-300 opacity-10 blur-2xl"></div>

    {{-- Content --}}
    <div class="relative px-6 py-7 sm:px-8 sm:py-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">

            {{-- Greeting block --}}
            <div>
                <p class="text-sm font-medium text-emerald-200 tracking-wide">{{ $d['date'] }}</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-white">
                    {{ $d['greeting'] }}, {{ $d['name'] }}
                </h1>
                @if($d['hasMember'])
                <p class="mt-1.5 text-sm text-emerald-200">
                    Member <span class="font-semibold text-white">{{ $d['memberNumber'] }}</span> · Your financial dashboard
                </p>
                @if($d['overdueCount'] > 0)
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-red-500/30 ring-1 ring-red-400/40 px-3 py-1 text-xs font-semibold text-red-200">
                    <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                    {{ $d['overdueCount'] }} overdue installment{{ $d['overdueCount'] > 1 ? 's' : '' }} — action needed
                </div>
                @elseif($d['paidThisMonth'])
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-500/30 ring-1 ring-emerald-400/40 px-3 py-1 text-xs font-semibold text-emerald-200">
                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                    Contribution paid this month
                </div>
                @endif
                @endif
            </div>

            {{-- Next payment badge --}}
            @if($d['hasMember'] && $d['nextPayment'])
            <div class="flex-shrink-0">
                <div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 text-center min-w-[140px]">
                    <p class="text-xs font-medium text-emerald-300 uppercase tracking-wide">Next Payment</p>
                    <p class="mt-1 text-2xl font-extrabold text-white">﷼ {{ number_format($d['nextPayment']['amount'], 0) }}</p>
                    <p class="mt-0.5 text-xs text-emerald-200">{{ $d['nextPayment']['label'] }}</p>
                    @if($d['nextPayment']['type'] === 'installment')
                    <span class="mt-2 inline-block rounded-full bg-indigo-500/30 px-2 py-0.5 text-xs text-indigo-200">Loan</span>
                    @else
                    <span class="mt-2 inline-block rounded-full bg-emerald-500/30 px-2 py-0.5 text-xs text-emerald-200">Contribution</span>
                    @endif
                </div>
            </div>
            @endif
        </div>

        @if($d['hasMember'])
        {{-- KPI tiles --}}
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Cash Balance</p>
                <p class="mt-1 text-xl font-bold text-white">﷼ {{ number_format($d['cash'], 0) }}</p>
                <p class="mt-0.5 text-xs text-emerald-400">Available cash</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Fund Balance</p>
                <p class="mt-1 text-xl font-bold text-white">﷼ {{ number_format($d['fund'], 0) }}</p>
                <p class="mt-0.5 text-xs text-emerald-400">Savings fund</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Active Loan</p>
                @if($d['activeLoan'])
                <p class="mt-1 text-xl font-bold text-white">﷼ {{ number_format($d['activeLoan']->amount_approved, 0) }}</p>
                <p class="mt-0.5 text-xs text-emerald-400">Outstanding</p>
                @else
                <p class="mt-1 text-xl font-bold text-white">None</p>
                <p class="mt-0.5 text-xs text-emerald-400">No active loan</p>
                @endif
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Health</p>
                @if($d['overdueCount'] === 0)
                <p class="mt-1 text-xl font-bold text-emerald-300">Good</p>
                <p class="mt-0.5 text-xs text-emerald-400">All payments current</p>
                @else
                <p class="mt-1 text-xl font-bold text-red-300">{{ $d['overdueCount'] }} Overdue</p>
                <p class="mt-0.5 text-xs text-red-400">Requires action</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
