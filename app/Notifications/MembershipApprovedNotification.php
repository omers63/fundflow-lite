<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class MembershipApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $memberNumber)
    {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail', TwilioChannel::class, TwilioWhatsAppChannel::class];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome to FundFlow — Membership Approved!')
            ->greeting("Dear {$notifiable->name},")
            ->line('Congratulations! Your membership application has been **approved**.')
            ->line("Your member number is: **{$this->memberNumber}**")
            ->line('You can now log in to your member portal to:')
            ->line('• View your contribution history')
            ->line('• Apply for interest-free loans')
            ->line('• Download monthly statements')
            ->action('Sign In to Member Portal', url('/login'))
            ->line('Welcome to the family!');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => 'Welcome to FundFlow — Membership Approved!',
            'body' => "Membership approved for {$notifiable->name}. Member number: {$this->memberNumber}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $message;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: Congratulations {$notifiable->name}! Your membership has been approved. Member No: {$this->memberNumber}. Login at: " . url('/login');

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
        return "🎉 *FundFlow Membership Approved*\n\nDear {$notifiable->name},\n\nYour membership application has been approved!\n\n*Member Number:* {$this->memberNumber}\n\nLogin at: " . url('/login') . "\n\nWelcome to the family!";
    }
}
