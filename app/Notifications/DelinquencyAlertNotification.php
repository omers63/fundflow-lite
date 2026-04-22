<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class DelinquencyAlertNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly int $overdueCount)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::ACCOUNT_ALERTS,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => $this->tr('Overdue Installments Alert', 'تنبيه الأقساط المتأخرة'),
            'body'    => $this->tr('You have :count overdue loan installment(s). Please settle them to maintain your membership standing.', 'لديك :count قسط/أقساط قرض متأخرة. يرجى تسويتها للحفاظ على حالة العضوية.', ['count' => $this->overdueCount]),
            'icon'    => 'heroicon-o-exclamation-triangle',
            'color'   => 'danger',
            'actions' => [
                ['label' => $this->tr('View Installments', 'عرض الأقساط'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — Overdue Installments Alert', 'FundFlow — تنبيه الأقساط المتأخرة'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('This is a reminder that you have **:count overdue loan installment(s)**.', 'تذكير: لديك **:count قسط/أقساط قرض متأخرة**.', ['count' => $this->overdueCount]))
            ->line($this->tr('Failure to clear overdue installments may affect your loan eligibility and membership standing.', 'عدم سداد الأقساط المتأخرة قد يؤثر على أهليتك للقرض وحالة عضويتك.'))
            ->line($this->tr('Please log in to your member portal to make the outstanding payments.', 'يرجى تسجيل الدخول إلى بوابة الأعضاء لسداد المستحقات.'))
            ->action($this->tr('Make Payment', 'إجراء السداد'), url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('FundFlow — Overdue Installments Alert', 'FundFlow — تنبيه الأقساط المتأخرة'),
            'body' => "Delinquency alert for {$notifiable->name}. Overdue installments: {$this->overdueCount}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr('FundFlow Alert: You have :count overdue loan installment(s). Please login to settle: :url', 'تنبيه FundFlow: لديك :count قسط/أقساط قرض متأخرة. يرجى تسجيل الدخول للتسوية: :url', ['count' => $this->overdueCount, 'url' => url('/member')]);

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'sms',
            'subject' => null,
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return $this->tr("⚠️ *FundFlow — Overdue Alert*\n\nDear :name,\n\nYou have *:count overdue installment(s)*.\n\nPlease settle them to maintain your membership standing.\n\nLogin: :url", "⚠️ *FundFlow — تنبيه تأخر*\n\nعزيزي/عزيزتي :name،\n\nلديك *:count قسط/أقساط متأخرة*.\n\nيرجى التسوية للحفاظ على حالة عضويتك.\n\nتسجيل الدخول: :url", ['name' => $notifiable->name, 'count' => $this->overdueCount, 'url' => url('/member')]);
    }
}
