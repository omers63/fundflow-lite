<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Contribution;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContributionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Contribution');
    }

    public function view(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('View:Contribution');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Contribution');
    }

    public function update(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('Update:Contribution');
    }

    public function delete(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('Delete:Contribution');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Contribution');
    }

    public function restore(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('Restore:Contribution');
    }

    public function forceDelete(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('ForceDelete:Contribution');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Contribution');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Contribution');
    }

    public function replicate(AuthUser $authUser, Contribution $contribution): bool
    {
        return $authUser->can('Replicate:Contribution');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Contribution');
    }

}