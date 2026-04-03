<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankImportSession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BankImportSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BankImportSession');
    }

    public function view(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('View:BankImportSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BankImportSession');
    }

    public function update(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('Update:BankImportSession');
    }

    public function delete(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('Delete:BankImportSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BankImportSession');
    }

    public function restore(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('Restore:BankImportSession');
    }

    public function forceDelete(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('ForceDelete:BankImportSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BankImportSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BankImportSession');
    }

    public function replicate(AuthUser $authUser, BankImportSession $bankImportSession): bool
    {
        return $authUser->can('Replicate:BankImportSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BankImportSession');
    }
}
