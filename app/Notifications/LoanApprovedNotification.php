<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly float $amount,
        public readonly int $installments,
        public readonly string $dueDate
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ACTIVITY,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => 'Loan Approved',
            'body'    => 'Your loan of ﷼' . number_format($this->amount, 2) . ' has been approved with ' . $this->installments . ' monthly installments.',
            'icon'    => 'heroicon-o-check-circle',
            'color'   => 'success',
            'actions' => [
                ['label' => 'View Loans', 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amountFormatted = '﷼' . number_format($this->amount, 2);

        $mail = (new MailMessage)
            ->subject('FundFlow — Loan Approved!')
            ->greeting("Dear {$notifiable->name},")
            ->line("Your loan application for **{$amountFormatted}** has been approved.")
            ->line("Repayment Details:")
            ->line("• Amount: {$amountFormatted}")
            ->line("• Installments: {$this->installments} monthly payments")
            ->line("• Final due date: {$this->dueDate}")
            ->action('View Loan Details', url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => 'FundFlow — Loan Approved!',
            'body' => "Loan approved for {$notifiable->name}. Amount: {$amountFormatted}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: Loan approved for ﷼" . number_format($this->amount, 2) . " ({$this->installments} installments). Login to view details: " . url('/member');

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
        return "✅ *FundFlow Loan Approved*\n\nDear {$notifiable->name},\n\nYour loan of *﷼" . number_format($this->amount, 2) . "* has been approved.\n\n*Installments:* {$this->installments} monthly payments\n*Due Date:* {$this->dueDate}\n\nView details: " . url('/member');
    }
}
