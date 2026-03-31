<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MembershipApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class MembershipApplicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MembershipApplication');
    }

    public function view(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('View:MembershipApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MembershipApplication');
    }

    public function update(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('Update:MembershipApplication');
    }

    public function delete(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('Delete:MembershipApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MembershipApplication');
    }

    public function restore(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('Restore:MembershipApplication');
    }

    public function forceDelete(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('ForceDelete:MembershipApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MembershipApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MembershipApplication');
    }

    public function replicate(AuthUser $authUser, MembershipApplication $membershipApplication): bool
    {
        return $authUser->can('Replicate:MembershipApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MembershipApplication');
    }

}