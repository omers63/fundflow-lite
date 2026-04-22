<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBroadcastNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::BROADCASTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => $this->subject,
            'body'    => $this->body,
            'icon'    => 'heroicon-o-megaphone',
            'color'   => 'info',
            'actions' => [
                ['label' => $this->tr('View Portal', 'عرض البوابة'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — :subject', 'FundFlow — :subject', ['subject' => $this->subject]))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->body)
            ->action($this->tr('View My Account', 'عرض حسابي'), url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('FundFlow — :subject', 'FundFlow — :subject', ['subject' => $this->subject]),
            'body'    => $this->body,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
