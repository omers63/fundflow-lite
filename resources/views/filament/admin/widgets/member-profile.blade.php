@php $d = $this->getData(); @endphp

@if(!($d['hasRecord'] ?? false))
    <div class="p-4 text-gray-400 text-sm">No member selected.</div>
@else
<div class="rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm">

    {{-- ── Header bar ────────────────────────────────────────────────────── --}}
    <div class="bg-gradient-to-r from-slate-700 via-slate-800 to-gray-900 px-6 py-5 flex flex-wrap items-center gap-4">

        {{-- Avatar --}}
        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-400 to-purple-600 flex items-center justify-center flex-shrink-0 shadow-lg">
            <span class="text-2xl font-bold text-white">{{ strtoupper(substr($d['name'], 0, 1)) }}</span>
        </div>

        {{-- Name + Number --}}
        <div class="flex-1 min-w-0">
            <h2 class="text-xl font-bold text-white truncate">{{ $d['name'] }}</h2>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <span class="text-sm text-slate-300 font-mono">{{ $d['member_number'] }}</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ $d['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30'
                       : ($d['status'] === 'delinquent' ? 'bg-red-500/20 text-red-300 ring-1 ring-red-500/30'
                       : 'bg-amber-500/20 text-amber-300 ring-1 ring-amber-500/30') }}">
                    {{ ucfirst($d['status']) }}
                </span>
                @if($d['is_loan_eligible_age'])
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 ring-1 ring-blue-500/30">
                    Loan Eligible
                </span>
                @endif
            </div>
        </div>

        {{-- Key stats chips --}}
        <div class="flex flex-wrap gap-3">
            <div class="text-center px-4 py-2 rounded-lg bg-white/10 backdrop-blur-sm">
                <p class="text-lg font-bold text-white">{{ $d['months_active'] }}</p>
                <p class="text-xs text-slate-400">Months Active</p>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-white/10 backdrop-blur-sm">
                <p class="text-lg font-bold text-white">{{ $d['compliance_rate'] }}%</p>
                <p class="text-xs text-slate-400">Compliance</p>
            </div>
            <div class="text-center px-4 py-2 rounded-lg bg-white/10 backdrop-blur-sm">
                <p class="text-lg font-bold text-white">SAR {{ number_format($d['monthly_contrib']) }}</p>
                <p class="text-xs text-slate-400">Monthly Alloc.</p>
            </div>
        </div>
    </div>

    {{-- ── Body grid ──────────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-900 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">

        {{-- Contact --}}
        <div class="p-5 space-y-3">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest flex items-center gap-1.5">
                <x-heroicon-o-user class="w-3.5 h-3.5" /> Contact
            </h3>
            <dl class="space-y-2">
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">Email</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium break-all">{{ $d['email'] }}</dd>
                </div>
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">Phone</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['phone'] ?: '—' }}</dd>
                </div>
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">Joined</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['joined_at'] }}</dd>
                </div>
                @if($d['dob'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">Date of Birth</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['dob'] }}</dd>
                </div>
                @endif
                @if($d['city'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">City</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['city'] }}</dd>
                </div>
                @endif
                @if($d['national_id'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">National ID</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-mono font-medium">{{ $d['national_id'] }}</dd>
                </div>
                @endif
                @if($d['gender'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-20 flex-shrink-0 pt-0.5">Gender</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ ucfirst($d['gender']) }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Employment --}}
        <div class="p-5 space-y-3">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest flex items-center gap-1.5">
                <x-heroicon-o-briefcase class="w-3.5 h-3.5" /> Employment & Finance
            </h3>
            <dl class="space-y-2">
                @if($d['occupation'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-24 flex-shrink-0 pt-0.5">Occupation</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['occupation'] }}</dd>
                </div>
                @endif
                @if($d['employer'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-24 flex-shrink-0 pt-0.5">Employer</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['employer'] }}</dd>
                </div>
                @endif
                @if($d['monthly_income'])
                <div class="flex items-start gap-2">
                    <dt class="text-xs text-gray-400 dark:text-gray-500 w-24 flex-shrink-0 pt-0.5">Monthly Income</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">SAR {{ number_format($d['monthly_income'], 2) }}</dd>
                </div>
                @endif
                @if(!$d['occupation'] && !$d['employer'] && !$d['monthly_income'])
                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No employment data recorded.</p>
                @endif
            </dl>

            @if($d['next_of_kin_name'] || $d['next_of_kin_phone'])
            <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest flex items-center gap-1.5 mb-2">
                    <x-heroicon-o-heart class="w-3.5 h-3.5" /> Next of Kin
                </h4>
                <dl class="space-y-1">
                    @if($d['next_of_kin_name'])
                    <div class="flex items-start gap-2">
                        <dt class="text-xs text-gray-400 dark:text-gray-500 w-12 flex-shrink-0 pt-0.5">Name</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['next_of_kin_name'] }}</dd>
                    </div>
                    @endif
                    @if($d['next_of_kin_phone'])
                    <div class="flex items-start gap-2">
                        <dt class="text-xs text-gray-400 dark:text-gray-500 w-12 flex-shrink-0 pt-0.5">Phone</dt>
                        <dd class="text-sm text-gray-900 dark:text-gray-100 font-medium">{{ $d['next_of_kin_phone'] }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>

        {{-- Membership relationships --}}
        <div class="p-5 space-y-3">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest flex items-center gap-1.5">
                <x-heroicon-o-users class="w-3.5 h-3.5" /> Membership
            </h3>

            {{-- Compliance bar --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Contribution compliance</span>
                    <span class="text-xs font-bold {{ $d['compliance_rate'] >= 90 ? 'text-emerald-600' : ($d['compliance_rate'] >= 70 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ $d['compliance_rate'] }}%
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div class="h-2 rounded-full {{ $d['compliance_rate'] >= 90 ? 'bg-emerald-500' : ($d['compliance_rate'] >= 70 ? 'bg-amber-500' : 'bg-red-500') }}"
                         style="width: {{ $d['compliance_rate'] }}%"></div>
                </div>
            </div>

            {{-- Loan eligibility --}}
            <div class="flex items-center gap-2 text-sm">
                @if($d['is_loan_eligible_age'])
                    <div class="w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-check class="w-3 h-3 text-emerald-600" />
                    </div>
                    <span class="text-gray-700 dark:text-gray-300">Loan-eligible since {{ $d['loan_eligible_date'] }}</span>
                @else
                    <div class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-clock class="w-3 h-3 text-amber-600" />
                    </div>
                    <span class="text-gray-500 dark:text-gray-400">Loan eligible {{ $d['loan_eligible_date'] }}</span>
                @endif
            </div>

            {{-- Sponsor --}}
            @if($d['parent_name'])
            <div class="flex items-center gap-2 text-sm">
                <div class="w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center flex-shrink-0">
                    <x-heroicon-o-arrow-up class="w-3 h-3 text-blue-600" />
                </div>
                <span class="text-gray-700 dark:text-gray-300">Sponsored by <span class="font-medium">{{ $d['parent_name'] }}</span>
                    <span class="text-gray-400 dark:text-gray-500 text-xs">({{ $d['parent_number'] }})</span>
                </span>
            </div>
            @endif

            {{-- Dependents --}}
            @if(count($d['dependents']) > 0)
            <div class="space-y-1">
                <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide font-semibold">
                    Dependents ({{ count($d['dependents']) }})
                </p>
                @foreach($d['dependents'] as $dep)
                <div class="flex items-center gap-2 text-sm">
                    <div class="w-5 h-5 rounded-full bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center flex-shrink-0">
                        <x-heroicon-o-arrow-down class="w-3 h-3 text-purple-600" />
                    </div>
                    <span class="text-gray-700 dark:text-gray-300">{{ $dep['name'] }}
                        <span class="text-gray-400 dark:text-gray-500 text-xs">({{ $dep['number'] }})</span>
                    </span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No dependents.</p>
            @endif
        </div>
    </div>
</div>
@endif
