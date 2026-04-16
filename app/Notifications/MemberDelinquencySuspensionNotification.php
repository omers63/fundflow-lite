<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class MemberDelinquencySuspensionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $trailingConsecutive,
        public readonly int $rollingTotal,
    ) {}

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
            'title' => 'Membership suspended — delinquency',
            'body' => 'Your membership has been suspended due to repeated missed contributions or loan repayments. '
                . "Trailing consecutive misses: {$this->trailingConsecutive}. "
                . "Rolling total (configured window): {$this->rollingTotal}. "
                . 'Repayment obligations may transfer to your guarantor. Contact the fund office.',
            'icon' => 'heroicon-o-no-symbol',
            'color' => 'danger',
            'actions' => [
                ['label' => 'Contact office', 'url' => url('/')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('FundFlow — Membership suspended (delinquency)')
            ->greeting("Dear {$notifiable->name},")
            ->line('Your membership has been **suspended** because contribution or loan repayment obligations were not met under the fund’s delinquency policy.')
            ->line("Consecutive missed cycles (trailing): **{$this->trailingConsecutive}**.")
            ->line("Total misses in the rolling window: **{$this->rollingTotal}**.")
            ->line('Outstanding loan repayments may be collected from your guarantor while you remain suspended. Please contact the fund office to regularize your account.')
            ->line('You will not be able to sign in to the member portal until your status is restored by the administration.');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => 'FundFlow — Membership suspended (delinquency)',
            'body' => "Delinquency suspension for {$notifiable->name}.",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: Your membership is suspended due to missed contributions/repayments (consecutive {$this->trailingConsecutive}, rolling {$this->rollingTotal}). Contact the fund office.";

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
        return "⚠️ *FundFlow — Membership suspended*\n\nDear {$notifiable->name},\n\nYour membership has been suspended due to repeated missed contributions or loan repayments.\n\nTrailing consecutive: *{$this->trailingConsecutive}*\nRolling total (window): *{$this->rollingTotal}*\n\nRepayment obligations may transfer to your guarantor. Contact the fund office.";
    }
}
