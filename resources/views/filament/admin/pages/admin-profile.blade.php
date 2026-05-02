<x-filament-panels::page>
    @php
        $user = auth()->user();
    @endphp

    <div class="space-y-6">

        <div class="admin-profile-identity-card">
            <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                <div
                    class="relative flex h-20 w-20 flex-shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-white/30 bg-white/20 text-3xl font-bold text-white select-none ring-2 ring-white/30">
                    @if($url = $user?->avatarPublicUrl())
                        <img src="{{ $url }}" alt="{{ $user?->name }}"
                            class="absolute inset-0 h-full w-full object-cover object-center">
                    @else
                        {{ strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="admin-profile-identity-heading">{{ $user?->name }}</p>
                    <div class="mt-2 flex flex-wrap gap-3 text-sm">
                        <span class="admin-profile-identity-muted flex items-center gap-1">
                            <x-heroicon-o-envelope class="h-4 w-4 shrink-0" /> {{ $user?->email }}
                        </span>
                        @if($user?->phone)
                            <span class="admin-profile-identity-muted flex items-center gap-1">
                                <x-heroicon-o-phone class="h-4 w-4 shrink-0" />
                                <x-phone-display :value="$user?->phone" class="admin-profile-identity-muted !font-mono" />
                            </span>
                        @endif
                    </div>
                    <p class="admin-profile-identity-subtle mt-2 text-xs">
                        {{ __('app.member.preferred_language') }}:
                        {{ $user?->preferred_locale === 'ar' ? __('Arabic') : __('English') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-xl bg-gradient-to-br from-slate-100/90 via-white to-blue-50 dark:from-slate-800 dark:via-gray-900 dark:to-blue-950/20 ring-1 ring-slate-200/80 dark:ring-slate-600/40 p-5 shadow-md">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ __('Account Security') }}</h3>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Email address') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $user?->email }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Phone number') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">
                        @if($user?->phone)
                            <x-phone-display :value="$user->phone" class="!font-mono" />
                        @else
                            {{ __('— not set') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Password') }}</dt>
                    <dd class="font-medium text-gray-500">●●●●●●●● <span
                            class="text-xs">{{ __('(use :action above to update)', ['action' => __('app.admin.edit_profile')]) }}</span>
                    </dd>
                </div>
            </dl>
        </div>

    </div>
</x-filament-panels::page>