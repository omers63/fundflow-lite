<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FundTier;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FundTierPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FundTier');
    }

    public function view(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('View:FundTier');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FundTier');
    }

    public function update(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('Update:FundTier');
    }

    public function delete(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('Delete:FundTier');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FundTier');
    }

    public function restore(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('Restore:FundTier');
    }

    public function forceDelete(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('ForceDelete:FundTier');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FundTier');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FundTier');
    }

    public function replicate(AuthUser $authUser, FundTier $fundTier): bool
    {
        return $authUser->can('Replicate:FundTier');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FundTier');
    }
}
