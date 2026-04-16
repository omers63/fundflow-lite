<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MemberDelinquencyRestoredNotification extends Notification
{
    use Queueable;

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => 'Membership restored',
            'body' => 'Your membership is active again. Contribution and repayment obligations are back in your name under the usual rules.',
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'actions' => [
                ['label' => 'Member portal', 'url' => url('/member')],
            ],
        ];
    }
}
