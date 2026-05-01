<div
    class="min-h-screen bg-gradient-to-br from-emerald-700 via-teal-800 to-cyan-900 flex items-center justify-center py-8 sm:py-12 px-4">
    <div class="w-full max-w-md">
        {{-- Card --}}
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="bg-gradient-to-r from-emerald-700 via-teal-800 to-cyan-900 p-6 sm:p-8 text-center">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white">{{ __('Welcome Back') }}</h2>
                <p class="text-emerald-100 text-sm mt-1">{{ __('Sign in to your FundFlow account') }}</p>
            </div>

            <div class="p-6 sm:p-8">
                {{-- Status Messages --}}
                @if($statusType === 'pending')
                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl flex gap-3">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-semibold text-amber-800 text-sm">{{ __('Application Under Review') }}</p>
                            <p class="text-amber-700 text-sm mt-1">{{ $statusMessage }}</p>
                            <a href="{{ route('application.status') }}"
                                class="text-amber-600 text-xs font-medium underline mt-1 inline-block">{{ __('Check detailed status') }}</a>
                        </div>
                    </div>
                @endif

                @if($statusType === 'rejected')
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex gap-3">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-semibold text-red-800 text-sm">{{ __('Application Not Approved') }}</p>
                            <p class="text-red-700 text-sm mt-1">{{ $statusMessage }}</p>
                            @if($rejectionReason)
                                <p class="text-red-600 text-xs mt-1 italic">"{{ $rejectionReason }}"</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if($statusType === 'suspended')
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl flex gap-3">
                        <svg class="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm-3.707-9.707a1 1 0 011.414 0L10 10.586l2.293-2.293a1 1 0 111.414 1.414L11.414 12l2.293 2.293a1 1 0 01-1.414 1.414L10 13.414l-2.293 2.293a1 1 0 01-1.414-1.414L8.586 12 6.293 9.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-semibold text-rose-800 text-sm">{{ __('Membership suspended') }}</p>
                            <p class="text-rose-700 text-sm mt-1">{{ $statusMessage }}</p>
                        </div>
                    </div>
                @endif

                @if($statusType === 'terminated')
                    <div class="mb-6 p-4 bg-slate-100 border border-slate-300 rounded-2xl flex gap-3">
                        <svg class="w-5 h-5 text-slate-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-11.707-.707a1 1 0 011.414-1.414L10 9.172l2.293-2.293a1 1 0 111.414 1.414L11.414 10l2.293 2.293a1 1 0 01-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 01-1.414-1.414L8.586 10 6.293 7.707z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">{{ __('Membership terminated') }}</p>
                            <p class="text-slate-700 text-sm mt-1">{{ $statusMessage }}</p>
                        </div>
                    </div>
                @endif

                @if (!$showProfilePicker)
                    {{-- Login Form --}}
                    <form wire:submit="login" class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Email Address') }}</label>
                            <input wire:model="email" type="email" autocomplete="email"
                                placeholder="{{ __('your@email.com') }}"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('email') border-red-400 @enderror">
                            @error('email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Password') }}</label>
                            <input wire:model="password" type="password" autocomplete="current-password"
                                placeholder="{{ __('••••••••') }}"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('password') border-red-400 @enderror">
                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                <input wire:model="remember" type="checkbox"
                                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                {{ __('Remember me') }}
                            </label>
                            <a href="{{ route('password.request') }}" class="text-sm text-emerald-600 hover:underline">
                                {{ __('Forgot password?') }}
                            </a>
                        </div>

                        <button type="submit" wire:loading.attr="disabled"
                            class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99] disabled:opacity-50 flex items-center justify-center gap-2">
                            <span wire:loading.remove>{{ __('Sign In') }}</span>
                            <span wire:loading class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('Signing in...') }}
                            </span>
                        </button>
                    </form>
                @else
                    <form wire:submit="verifySelectedProfile" class="space-y-5">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">{{ __('Who is accessing the portal?') }}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ __('Select a profile, then verify using PIN/password.') }}
                            </p>
                        </div>

                        <div class="space-y-3">
                            <label
                                class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Select profile') }}</label>
                            <select wire:change="selectProfile($event.target.value)"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('selectedMemberId') border-red-400 @enderror">
                                <option value="">{{ __('Choose a profile') }}</option>
                                @foreach($availableProfiles as $profile)
                                    <option value="{{ $profile['id'] }}" @selected($selectedMemberId === $profile['id'])>
                                        {{ $profile['name'] }} —
                                        {{ $profile['is_parent'] ? __('Parent profile') : __('Dependent profile') }}
                                        @if($profile['is_separated'])
                                            ({{ __('Separated') }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('selectedMemberId')
                                <p class="text-red-500 text-xs">{{ $message }}</p>
                            @enderror

                            @if($selectedMemberId)
                                @php
                                    $selectedProfile = collect($availableProfiles)->firstWhere('id', $selectedMemberId);
                                @endphp
                                @if($selectedProfile)
                                    <div class="p-3 rounded-xl border border-emerald-200 bg-emerald-50/70">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden flex items-center justify-center text-sm font-bold text-slate-600">
                                                @if($selectedProfile['avatar_url'])
                                                    <img src="{{ $selectedProfile['avatar_url'] }}" alt="{{ $selectedProfile['name'] }}"
                                                        class="h-full w-full object-cover">
                                                @else
                                                    {{ strtoupper(mb_substr($selectedProfile['name'], 0, 1)) }}
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800">{{ $selectedProfile['name'] }}</p>
                                                <p class="text-xs text-slate-500">
                                                    {{ $selectedProfile['is_parent'] ? __('Parent profile') : __('Dependent profile') }}
                                                    @if($selectedProfile['is_separated'])
                                                        · {{ __('Separated') }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                {{ __('Verification code/password') }}
                            </label>
                            <input wire:model="verificationSecret" type="password" autocomplete="off"
                                placeholder="{{ __('Enter parent PIN or dependent password') }}"
                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('verificationSecret') border-red-400 @enderror">
                            @error('verificationSecret')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="backToEmailStep"
                                class="w-1/3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 px-4 rounded-xl transition-all">
                                {{ __('Back') }}
                            </button>
                            <button type="submit" wire:loading.attr="disabled"
                                class="w-2/3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-200 shadow-md hover:shadow-lg">
                                {{ __('Continue') }}
                            </button>
                        </div>
                    </form>
                @endif

                <div class="mt-6 text-center">
                    <p class="text-sm text-slate-500">
                        {{ __('Not a member yet?') }}
                        <a href="{{ route('apply') }}"
                            class="text-emerald-600 font-semibold hover:underline">{{ __('Apply for membership') }}</a>
                    </p>
                    <p class="text-sm text-slate-500 mt-2">
                        {{ __('Applied already?') }}
                        <a href="{{ route('application.status') }}"
                            class="text-teal-600 font-semibold hover:underline">{{ __('Check your status') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>