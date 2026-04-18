<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'FundFlow') }} — Family Fund Management</title>
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
<body class="antialiased bg-slate-50 font-sans text-slate-800">

    {{-- Navigation --}}
    <nav class="bg-white/95 backdrop-blur-sm border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center space-x-3 group">
                    <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-slate-800">Fund<span class="text-blue-600">Flow</span></span>
                </a>

                {{-- Desktop Nav --}}
                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ route('home') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Home</a>
                    <a href="{{ route('home') }}#features" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Features</a>
                    <a href="{{ route('home') }}#how-it-works" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">How It Works</a>
                    <a href="{{ route('application.status') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors">Check Status</a>
                </div>

                {{-- CTA Buttons --}}
                <div class="flex items-center space-x-3">
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors px-4 py-2">Sign In</a>
                    <a href="{{ route('apply') }}" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-all shadow-sm hover:shadow-md">
                        Apply Now
                    </a>
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
                        <span class="text-xl font-bold text-white">Fund<span class="text-sky-400">Flow</span></span>
                    </div>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-xs">
                        A transparent and trusted family fund management platform — built on mutual support and zero-interest principles.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('apply') }}" class="hover:text-sky-400 transition-colors">Apply for Membership</a></li>
                        <li><a href="{{ route('application.status') }}" class="hover:text-sky-400 transition-colors">Check Application Status</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-sky-400 transition-colors">Member Login</a></li>
<<<<<<< HEAD
                        <li><a href="{{ route('downloads.terms-and-conditions') }}" class="hover:text-sky-400 transition-colors">Terms &amp; Conditions (PDF)</a></li>
=======
                        <li>
                            <a href="{{ route('downloads.terms-and-conditions') }}" class="hover:text-sky-400 transition-colors" download>
                                Terms And Conditions
                            </a>
                        </li>
>>>>>>> bfc922258871b391c0c11851031beed3ab6c1df8
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold text-sm mb-4">Contact</h4>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li>admin@fundflow.sa</li>
                        <li>+966 50 000 0000</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-slate-500">&copy; {{ date('Y') }} FundFlow. All rights reserved.</p>
                <p class="text-sm text-slate-500">Managed in SAR (Saudi Riyal ﷼)</p>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
