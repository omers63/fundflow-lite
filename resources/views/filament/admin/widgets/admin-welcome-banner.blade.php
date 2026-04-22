@php $d = $this->getData(); @endphp

<div class="relative overflow-hidden rounded-2xl shadow-xl">
    {{-- Background gradient --}}
    <div class="absolute inset-0 bg-gradient-to-br from-slate-800 via-indigo-900 to-purple-900"></div>

    {{-- Decorative blobs --}}
    <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-indigo-500 opacity-20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-16 -left-16 h-56 w-56 rounded-full bg-purple-500 opacity-20 blur-3xl"></div>
    <div class="pointer-events-none absolute top-4 right-1/3 h-32 w-32 rounded-full bg-sky-400 opacity-10 blur-2xl"></div>

    {{-- Content --}}
    <div class="relative px-6 py-7 sm:px-8 sm:py-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">

            {{-- Greeting --}}
            <div>
                <p class="text-sm font-medium text-indigo-300 tracking-wide">{{ $d['date'] }}</p>
                <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-white">
                    {{ $d['greeting'] }}, {{ $d['name'] }}
                </h1>
                <p class="mt-1.5 text-sm text-indigo-200">
                    {{ __('Here\'s a snapshot of') }} <span class="font-semibold text-white">{{ app()->getLocale() === 'ar' ? 'فندفلو' : 'FundFlow' }}</span> {{ __('right now.') }}
                </p>
            </div>

            {{-- Compliance badge --}}
            <div class="flex-shrink-0 text-center">
                <div class="inline-flex flex-col items-center justify-center h-24 w-24 rounded-full bg-white/10 ring-2 ring-white/20">
                    <span class="text-3xl font-extrabold text-white">{{ $d['complianceRate'] }}%</span>
                    <span class="text-xs text-indigo-300 font-medium mt-0.5">{{ __('Compliance') }}</span>
                </div>
                <p class="mt-1.5 text-xs text-indigo-300">{{ __(':paid / :active paid this month', ['paid' => $d['paidThisMonth'], 'active' => $d['activeMembers']]) }}</p>
            </div>
        </div>

        {{-- KPI tiles --}}
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-indigo-300 uppercase tracking-wider">{{ __('Master Fund') }}</p>
                <p class="mt-1 text-xl font-bold text-white">SAR {{ number_format($d['masterFund'], 0) }}</p>
                <p class="mt-0.5 text-xs text-indigo-400">{{ __('Investable capital') }}</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-indigo-300 uppercase tracking-wider">{{ __('Cash on Hand') }}</p>
                <p class="mt-1 text-xl font-bold text-white">SAR {{ number_format($d['masterCash'], 0) }}</p>
                <p class="mt-0.5 text-xs text-indigo-400">{{ __('Member deposits') }}</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-indigo-300 uppercase tracking-wider">{{ __('Active Loans') }}</p>
                <p class="mt-1 text-xl font-bold text-white">{{ $d['activeLoans'] }}</p>
                <p class="mt-0.5 text-xs text-indigo-400">{{ __('Outstanding') }}</p>
            </div>
            <div class="rounded-xl bg-white/8 ring-1 ring-white/15 px-4 py-3 backdrop-blur-sm">
                <p class="text-xs font-medium text-indigo-300 uppercase tracking-wider">{{ __('Active Members') }}</p>
                <p class="mt-1 text-xl font-bold text-white">{{ $d['activeMembers'] }}</p>
                <p class="mt-0.5 text-xs text-indigo-400">{{ __('Enrolled & active') }}</p>
            </div>
        </div>
    </div>
</div>
