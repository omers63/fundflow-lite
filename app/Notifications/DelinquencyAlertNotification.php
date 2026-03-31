<?php

namespace App\Notifications;

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

    public function __construct(public readonly int $overdueCount)
    {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', TwilioChannel::class, TwilioWhatsAppChannel::class];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => 'Overdue Installments Alert',
            'body'    => 'You have ' . $this->overdueCount . ' overdue loan installment(s). Please settle them to maintain your membership standing.',
            'icon'    => 'heroicon-o-exclamation-triangle',
            'color'   => 'danger',
            'actions' => [
                ['label' => 'View Installments', 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('FundFlow — Overdue Installments Alert')
            ->greeting("Dear {$notifiable->name},")
            ->line("This is a reminder that you have **{$this->overdueCount} overdue loan installment(s)**.")
            ->line('Failure to clear overdue installments may affect your loan eligibility and membership standing.')
            ->line('Please log in to your member portal to make the outstanding payments.')
            ->action('Make Payment', url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => 'FundFlow — Overdue Installments Alert',
            'body' => "Delinquency alert for {$notifiable->name}. Overdue installments: {$this->overdueCount}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow Alert: You have {$this->overdueCount} overdue loan installment(s). Please login to settle: " . url('/member');

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
        return "⚠️ *FundFlow — Overdue Alert*\n\nDear {$notifiable->name},\n\nYou have *{$this->overdueCount} overdue installment(s)*.\n\nPlease settle them to maintain your membership standing.\n\nLogin: " . url('/member');
    }
}
