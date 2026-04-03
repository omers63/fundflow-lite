<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoanTier;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LoanTierPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanTier');
    }

    public function view(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('View:LoanTier');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanTier');
    }

    public function update(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('Update:LoanTier');
    }

    public function delete(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('Delete:LoanTier');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanTier');
    }

    public function restore(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('Restore:LoanTier');
    }

    public function forceDelete(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('ForceDelete:LoanTier');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanTier');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanTier');
    }

    public function replicate(AuthUser $authUser, LoanTier $loanTier): bool
    {
        return $authUser->can('Replicate:LoanTier');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanTier');
    }
}
