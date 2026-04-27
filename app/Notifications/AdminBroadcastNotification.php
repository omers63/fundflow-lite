<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
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
    ) {
    }

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
            'title' => $this->subject,
            'body' => $this->body,
            'icon' => 'heroicon-o-megaphone',
            'color' => 'info',
            'actions' => [
                ['label' => $this->tr('View Portal', 'عرض البوابة'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = ['name' => $notifiable->name, 'subject' => $this->subject, 'body' => $this->body];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('admin_broadcast', 'subject', $locale, $this->tr('FundFlow — :subject', 'FundFlow — :subject', ['subject' => $this->subject])),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('admin_broadcast', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('admin_broadcast', 'body', $locale, $this->body),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('admin_broadcast', 'action_label', $locale, $this->tr('View My Account', 'عرض حسابي')),
            $vars
        );

        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        $mail->action($actionLabel, url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body' => $this->body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
