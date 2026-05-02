<div class="mt-6 border-t border-slate-200/80 pt-6 dark:border-slate-700/80">
    <p class="mb-4 text-center text-sm font-semibold text-slate-700 dark:text-slate-200">
        {{ __('Quick links') }}
    </p>

    <div class="flex flex-col gap-3">
        <a href="{{ route('home') }}"
            class="group flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-emerald-400 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:text-emerald-800 hover:shadow-md dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-500/60 dark:hover:from-emerald-950/40 dark:hover:to-teal-950/40 dark:hover:text-emerald-200">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 transition group-hover:text-emerald-700 dark:text-emerald-400"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>{{ __('Back to home') }}</span>
        </a>

        <a href="{{ route('login') }}"
            class="group flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:border-emerald-400 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 hover:text-emerald-800 hover:shadow-md dark:border-slate-600 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:border-emerald-500/60 dark:hover:from-emerald-950/40 dark:hover:to-teal-950/40 dark:hover:text-emerald-200">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 transition group-hover:text-emerald-700 dark:text-emerald-400"
                fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>{{ __('Member login') }}</span>
        </a>

        <a href="{{ route('apply') }}"
            class="group flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-3 text-sm font-bold text-white shadow-md transition-all duration-200 hover:from-emerald-700 hover:to-teal-700 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99] dark:from-emerald-600 dark:to-teal-600">
            <svg class="h-5 w-5 shrink-0 opacity-95" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            <span>{{ __('Apply for membership') }}</span>
        </a>
    </div>

</div>