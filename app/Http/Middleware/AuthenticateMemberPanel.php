<?php

namespace App\Http\Middleware;

use App\Models\User;
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
        $guard = Filament::auth();

        if (!$guard->check()) {
            $this->unauthenticated($request, $guards);
        }

        Auth::shouldUse(Filament::getAuthGuard());

        /** @var Authenticatable $authUser */
        $authUser = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($authUser instanceof FilamentUser && !$authUser->canAccessPanel($panel)) {
            if (
                $panel->getId() === 'member'
                && $authUser instanceof User
                && $authUser->role === 'member'
                && in_array($authUser->member?->status, ['suspended', 'terminated'], true)
            ) {
                $memberStatus = $authUser->member?->status;

                Auth::guard(Filament::getAuthGuard())->logout();
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

