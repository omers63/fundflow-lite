<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ?string $reason = null)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::MEMBERSHIP,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => 'Membership Application Update',
            'body'    => $this->reason
                ? 'Your membership application was not approved. Reason: ' . $this->reason
                : 'Your membership application could not be approved at this time.',
            'icon'    => 'heroicon-o-x-circle',
            'color'   => 'danger',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('FundFlow — Membership Application Update')
            ->greeting("Dear {$notifiable->name},")
            ->line('Thank you for your interest in joining FundFlow.')
            ->line('After careful review, we regret to inform you that your membership application could not be approved at this time.');

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        $mail->line('If you believe this decision was made in error or have any questions, please contact us at admin@fundflow.sa.')
             ->action('Contact Us', 'mailto:admin@fundflow.sa');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => 'FundFlow — Membership Application Update',
            'body' => "Membership rejected for {$notifiable->name}. Reason: {$this->reason}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
