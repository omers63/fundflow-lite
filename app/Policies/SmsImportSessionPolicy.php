<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SmsImportSession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SmsImportSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsImportSession');
    }

    public function view(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('View:SmsImportSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmsImportSession');
    }

    public function update(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('Update:SmsImportSession');
    }

    public function delete(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('Delete:SmsImportSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmsImportSession');
    }

    public function restore(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('Restore:SmsImportSession');
    }

    public function forceDelete(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('ForceDelete:SmsImportSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmsImportSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmsImportSession');
    }

    public function replicate(AuthUser $authUser, SmsImportSession $smsImportSession): bool
    {
        return $authUser->can('Replicate:SmsImportSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmsImportSession');
    }
}
