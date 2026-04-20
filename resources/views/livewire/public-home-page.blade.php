<div>
    {{-- Hero Section --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 text-white">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-20 left-10 w-72 h-72 bg-sky-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-24 lg:py-28 text-center">
            <div class="inline-flex items-center px-4 py-2 rounded-full bg-sky-500/20 border border-sky-400/30 text-sky-200 text-sm font-medium mb-8">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Zero-Interest Family Fund
            </div>
            <h1 class="text-3xl sm:text-4xl md:text-6xl font-extrabold leading-tight mb-5 sm:mb-6">
                Manage Your Family Fund
                <span class="block text-transparent bg-clip-text bg-gradient-to-r from-sky-300 via-blue-200 to-cyan-200">
                    Together & Transparently
                </span>
            </h1>
            <p class="text-base sm:text-lg md:text-xl text-slate-300 max-w-2xl mx-auto mb-8 sm:mb-10 leading-relaxed">
                A trusted platform for family fund contributions, interest-free loans, and complete financial transparency — empowering families to support each other.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center">
                <a href="{{ route('apply') }}" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold px-8 py-4 rounded-2xl transition-all shadow-lg hover:shadow-blue-500/30 text-lg">
                    Apply for Membership
                </a>
                <a href="{{ route('login') }}" class="w-full sm:w-auto bg-white/10 hover:bg-white/20 border border-white/20 text-white font-semibold px-8 py-4 rounded-2xl transition-all text-lg">
                    Member Login
                </a>
                <a href="{{ route('downloads.terms-and-conditions') }}" class="w-full sm:w-auto bg-white/10 hover:bg-white/20 border border-white/20 text-white font-semibold px-8 py-4 rounded-2xl transition-all text-lg">
                    Download Terms &amp; Conditions
                </a>
            </div>
        </div>
    </section>

    {{-- Stats Section --}}
    <section class="py-16 bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-8 rounded-2xl bg-gradient-to-br from-slate-50 to-blue-50 border border-blue-100">
                    <div class="text-4xl font-extrabold text-blue-700 mb-2">{{ number_format($stats['members']) }}</div>
                    <div class="text-slate-600 font-medium">Active Members</div>
                </div>
                <div class="text-center p-8 rounded-2xl bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-100">
                    <div class="text-4xl font-extrabold text-amber-600 mb-2">﷼{{ number_format($stats['total_contributions'], 0) }}</div>
                    <div class="text-slate-600 font-medium">Total Contributions</div>
                </div>
                <div class="text-center p-8 rounded-2xl bg-gradient-to-br from-indigo-50 to-sky-50 border border-indigo-100">
                    <div class="text-4xl font-extrabold text-indigo-700 mb-2">{{ number_format($stats['active_loans']) }}</div>
                    <div class="text-slate-600 font-medium">Active Loans</div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="features" class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4">Everything You Need</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">A complete family fund management solution with powerful tools for members and administrators.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-blue-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Membership Management</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Easy application process, online form submission, and admin approval workflow with instant notifications.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-sky-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-sky-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Monthly Contributions</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Track member contributions by month and year, with automatic balance calculations and statements.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-indigo-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Monthly Statements</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Download detailed PDF statements showing contributions, loan repayments, and account balance.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-purple-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Interest-Free Loans</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Apply for zero-interest loans with flexible repayment schedules and eligibility-based approval.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-amber-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Smart Notifications</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Receive real-time email, SMS, and WhatsApp alerts for approvals, loans, and payment reminders.</p>
                </div>
                <div class="bg-white rounded-2xl p-8 border border-slate-200 hover:border-rose-300 hover:shadow-lg transition-all group">
                    <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center mb-5">
                        <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Admin Dashboard</h3>
                    <p class="text-slate-500 text-sm leading-relaxed">Comprehensive oversight with statistics, delinquency handling, and bulk management tools.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="how-it-works" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-800 mb-4">How It Works</h2>
                <p class="text-lg text-slate-500">Get started in four simple steps</p>
            </div>
            <div class="grid md:grid-cols-4 gap-8">
                @foreach([
                    ['step' => '1', 'title' => 'Apply Online', 'desc' => 'Fill out the membership application form and upload your signed application document.'],
                    ['step' => '2', 'title' => 'Get Approved', 'desc' => 'Admin reviews your application and notifies you via email, SMS, and WhatsApp.'],
                    ['step' => '3', 'title' => 'Contribute Monthly', 'desc' => 'Make regular monthly contributions to build your fund balance.'],
                    ['step' => '4', 'title' => 'Access Benefits', 'desc' => 'Apply for interest-free loans, view statements, and track your financial activity.'],
                ] as $step)
                <div class="text-center relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-2xl flex items-center justify-center text-white text-2xl font-extrabold mx-auto mb-5 shadow-lg shadow-blue-900/30">
                        {{ $step['step'] }}
                    </div>
                    <h3 class="font-bold text-slate-800 text-lg mb-2">{{ $step['title'] }}</h3>
                    <p class="text-slate-500 text-sm">{{ $step['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="py-20 bg-gradient-to-br from-blue-800 via-blue-700 to-indigo-800 text-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">Ready to Join Your Family Fund?</h2>
            <p class="text-blue-100 text-lg mb-8">Apply for membership today and start building a stronger financial future together.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('apply') }}" class="bg-white text-blue-800 font-bold px-8 py-4 rounded-2xl hover:bg-sky-50 transition-all shadow-lg text-lg">
                    Apply for Membership
                </a>
                <a href="{{ route('application.status') }}" class="bg-white/15 hover:bg-white/25 border border-white/30 text-white font-semibold px-8 py-4 rounded-2xl transition-all text-lg">
                    Check Application Status
                </a>
            </div>
        </div>
    </section>
</div>
