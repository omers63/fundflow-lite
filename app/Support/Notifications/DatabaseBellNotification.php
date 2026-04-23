<?php

namespace App\Support\Notifications;

use Filament\Notifications\Notification as BaseNotification;
use Illuminate\Contracts\Auth\Authenticatable;

class DatabaseBellNotification extends BaseNotification
{
    public function send(): static
    {
        $user = auth()->user();

        if ($user instanceof Authenticatable) {
            $this->sendToDatabase($user, isEventDispatched: true);
        }

        // Guard: never push popup/toast notifications to session.
        // All in-panel notifications should live in the database bell.
        return $this;
    }
}

