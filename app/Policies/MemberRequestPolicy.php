<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MemberRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MemberRequest');
    }

    public function view(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('View:MemberRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MemberRequest');
    }

    public function update(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('Update:MemberRequest');
    }

    public function delete(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('Delete:MemberRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MemberRequest');
    }

    public function restore(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('Restore:MemberRequest');
    }

    public function forceDelete(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('ForceDelete:MemberRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MemberRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MemberRequest');
    }

    public function replicate(AuthUser $authUser, MemberRequest $memberRequest): bool
    {
        return $authUser->can('Replicate:MemberRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MemberRequest');
    }

}