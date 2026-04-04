<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmsTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmsTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmsTransaction');
    }

    public function view(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('View:SmsTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmsTransaction');
    }

    public function update(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('Update:SmsTransaction');
    }

    public function delete(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('Delete:SmsTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmsTransaction');
    }

    public function restore(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('Restore:SmsTransaction');
    }

    public function forceDelete(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('ForceDelete:SmsTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmsTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmsTransaction');
    }

    public function replicate(AuthUser $authUser, SmsTransaction $smsTransaction): bool
    {
        return $authUser->can('Replicate:SmsTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmsTransaction');
    }

}