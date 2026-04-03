@php $data = $this->getData(); @endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80">
        <div class="flex items-center gap-2">
            <x-heroicon-o-funnel class="w-5 h-5 text-primary-500" />
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Membership Pipeline</h3>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            @if($data['apps_this_month'] > 0)
            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                +{{ $data['apps_this_month'] }} app{{ $data['apps_this_month'] > 1 ? 's' : '' }} this month
            </span>
            @endif
            @if($data['new_this_month'] > 0)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                +{{ $data['new_this_month'] }} member{{ $data['new_this_month'] > 1 ? 's' : '' }} joined
            </span>
            @endif
        </div>
    </div>

    {{-- Pipeline funnel --}}
    <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-700 sm:grid-cols-5">

        {{-- 1. Pending Applications --}}
        <a href="{{ $data['applications_url'] }}" class="group flex flex-col items-center justify-center px-4 py-5 text-center hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-colors">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40 ring-2 ring-amber-200 dark:ring-amber-800 mb-3 group-hover:ring-amber-400 transition-all">
                <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </div>
            <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $data['pending_apps'] }}</span>
            <span class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Pending Review</span>
            @if($data['pending_apps'] > 0)
            <span class="mt-2 text-xs text-amber-600 dark:text-amber-400 opacity-0 group-hover:opacity-100 transition-opacity">Review now →</span>
            @endif
        </a>

        {{-- Arrow divider (hidden on mobile) --}}
        <div class="hidden sm:flex items-center justify-center border-l-0 border-r-0 px-1">
            <x-heroicon-o-chevron-right class="w-5 h-5 text-gray-300 dark:text-gray-600" />
        </div>

        {{-- 2. Approved Applications --}}
        <a href="{{ $data['applications_url'] }}" class="group flex flex-col items-center justify-center px-4 py-5 text-center hover:bg-indigo-50 dark:hover:bg-indigo-900/10 transition-colors">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 ring-2 ring-indigo-200 dark:ring-indigo-800 mb-3 group-hover:ring-indigo-400 transition-all">
                <x-heroicon-o-check-circle class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
            </div>
            <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $data['approved_apps'] }}</span>
            <span class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Approved / Pending Activation</span>
        </a>

        {{-- Arrow divider (hidden on mobile) --}}
        <div class="hidden sm:flex items-center justify-center border-l-0 border-r-0 px-1">
            <x-heroicon-o-chevron-right class="w-5 h-5 text-gray-300 dark:text-gray-600" />
        </div>

        {{-- 3. Active Members --}}
        <a href="{{ $data['members_url'] }}" class="group flex flex-col items-center justify-center px-4 py-5 text-center hover:bg-emerald-50 dark:hover:bg-emerald-900/10 transition-colors">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 ring-2 ring-emerald-200 dark:ring-emerald-800 mb-3 group-hover:ring-emerald-400 transition-all">
                <x-heroicon-o-users class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $data['active_members'] }}</span>
            <span class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Active Members</span>
            @if($data['new_this_month'] > 0)
            <span class="mt-2 text-xs text-emerald-500 dark:text-emerald-400">+{{ $data['new_this_month'] }} this month</span>
            @endif
        </a>

    </div>

    {{-- Bottom: delinquent & suspended flags --}}
    @if($data['delinquent'] > 0 || $data['suspended'] > 0)
    <div class="flex flex-wrap gap-3 px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-red-50/50 dark:bg-red-900/5">
        @if($data['delinquent'] > 0)
        <a href="{{ $data['members_url'] }}" class="inline-flex items-center gap-1.5 rounded-full bg-red-100 dark:bg-red-900/40 px-3 py-1 text-xs font-medium text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 transition-colors">
            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
            {{ $data['delinquent'] }} delinquent member{{ $data['delinquent'] > 1 ? 's' : '' }}
        </a>
        @endif
        @if($data['suspended'] > 0)
        <a href="{{ $data['members_url'] }}" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
            <x-heroicon-o-no-symbol class="w-3.5 h-3.5" />
            {{ $data['suspended'] }} suspended
        </a>
        @endif
    </div>
    @endif

</div>
