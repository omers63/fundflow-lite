<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateMemberPanel
{
    /**
     * @param  array<string>  $guards
     */
    protected function unauthenticated(Request $request, array $guards): never
    {
        throw new AuthenticationException(
            message: 'Unauthenticated.',
            guards: $guards,
            redirectTo: route('login'),
        );
    }

    public function handle(Request $request, \Closure $next, ...$guards)
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $memberGuardName = Filament::getPanel('member')?->getAuthGuard() ?? Filament::getAuthGuard();
        $guard = Auth::guard($memberGuardName);

        if (!$guard->check()) {
            $impersonatedUserId = (int) $request->session()->get('impersonated_user_id');
            if ($impersonatedUserId > 0) {
                $impersonatedUser = User::find($impersonatedUserId);
                if (
                    $impersonatedUser instanceof User
                    && $impersonatedUser->canAccessPanel($panel)
                ) {
                    Auth::guard($memberGuardName)->login($impersonatedUser);
                    if ($memberGuardName !== 'web') {
                        Auth::guard('web')->login($impersonatedUser);
                    }
                    $guard = Auth::guard($memberGuardName);
                }
            }
        }

        if (!$guard->check() && Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            if ($webUser instanceof User && $webUser->canAccessPanel($panel)) {
                Auth::guard($memberGuardName)->login($webUser);
                $guard = Auth::guard($memberGuardName);
            }
        }

        if (!$guard->check()) {
            $this->unauthenticated($request, $guards);
        }

        if (
            $request->isMethod('post')
            && $request->path() === 'member/logout'
            && (int) $request->session()->get('impersonator_user_id') > 0
        ) {
            app(ImpersonationService::class)->stop();

            return redirect('/member');
        }

        Auth::shouldUse($memberGuardName);

        /** @var Authenticatable $authUser */
        $authUser = $guard->user();

        if ($authUser instanceof FilamentUser && !$authUser->canAccessPanel($panel)) {
            if (
                $panel->getId() === 'member'
                && $authUser instanceof User
                && $authUser->role === 'member'
                && in_array($authUser->member?->status, ['suspended', 'terminated'], true)
            ) {
                $memberStatus = $authUser->member?->status;

                Auth::guard($memberGuardName)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->flash(
                    $memberStatus === 'terminated' ? 'member_terminated_notice' : 'member_suspended_notice',
                    true
                );

                return redirect()->route('login');
            }

            abort(403);
        }

        return $next($request);
    }
}

