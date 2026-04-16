<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => $this->subject,
            'body'    => $this->body,
            'icon'    => 'heroicon-o-megaphone',
            'color'   => 'info',
            'actions' => [
                ['label' => 'View Portal', 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("FundFlow — {$this->subject}")
            ->greeting("Dear {$notifiable->name},")
            ->line($this->body)
            ->action('View My Account', url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => "FundFlow — {$this->subject}",
            'body'    => $this->body,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
