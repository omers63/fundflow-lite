<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $settings['public_primary_color'] ?? '#4f46e5' }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <title>{{ $settings['app_name'] ?? 'FundFlow' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-950 text-white">
    <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
        <nav
            class="mb-8 flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
            <span class="text-base font-semibold sm:text-lg">{{ $settings['app_name'] ?? 'FundFlow' }}</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('locale.switch', 'en') }}"
                    class="rounded-lg border border-white/20 px-3 py-2 text-xs sm:text-sm">EN</a>
                <a href="{{ route('locale.switch', 'ar') }}"
                    class="rounded-lg border border-white/20 px-3 py-2 text-xs sm:text-sm">AR</a>
            </div>
        </nav>

        <section class="relative overflow-hidden rounded-3xl p-6 sm:p-10"
            style="background: linear-gradient(135deg, {{ $settings['public_primary_color'] ?? '#4f46e5' }} 0%, {{ $settings['public_secondary_color'] ?? '#0ea5e9' }} 100%);">
            <div class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/20 blur-2xl"></div>
            <div class="absolute -bottom-16 left-8 h-40 w-40 rounded-full bg-indigo-900/40 blur-3xl"></div>

            <p class="text-xs uppercase tracking-[0.2em] text-white/80">{{ __('Family Fund Management Platform') }}</p>
            <h1 class="mt-3 text-2xl font-bold leading-tight sm:text-4xl">
                {{ $settings['public_hero_title'] ?? __('Manage family funds, sponsorships, and enrollment workflows in one secure workspace.') }}
            </h1>
            <p class="mt-4 max-w-2xl text-sm text-white/90 sm:text-base">
                {{ $settings['public_hero_subtitle'] ?? __('From contributions and dependent sponsorship to approvals, communication, and audit trails, FundFlow gives every family and operator complete operational control.') }}
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="/admin/login"
                    class="rounded-xl bg-white px-5 py-3 text-center text-sm font-semibold text-slate-900">{{ __('Operator / Admin login') }}</a>
                <a href="/member/login"
                    class="rounded-xl border border-white/60 px-5 py-3 text-center text-sm font-semibold">{{ __('Family member login') }}</a>
                <span
                    class="text-xs text-white/80 sm:ml-2">{{ __('PWA-ready, mobile-first, bilingual (EN/AR)') }}</span>
            </div>
        </section>

        <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-indigo-300">{{ __('Fund Visibility') }}</p>
                <h2 class="mt-2 text-2xl font-bold">360°</h2>
                <p class="mt-1 text-sm text-slate-300">
                    {{ __('Real-time overview of contributions, commitments, and utilization.') }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-sky-300">{{ __('Automation') }}</p>
                <h2 class="mt-2 text-2xl font-bold">{{ __('Smart') }}</h2>
                <p class="mt-1 text-sm text-slate-300">
                    {{ __('Configurable workflows for enrollment, review, and approvals.') }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-emerald-300">{{ __('Governance') }}</p>
                <h2 class="mt-2 text-2xl font-bold">{{ __('Audit+') }}</h2>
                <p class="mt-1 text-sm text-slate-300">
                    {{ __('Every action logged with role controls and policy enforcement.') }}</p>
            </article>
            <article class="rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-wide text-amber-300">{{ __('Engagement') }}</p>
                <h2 class="mt-2 text-2xl font-bold">{{ __('Live') }}</h2>
                <p class="mt-1 text-sm text-slate-300">
                    {{ __('Central notifications and contextual comments on workflows.') }}</p>
            </article>
        </section>

        <section class="mt-8 grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-white/10 bg-gradient-to-b from-white/10 to-white/5 p-6">
                <h3 class="text-lg font-semibold">{{ __('Advanced Features for Family Funds') }}</h3>
                <ul class="mt-4 space-y-3 text-sm text-slate-200">
                    <li>• {{ __('Multi-tenant family workspaces with isolated data boundaries') }}</li>
                    <li>• {{ __('Parent-dependent sponsorship modeling and linked member records') }}</li>
                    <li>• {{ __('Enrollment pipeline with statuses, reviewer actions, and timeline control') }}</li>
                    <li>• {{ __('Subscription-ready architecture with usage tracking hooks') }}</li>
                    <li>• {{ __('Role-based access via Admin and Member portals with distinct UX themes') }}</li>
                </ul>
            </article>

            <article class="rounded-2xl border border-white/10 bg-gradient-to-b from-white/10 to-white/5 p-6">
                <h3 class="text-lg font-semibold">{{ __('Operational Excellence') }}</h3>
                <ul class="mt-4 space-y-3 text-sm text-slate-200">
                    <li>• {{ __('Centralized system settings for branding, content, and maintenance mode') }}</li>
                    <li>• {{ __('Database notifications for actionable alerts across workflows') }}</li>
                    <li>• {{ __('Comment-enabled communication layer for admins and members') }}</li>
                    <li>• {{ __('Mobile installable PWA experience with offline fallback support') }}</li>
                    <li>• {{ __('Bilingual English/Arabic public and panel-ready interface') }}</li>
                </ul>
            </article>
        </section>

        <section class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-6 sm:p-8">
            <h3 class="text-xl font-semibold">{{ __('How it works') }}</h3>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-indigo-300">{{ __('Step 1') }}</p>
                    <p class="mt-2 text-sm">
                        {{ __('Operators onboard families as secure tenants with dedicated domains.') }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-sky-300">{{ __('Step 2') }}</p>
                    <p class="mt-2 text-sm">
                        {{ __('Families enroll members, define sponsorship links, and manage approvals.') }}</p>
                </div>
                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-emerald-300">{{ __('Step 3') }}</p>
                    <p class="mt-2 text-sm">
                        {{ __('Teams collaborate with comments, notifications, and full audit visibility.') }}</p>
                </div>
            </div>
        </section>
    </main>
</body>

</html>