<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanInstallment;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanInstallmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanInstallment');
    }

    public function view(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('View:LoanInstallment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanInstallment');
    }

    public function update(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('Update:LoanInstallment');
    }

    public function delete(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('Delete:LoanInstallment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanInstallment');
    }

    public function restore(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('Restore:LoanInstallment');
    }

    public function forceDelete(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('ForceDelete:LoanInstallment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanInstallment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanInstallment');
    }

    public function replicate(AuthUser $authUser, LoanInstallment $loanInstallment): bool
    {
        return $authUser->can('Replicate:LoanInstallment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanInstallment');
    }

}