<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectSuspendedMemberFromPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        $member = $user->member;

        if ($user->role === 'member' && $member?->status === 'suspended') {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('member_suspended_notice', true);

            return redirect()->to('/member/login');
        }

        return $next($request);
    }
}

