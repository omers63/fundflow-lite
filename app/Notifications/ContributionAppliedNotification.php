<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\Contribution;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class ContributionAppliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Contribution $contribution,
        public readonly float        $cashBalance,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', TwilioChannel::class, TwilioWhatsAppChannel::class];
    }

    private function periodLabel(): string
    {
        return date('F', mktime(0, 0, 0, $this->contribution->month, 1)) . ' ' . $this->contribution->year;
    }

    private function shortBody(): string
    {
        $late = $this->contribution->is_late ? ' (late)' : '';
        return sprintf(
            'Your contribution of ﷼%s for %s has been applied%s. Remaining cash balance: ﷼%s.',
            number_format((float) $this->contribution->amount, 2),
            $this->periodLabel(),
            $late,
            number_format($this->cashBalance, 2)
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => "Contribution Applied – {$this->periodLabel()}",
            'body'    => $this->shortBody(),
            'icon'    => $this->contribution->is_late ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle',
            'color'   => $this->contribution->is_late ? 'warning' : 'success',
            'actions' => [
                ['label' => 'View My Contributions', 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = number_format((float) $this->contribution->amount, 2);

        $mail = (new MailMessage)
            ->subject("FundFlow — Account Statement: {$this->periodLabel()} Contribution")
            ->greeting("Dear {$notifiable->name},")
            ->line("Your monthly contribution for **{$this->periodLabel()}** has been successfully applied.")
            ->line("---")
            ->line("**Account Statement**")
            ->line("• Period: {$this->periodLabel()}")
            ->line("• Contribution Amount: ﷼{$amount}")
            ->line("• Applied On: " . now()->format('d F Y H:i'))
            ->line("• Remaining Cash Balance: ﷼" . number_format($this->cashBalance, 2));

        if ($this->contribution->is_late) {
            $mail->line("⚠️ This contribution was recorded as **late** (applied after the 5th of the due month).");
        }

        $mail->action('View My Account', url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => "FundFlow — Account Statement: {$this->periodLabel()} Contribution",
            'body'    => $this->shortBody(),
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: " . $this->shortBody() . " " . url('/member');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'sms',
            'subject' => null,
            'body'    => $body,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        $amount = number_format((float) $this->contribution->amount, 2);
        $icon   = $this->contribution->is_late ? '⚠️' : '✅';

        return "{$icon} *FundFlow – Contribution Applied*\n\n"
            . "Dear {$notifiable->name},\n\n"
            . "*Period:* {$this->periodLabel()}\n"
            . "*Amount:* ﷼{$amount}\n"
            . "*Applied On:* " . now()->format('d F Y H:i') . "\n"
            . "*Remaining Cash Balance:* ﷼" . number_format($this->cashBalance, 2) . "\n"
            . ($this->contribution->is_late ? "\n⚠️ This contribution was recorded as late.\n" : '')
            . "\n" . url('/member');
    }
}
