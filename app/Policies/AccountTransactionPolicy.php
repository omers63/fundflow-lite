<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AccountTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AccountTransaction');
    }

    public function view(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('View:AccountTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AccountTransaction');
    }

    public function update(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('Update:AccountTransaction');
    }

    public function delete(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('Delete:AccountTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AccountTransaction');
    }

    public function restore(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('Restore:AccountTransaction');
    }

    public function forceDelete(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('ForceDelete:AccountTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AccountTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AccountTransaction');
    }

    public function replicate(AuthUser $authUser, AccountTransaction $accountTransaction): bool
    {
        return $authUser->can('Replicate:AccountTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AccountTransaction');
    }

}