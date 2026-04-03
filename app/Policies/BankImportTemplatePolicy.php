<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankImportTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BankImportTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BankImportTemplate');
    }

    public function view(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('View:BankImportTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BankImportTemplate');
    }

    public function update(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('Update:BankImportTemplate');
    }

    public function delete(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('Delete:BankImportTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BankImportTemplate');
    }

    public function restore(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('Restore:BankImportTemplate');
    }

    public function forceDelete(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('ForceDelete:BankImportTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BankImportTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BankImportTemplate');
    }

    public function replicate(AuthUser $authUser, BankImportTemplate $bankImportTemplate): bool
    {
        return $authUser->can('Replicate:BankImportTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BankImportTemplate');
    }
}
