<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SmsImportTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SmsImportTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsImportTemplate');
    }

    public function view(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('View:SmsImportTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmsImportTemplate');
    }

    public function update(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('Update:SmsImportTemplate');
    }

    public function delete(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('Delete:SmsImportTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmsImportTemplate');
    }

    public function restore(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('Restore:SmsImportTemplate');
    }

    public function forceDelete(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('ForceDelete:SmsImportTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmsImportTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmsImportTemplate');
    }

    public function replicate(AuthUser $authUser, SmsImportTemplate $smsImportTemplate): bool
    {
        return $authUser->can('Replicate:SmsImportTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmsImportTemplate');
    }
}
