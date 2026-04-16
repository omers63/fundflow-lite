<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NotificationLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NotificationLog');
    }

    public function view(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('View:NotificationLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NotificationLog');
    }

    public function update(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('Update:NotificationLog');
    }

    public function delete(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('Delete:NotificationLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:NotificationLog');
    }

    public function restore(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('Restore:NotificationLog');
    }

    public function forceDelete(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('ForceDelete:NotificationLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NotificationLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NotificationLog');
    }

    public function replicate(AuthUser $authUser, NotificationLog $notificationLog): bool
    {
        return $authUser->can('Replicate:NotificationLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NotificationLog');
    }

}