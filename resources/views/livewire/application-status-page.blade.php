<div class="min-h-screen bg-gradient-to-br from-slate-50 to-teal-50 py-16 px-4">
    <div class="max-w-lg mx-auto">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-slate-800 mb-2">Check Application Status</h1>
            <p class="text-slate-500">Enter your email and National ID to check the status of your membership application.</p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-teal-600 to-emerald-600 p-6">
                <div class="flex items-center gap-3 text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <h2 class="text-white font-bold text-lg">Application Lookup</h2>
                </div>
            </div>

            <div class="p-8">
                <form wire:submit="check" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Email Address *</label>
                        <input wire:model="email" type="email" placeholder="your@email.com" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 @error('email') border-red-400 @enderror">
                        @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">National ID *</label>
                        <input wire:model="national_id" type="text" placeholder="1XXXXXXXXX" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 @error('national_id') border-red-400 @enderror">
                        @error('national_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="w-full bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all disabled:opacity-50">
                        <span wire:loading.remove>Check Status</span>
                        <span wire:loading>Checking...</span>
                    </button>
                </form>

                @if($searched)
                <div class="mt-8 pt-6 border-t border-slate-100">
                    @if($result)
                    <div class="space-y-4">
                        @if($result['status'] === 'pending')
                        <div class="p-5 bg-amber-50 border border-amber-200 rounded-2xl">
                            <div class="flex items-center gap-3 mb-3">
                                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="font-bold text-amber-800">Pending Review</p>
                                    <p class="text-xs text-amber-600">Applicant: {{ $result['name'] }}</p>
                                </div>
                            </div>
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500">Submitted</dt><dd class="font-medium text-slate-700">{{ $result['submitted_at'] }}</dd></div>
                                <div class="flex justify-between"><dt class="text-slate-500">City</dt><dd class="font-medium text-slate-700">{{ $result['city'] }}</dd></div>
                            </dl>
                            <p class="text-xs text-amber-700 mt-3">Your application is being reviewed. You will be notified when a decision is made.</p>
                        </div>
                        @elseif($result['status'] === 'approved')
                        <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl">
                            <div class="flex items-center gap-3 mb-3">
                                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="font-bold text-emerald-800">Approved!</p>
                                    <p class="text-xs text-emerald-600">Applicant: {{ $result['name'] }}</p>
                                </div>
                            </div>
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500">Submitted</dt><dd class="font-medium text-slate-700">{{ $result['submitted_at'] }}</dd></div>
                                @if($result['reviewed_at'])<div class="flex justify-between"><dt class="text-slate-500">Approved</dt><dd class="font-medium text-slate-700">{{ $result['reviewed_at'] }}</dd></div>@endif
                                <div class="flex justify-between"><dt class="text-slate-500">City</dt><dd class="font-medium text-slate-700">{{ $result['city'] }}</dd></div>
                            </dl>
                            <div class="mt-3 pt-3 border-t border-emerald-200">
                                <a href="{{ route('login') }}" class="text-sm font-bold text-emerald-700 hover:underline">Sign in to your account →</a>
                            </div>
                        </div>
                        @else
                        <div class="p-5 bg-red-50 border border-red-200 rounded-2xl">
                            <div class="flex items-center gap-3 mb-3">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="font-bold text-red-800">Not Approved</p>
                                    <p class="text-xs text-red-600">Applicant: {{ $result['name'] }}</p>
                                </div>
                            </div>
                            <dl class="space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-slate-500">Submitted</dt><dd class="font-medium text-slate-700">{{ $result['submitted_at'] }}</dd></div>
                                @if($result['reviewed_at'])<div class="flex justify-between"><dt class="text-slate-500">Reviewed</dt><dd class="font-medium text-slate-700">{{ $result['reviewed_at'] }}</dd></div>@endif
                            </dl>
                            @if($result['rejection_reason'])
                            <div class="mt-3 pt-3 border-t border-red-200">
                                <p class="text-xs text-red-600 font-medium">Reason:</p>
                                <p class="text-sm text-red-700 italic mt-1">"{{ $result['rejection_reason'] }}"</p>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="p-5 bg-slate-50 border border-slate-200 rounded-2xl text-center">
                        <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-slate-600 font-medium">No application found</p>
                        <p class="text-slate-400 text-sm mt-1">No application matches the provided email and National ID.</p>
                        <a href="{{ route('apply') }}" class="text-emerald-600 text-sm font-semibold hover:underline mt-2 inline-block">Apply for membership →</a>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
