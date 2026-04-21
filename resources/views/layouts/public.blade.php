<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @php
        $publicAppName = app()->getLocale() === 'ar' ? 'فندفلو' : config('app.name', 'FundFlow');
        $familyFundSuffix = __('Family Fund Management System');
        $topbarBrand = str_contains($publicAppName, $familyFundSuffix)
            ? $publicAppName
            : $publicAppName . ' - ' . $familyFundSuffix;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $publicAppName }} — {{ __('Family Fund Management System') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="antialiased bg-slate-50 font-sans text-slate-800 {{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

    {{-- Navigation --}}
    <nav x-data="{ open: false }" class="bg-white/95 backdrop-blur-sm border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center space-x-3 pr-4 md:pr-6 md:mr-4 md:border-r md:border-slate-200 group">
                    <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-slate-800">
                        {{ $topbarBrand }}
                    </span>
                </a>

                {{-- Desktop Nav --}}
                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ route('home') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">{{ __('Home') }}</a>
                    <a href="{{ route('home') }}#features" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">{{ __('Features') }}</a>
                    <a href="{{ route('home') }}#how-it-works" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">{{ __('How It Works') }}</a>
                    <a href="{{ route('application.status') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">{{ __('Check Status') }}</a>
                </div>

                {{-- CTA Buttons (desktop) --}}
                <div class="hidden md:flex items-center space-x-3">
                    <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors px-2 py-2">
                        {{ app()->getLocale() === 'ar' ? __('English') : __('العربية') }}
                    </a>
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors px-4 py-2">{{ __('Sign In') }}</a>
                    <a href="{{ route('apply') }}" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-all shadow-sm hover:shadow-md">
                        {{ __('Apply Now') }}
                    </a>
                </div>

                {{-- Mobile menu toggle --}}
                <button
                    type="button"
                    class="md:hidden inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-800 transition-colors"
                    @click="open = ! open"
                    :aria-expanded="open.toString()"
                    aria-controls="public-mobile-menu"
                    aria-label="{{ __('Toggle navigation') }}"
                >
                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mobile menu --}}
            <div id="public-mobile-menu" x-show="open" x-cloak class="md:hidden border-t border-slate-200 py-3">
                <div class="grid gap-1">
                    <a href="{{ route('home') }}" @click="open = false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Home') }}</a>
                    <a href="{{ route('home') }}#features" @click="open = false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Features') }}</a>
                    <a href="{{ route('home') }}#how-it-works" @click="open = false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('How It Works') }}</a>
                    <a href="{{ route('application.status') }}" @click="open = false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ __('Check Status') }}</a>
                    <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}" @click="open = false" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        {{ app()->getLocale() === 'ar' ? __('English') : __('العربية') }}
                    </a>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" @click="open = false" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ __('Sign In') }}</a>
                    <a href="{{ route('apply') }}" @click="open = false" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">{{ __('Apply Now') }}</a>
                </div>
            </div>
        </div>
    </nav>

    {{-- Page Content --}}
    <main>
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="bg-slate-900 text-slate-300 py-16 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-10 mb-12">
                <div class="md:col-span-2">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    <span class="text-xl font-bold text-white">{{ $publicAppName }}</span>
                    </div>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-xs">
                        {{ __('A transparent and trusted family fund management platform — built on mutual support and zero-interest principles.') }}
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">{{ __('Quick Links') }}</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('apply') }}" class="hover:text-sky-400 transition-colors">{{ __('Apply for Membership') }}</a></li>
                        <li><a href="{{ route('application.status') }}" class="hover:text-sky-400 transition-colors">{{ __('Check Application Status') }}</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-sky-400 transition-colors">{{ __('Member Login') }}</a></li>
                        <li><a href="{{ route('downloads.terms-and-conditions') }}" class="hover:text-sky-400 transition-colors">{{ __('Terms & Conditions (PDF)') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">{{ __('Contact') }}</h4>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li>admin@fundflow.sa</li>
                        <li><x-phone-display value="{{ '+966500000000' }}" /></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-slate-500">&copy; {{ date('Y') }} {{ $publicAppName }}. {{ __('All rights reserved.') }}</p>
                <p class="text-sm text-slate-500">{{ __('Managed in :currency (Saudi Riyal)', ['currency' => __('SAR')]) }}</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
