@php $d = $this->getData(); @endphp

<div class="space-y-3">

{{-- ── Main banner ─────────────────────────────────────────────────────── --}}
<div class="relative overflow-hidden rounded-2xl shadow-xl">
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-700 via-teal-800 to-cyan-900"></div>
    <div class="pointer-events-none absolute -top-20 -right-20 h-64 w-64 rounded-full bg-emerald-400 opacity-20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-12 -left-12 h-48 w-48 rounded-full bg-teal-300 opacity-20 blur-3xl"></div>

    <div class="relative px-6 py-7 sm:px-8 sm:py-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">

            {{-- Greeting --}}
            <div>
                <p class="text-sm font-medium text-emerald-200 tracking-wide">{{ $d['date'] }}</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-white">
                    {{ $d['greeting'] }}, {{ $d['name'] }}
                </h1>
                @if($d['hasMember'])
                <p class="mt-1.5 text-sm text-emerald-200">
                    Member <span class="font-semibold text-white">{{ $d['memberNumber'] }}</span>
                    @if($d['memberStatus'] !== 'active')
                        &nbsp;·&nbsp;<span class="text-amber-300 font-semibold">{{ ucfirst($d['memberStatus']) }}</span>
                    @endif
                </p>
                {{-- Status badges --}}
                <div class="mt-2 flex flex-wrap gap-2">
                    @if($d['overdueCount'] > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-500/30 ring-1 ring-red-400/40 px-3 py-1 text-xs font-semibold text-red-200">
                        <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                        {{ $d['overdueCount'] }} overdue installment{{ $d['overdueCount'] > 1 ? 's' : '' }}
                    </span>
                    @elseif($d['paidThisMonth'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/30 ring-1 ring-emerald-400/40 px-3 py-1 text-xs font-semibold text-emerald-200">
                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                        Contribution paid this month
                    </span>
                    @endif
                    @if($d['pendingLoan'])
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/30 ring-1 ring-blue-400/40 px-3 py-1 text-xs font-semibold text-blue-200">
                        <x-heroicon-o-clock class="w-3.5 h-3.5" />
                        Loan application under review
                        @if($d['pendingLoan']->queue_position)
                            (Queue #{{ $d['pendingLoan']->queue_position }})
                        @endif
                    </span>
                    @endif
                </div>
                @endif
            </div>

            {{-- Next payment --}}
            @if($d['hasMember'] && $d['nextPayment'])
            <div class="flex-shrink-0">
                <div class="rounded-2xl bg-white/10 ring-1 ring-white/20 px-5 py-4 text-center min-w-[140px]">
                    <p class="text-xs font-medium text-emerald-300 uppercase tracking-wide">Next Payment</p>
                    <p class="mt-1 text-2xl font-extrabold text-white">﷼ {{ number_format($d['nextPayment']['amount'], 0) }}</p>
                    <p class="mt-0.5 text-xs text-emerald-200">{{ $d['nextPayment']['label'] }}</p>
                    <span class="mt-2 inline-block rounded-full {{ $d['nextPayment']['type'] === 'installment' ? 'bg-indigo-500/30 text-indigo-200' : 'bg-emerald-500/30 text-emerald-200' }} px-2 py-0.5 text-xs">
                        {{ $d['nextPayment']['type'] === 'installment' ? 'Loan' : 'Contribution' }}
                    </span>
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
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Fund Balance</p>
                <p class="mt-1 text-xl font-bold text-white">﷼ {{ number_format($d['fund'], 0) }}</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Active Loan</p>
                @if($d['activeLoan'])
                <p class="mt-1 text-xl font-bold text-white">﷼ {{ number_format($d['activeLoan']->amount_approved, 0) }}</p>
                <p class="mt-0.5 text-xs text-emerald-400">Outstanding</p>
                @else
                <p class="mt-1 text-xl font-bold text-white">None</p>
                @endif
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-emerald-300 uppercase tracking-wider">Health</p>
                @if($d['overdueCount'] === 0)
                <p class="mt-1 text-xl font-bold text-emerald-300">Good</p>
                @else
                <p class="mt-1 text-xl font-bold text-red-300">{{ $d['overdueCount'] }} Overdue</p>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── Contextual alert strips ─────────────────────────────────────────── --}}
@if($d['hasMember'])

@if($d['overdueCount'] > 0)
<div class="flex items-center justify-between gap-4 rounded-xl bg-red-50 dark:bg-red-950/30 ring-1 ring-red-200 dark:ring-red-800 px-5 py-4">
    <div class="flex items-center gap-3">
        <x-heroicon-o-exclamation-circle class="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" />
        <div>
            <p class="text-sm font-semibold text-red-800 dark:text-red-300">
                {{ $d['overdueCount'] }} overdue installment{{ $d['overdueCount'] > 1 ? 's' : '' }}
                — SAR {{ number_format($d['overdueAmount'], 2) }} outstanding
            </p>
            <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">Late fees may be accruing. Pay as soon as possible to avoid further penalties.</p>
        </div>
    </div>
    <a href="{{ \App\Filament\Member\Resources\MyInstallmentsResource::getUrl('index') }}"
       class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
        <x-heroicon-o-credit-card class="w-3.5 h-3.5" />
        View Installments
    </a>
</div>
@endif

@if($d['canRepay'] && !$d['repayInsufficient'])
<div class="flex items-center justify-between gap-4 rounded-xl bg-blue-50 dark:bg-blue-950/30 ring-1 ring-blue-200 dark:ring-blue-800 px-5 py-4">
    <div class="flex items-center gap-3">
        <x-heroicon-o-credit-card class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0" />
        <div>
            <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">Loan installment is due for this period</p>
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">You can pay your installment now from your cash account.</p>
        </div>
    </div>
    <a href="{{ \App\Filament\Member\Resources\MyInstallmentsResource::getUrl('index') }}"
       class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
        Pay Now
    </a>
</div>
@elseif($d['canRepay'] && $d['repayInsufficient'])
<div class="flex items-center gap-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 ring-1 ring-amber-200 dark:ring-amber-800 px-5 py-4">
    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0" />
    <div>
        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Loan installment due — insufficient cash</p>
        <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Your cash balance is too low to cover this month's installment. Contact admin to top up your account.</p>
    </div>
</div>
@endif

@if($d['contributionDue'] && !$d['canRepay'])
<div class="flex items-center justify-between gap-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 ring-1 ring-amber-200 dark:ring-amber-800 px-5 py-4">
    <div class="flex items-center gap-3">
        <x-heroicon-o-banknotes class="w-6 h-6 text-amber-600 dark:text-amber-400 flex-shrink-0" />
        <div>
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Monthly contribution not yet recorded</p>
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Ensure your contribution is processed before the cycle deadline to avoid late fees.</p>
        </div>
    </div>
    <a href="{{ \App\Filament\Member\Resources\MyContributionsResource::getUrl('index') }}"
       class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
        View Contributions
    </a>
</div>
@endif

@if($d['isEligible'] && !$d['activeLoan'] && !$d['pendingLoan'])
<div class="flex items-center justify-between gap-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200 dark:ring-emerald-800 px-5 py-4">
    <div class="flex items-center gap-3">
        <x-heroicon-o-check-badge class="w-6 h-6 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
        <div>
            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">You are eligible to apply for a loan</p>
            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">Your fund balance qualifies you. Use the Loan Calculator to estimate your repayments.</p>
        </div>
    </div>
    <a href="{{ \App\Filament\Member\Resources\MyLoansResource::getUrl('index') }}"
       class="flex-shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
        Apply Now
    </a>
</div>
@endif

{{-- ── Quick actions ─────────────────────────────────────────────────── --}}
<div class="flex flex-wrap gap-2 pt-1">
    <a href="{{ \App\Filament\Member\Resources\MyLoansResource::getUrl('index') }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-document-currency-dollar class="w-3.5 h-3.5 text-emerald-500" />
        My Loans
    </a>
    <a href="{{ \App\Filament\Member\Resources\MyInstallmentsResource::getUrl('index') }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-calendar-days class="w-3.5 h-3.5 text-blue-500" />
        Installments
    </a>
    <a href="{{ \App\Filament\Member\Resources\MyContributionsResource::getUrl('index') }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-banknotes class="w-3.5 h-3.5 text-amber-500" />
        Contributions
    </a>
    <a href="{{ \App\Filament\Member\Resources\MyStatementsResource::getUrl('index') }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-document-chart-bar class="w-3.5 h-3.5 text-purple-500" />
        Statements
    </a>
    <a href="{{ \App\Filament\Member\Resources\MyAccountLedgerResource::getUrl('index') }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-clipboard-document-list class="w-3.5 h-3.5 text-teal-500" />
        Account Ledger
    </a>
    <a href="{{ \App\Filament\Member\Pages\MyProfilePage::getUrl() }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-user-circle class="w-3.5 h-3.5 text-gray-500" />
        My Profile
    </a>
    <a href="{{ \App\Filament\Member\Pages\SupportPage::getUrl() }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
        <x-heroicon-o-chat-bubble-left-right class="w-3.5 h-3.5 text-indigo-500" />
        Support
    </a>
</div>

@endif
</div>
