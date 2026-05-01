<?php

namespace App\Services;

use App\Models\ImpersonationAudit;
use App\Models\Member;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class ImpersonationService
{
    public function start(User $impersonator, User $impersonatedUser, ?Member $impersonatedMember = null): void
    {
        $originalUserId = session('impersonator_user_id', $impersonator->id);
        session([
            'impersonator_user_id' => $originalUserId,
            'impersonated_user_id' => $impersonatedUser->id,
            'impersonated_member_id' => $impersonatedMember?->id,
            'impersonation_started_at' => now()->toDateTimeString(),
        ]);

        ImpersonationAudit::create([
            'impersonator_user_id' => $impersonator->id,
            'impersonated_user_id' => $impersonatedUser->id,
            'impersonated_member_id' => $impersonatedMember?->id,
            'event' => 'started',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => ['started_from' => 'member_dependents'],
            'occurred_at' => now(),
        ]);

        $memberGuard = $this->memberGuard();
        Auth::shouldUse($memberGuard);
        Auth::guard($memberGuard)->login($impersonatedUser);
        if ($memberGuard !== 'web') {
            Auth::guard('web')->login($impersonatedUser);
        }
    }

    public function stop(): bool
    {
        $impersonatorId = (int) session('impersonator_user_id');
        $impersonatedUserId = (int) session('impersonated_user_id');
        $impersonatedMemberId = session('impersonated_member_id');

        if ($impersonatorId <= 0) {
            return false;
        }

        $impersonator = User::find($impersonatorId);
        if (!$impersonator) {
            return false;
        }

        ImpersonationAudit::create([
            'impersonator_user_id' => $impersonatorId,
            'impersonated_user_id' => $impersonatedUserId ?: $impersonatorId,
            'impersonated_member_id' => is_numeric($impersonatedMemberId) ? (int) $impersonatedMemberId : null,
            'event' => 'stopped',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta' => ['stopped_from' => 'member_profile'],
            'occurred_at' => now(),
        ]);

        $memberGuard = $this->memberGuard();
        Auth::shouldUse($memberGuard);
        Auth::guard($memberGuard)->login($impersonator);
        if ($memberGuard !== 'web') {
            Auth::guard('web')->login($impersonator);
        }
        session()->forget([
            'impersonator_user_id',
            'impersonated_user_id',
            'impersonated_member_id',
            'impersonation_started_at',
        ]);

        return true;
    }

    private function memberGuard(): string
    {
        return Filament::getPanel('member')?->getAuthGuard() ?? Auth::getDefaultDriver();
    }
}
