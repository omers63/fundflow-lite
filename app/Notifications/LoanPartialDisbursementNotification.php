<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\LoanDisbursement;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanPartialDisbursementNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LoanDisbursement $disbursement,
        public readonly float $totalDisbursed,
        public readonly float $amountApproved,
    ) {}

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
            'title' => 'Loan Partially Disbursed',
            'body'  => 'SAR ' . number_format($this->disbursement->amount, 2) . ' of your loan has been disbursed. '
                . 'Total disbursed so far: SAR ' . number_format($this->totalDisbursed, 2)
                . ' of SAR ' . number_format($this->amountApproved, 2) . '.',
            'icon'  => 'heroicon-o-banknotes',
            'color' => 'info',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $portion   = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total     = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved  = 'SAR ' . number_format($this->amountApproved, 2);
        $remaining = 'SAR ' . number_format(max(0, $this->amountApproved - $this->totalDisbursed), 2);

        NotificationLog::create([
            'user_id'  => $notifiable->id,
            'channel'  => 'mail',
            'subject'  => 'Loan Partially Disbursed',
            'body'     => "Partial disbursement of {$portion}.",
            'status'   => 'sent',
            'sent_at'  => now(),
        ]);

        return (new MailMessage)
            ->subject('FundFlow — Loan Partially Disbursed')
            ->greeting("Dear {$notifiable->name},")
            ->line("A partial disbursement of **{$portion}** has been credited against your approved loan.")
            ->line("**Total disbursed so far:** {$total} of {$approved}")
            ->line("**Remaining to disburse:** {$remaining}")
            ->line('Repayment will begin only after the loan is fully disbursed.')
            ->action('View My Loans', url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $portion  = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total    = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved = 'SAR ' . number_format($this->amountApproved, 2);

        $body = "FundFlow: Partial loan disbursement of {$portion}. Total disbursed: {$total} of {$approved}. Repayment starts after full disbursement. " . url('/member');

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
        $portion   = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total     = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved  = 'SAR ' . number_format($this->amountApproved, 2);
        $remaining = 'SAR ' . number_format(max(0, $this->amountApproved - $this->totalDisbursed), 2);

        return "💰 *FundFlow – Partial Loan Disbursement*\n\n"
            . "Dear {$notifiable->name},\n\n"
            . "*This portion:* {$portion}\n"
            . "*Total disbursed:* {$total} of {$approved}\n"
            . "*Remaining:* {$remaining}\n\n"
            . "Repayment begins after the loan is fully disbursed.\n\n"
            . url('/member');
    }
}
