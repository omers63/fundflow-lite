@php
    $appShortName = config('app.short_name', 'FundFlow');
    $appName = config('app.name', 'FundFlow');
    $isArabic = app()->getLocale() === 'ar';
@endphp

<div
    class="mb-5 rounded-3xl border border-slate-200/70 bg-white/90 p-5 shadow-sm dark:border-slate-700/70 dark:bg-slate-900/70">
    <div
        class="mb-4 flex items-center gap-2 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-3 py-2 text-xs font-semibold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.981-1.742 2.981H4.42c-1.53 0-2.492-1.647-1.742-2.98l5.58-9.92zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v3.5a1 1 0 102 0V7a1 1 0 00-1-1z"
                clip-rule="evenodd" />
        </svg>
        <span>
            {{ $isArabic ? 'منطقة إدارية حساسة: الوصول للمستخدمين المصرح لهم فقط.' : 'Sensitive administrative zone: authorized personnel only.' }}
        </span>
    </div>

    <div class="flex items-start gap-4">
        <img src="{{ asset('favicon-192x192.png') }}" alt="{{ $appName }} icon"
            class="h-20 w-20 rounded-2xl bg-slate-100/70 object-contain p-1.5 dark:bg-slate-800/70" />

        <div class="min-w-0 flex-1">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                {{ $isArabic ? 'بوابة الإدارة الآمنة' : 'Secure Administrator Access' }}
            </h2>
            <p class="mt-1 text-sm font-semibold text-sky-700 dark:text-sky-300">
                {{ __($appShortName) }} - {{ __('Family Fund Management System') }}
            </p>
        </div>
    </div>
    <div
        class="mt-3 rounded-2xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 text-[11px] leading-relaxed text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
        <span class="font-semibold">{{ $isArabic ? 'إشعار قانوني:' : 'Legal notice:' }}</span>
        {{ $isArabic
    ? 'الدخول غير المصرح به أو إساءة استخدام النظام قد يعرضك للمساءلة التأديبية والقانونية.'
    : 'Unauthorized access or misuse of this system may result in disciplinary and legal action.' }}
    </div>
</div>