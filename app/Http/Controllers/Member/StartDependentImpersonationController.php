<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\ImpersonationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class StartDependentImpersonationController extends Controller
{
    public function __invoke(Member $dependent): RedirectResponse
    {
        $memberGuardName = Filament::getPanel('member')?->getAuthGuard() ?? 'web';
        $actor = auth()->guard($memberGuardName)->user();
        if (!$actor instanceof User) {
            $actor = auth()->user();
        }
        if (!$actor instanceof User) {
            abort(403);
        }

        $parentMember = Member::query()->where('user_id', $actor->id)->first();
        if (!$parentMember || (int) $dependent->parent_id !== (int) $parentMember->id) {
            abort(403);
        }

        $dependentUser = $dependent->user;
        if (!$dependentUser instanceof User) {
            abort(403);
        }

        $memberPanel = Filament::getPanel('member');
        if ($memberPanel !== null && !$dependentUser->canAccessPanel($memberPanel)) {
            return redirect('/member/my-dependents');
        }

        app(ImpersonationService::class)->start($actor, $dependentUser, $dependent);
        request()->session()->save();

        return redirect('/member');
    }
}
