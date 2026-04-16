<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class ContributionDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int    $month,
        public readonly int    $year,
        public readonly float  $amount,
        public readonly Carbon $deadline,
        public readonly float  $cashBalance,
    ) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::CONTRIBUTIONS,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    private function periodLabel(): string
    {
        return date('F', mktime(0, 0, 0, $this->month, 1)) . ' ' . $this->year;
    }

    private function shortBody(): string
    {
        return sprintf(
            'Your contribution for %s (﷼%s) is due by %s. Current cash balance: ﷼%s.',
            $this->periodLabel(),
            number_format($this->amount, 2),
            $this->deadline->format('d M Y'),
            number_format($this->cashBalance, 2)
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $sufficient = $this->cashBalance >= $this->amount;

        return [
            'title'   => "Contribution Due – {$this->periodLabel()}",
            'body'    => $this->shortBody(),
            'icon'    => $sufficient ? 'heroicon-o-bell' : 'heroicon-o-exclamation-triangle',
            'color'   => $sufficient ? 'warning' : 'danger',
            'actions' => [
                ['label' => 'View Account', 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sufficient = $this->cashBalance >= $this->amount;

        $mail = (new MailMessage)
            ->subject("FundFlow — {$this->periodLabel()} Contribution Due")
            ->greeting("Dear {$notifiable->name},")
            ->line("Your monthly contribution for **{$this->periodLabel()}** is due.")
            ->line("**Amount due:** ﷼" . number_format($this->amount, 2))
            ->line("**Deadline:** " . $this->deadline->format('d F Y'))
            ->line("**Your current cash balance:** ﷼" . number_format($this->cashBalance, 2));

        if (! $sufficient) {
            $shortfall = $this->amount - $this->cashBalance;
            $mail->line("⚠️ Your cash account is short by ﷼" . number_format($shortfall, 2) . ". Please fund your account before the deadline to avoid a late contribution.");
        }

        $mail->action('View My Account', url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => "FundFlow — {$this->periodLabel()} Contribution Due",
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
        $sufficient = $this->cashBalance >= $this->amount;
        $icon       = $sufficient ? '🔔' : '⚠️';

        return "{$icon} *FundFlow – Contribution Due*\n\n"
            . "Dear {$notifiable->name},\n\n"
            . "Your contribution for *{$this->periodLabel()}* is due.\n\n"
            . "*Amount:* ﷼" . number_format($this->amount, 2) . "\n"
            . "*Deadline:* " . $this->deadline->format('d F Y') . "\n"
            . "*Cash Balance:* ﷼" . number_format($this->cashBalance, 2) . "\n\n"
            . url('/member');
    }
}
