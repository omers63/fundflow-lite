<div class="min-h-screen bg-gradient-to-br from-emerald-700 via-teal-800 to-cyan-900 flex items-center justify-center py-8 sm:py-12 px-4">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-700 via-teal-800 to-cyan-900 p-6 sm:p-8 text-center">
            <h2 class="text-2xl font-bold text-white">{{ __('Reset your password') }}</h2>
            <p class="text-emerald-100 text-sm mt-1">{{ __('Enter your email to receive reset instructions.') }}</p>
        </div>

        <div class="p-6 sm:p-8 space-y-5">
            @if($statusMessage)
                <div class="p-3 rounded-xl text-sm {{ $statusType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($statusType === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-50 text-slate-700 border border-slate-200') }}">
                    {{ $statusMessage }}
                </div>
            @endif

            <form wire:submit="sendResetLink" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Email Address') }}</label>
                    <input wire:model="email" type="email" autocomplete="email"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('email') border-red-400 @enderror">
                    @error('email')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold py-3.5 px-6 rounded-xl">
                    {{ __('Send reset link') }}
                </button>
            </form>

            <p class="text-sm text-slate-500 text-center">
                <a href="{{ route('login') }}" class="text-emerald-600 font-semibold hover:underline">{{ __('Back to sign in') }}</a>
            </p>
        </div>
    </div>
</div>
