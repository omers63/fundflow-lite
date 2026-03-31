<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MonthlyStatement;
use Illuminate\Auth\Access\HandlesAuthorization;

class MonthlyStatementPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MonthlyStatement');
    }

    public function view(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('View:MonthlyStatement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MonthlyStatement');
    }

    public function update(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('Update:MonthlyStatement');
    }

    public function delete(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('Delete:MonthlyStatement');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MonthlyStatement');
    }

    public function restore(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('Restore:MonthlyStatement');
    }

    public function forceDelete(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('ForceDelete:MonthlyStatement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MonthlyStatement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MonthlyStatement');
    }

    public function replicate(AuthUser $authUser, MonthlyStatement $monthlyStatement): bool
    {
        return $authUser->can('Replicate:MonthlyStatement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MonthlyStatement');
    }

}