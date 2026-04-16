<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanDisbursedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

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
            'title' => 'Loan Disbursed',
            'body'  => "Your loan of SAR " . number_format($this->loan->amount_approved, 2) . " has been disbursed. First repayment: " . $this->firstRepaymentLabel(),
            'icon'  => 'heroicon-o-banknotes',
            'color' => 'success',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Disbursed', 'body' => "Loan {$amount} disbursed.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)
            ->subject('FundFlow — Loan Disbursed')
            ->greeting("Dear {$notifiable->name},")
            ->line("Your loan of **{$amount}** has been disbursed.")
            ->line("**Installments:** {$this->loan->installments_count} monthly payments")
            ->line("**Minimum monthly installment:** SAR " . number_format($this->loan->loanTier?->min_monthly_installment ?? 0))
            ->line("**First repayment cycle:** " . $this->firstRepaymentLabel())
            ->line("**Exempted contribution cycle:** " . $this->exemptedLabel())
            ->action('View My Loans', url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: Loan of SAR " . number_format($this->loan->amount_approved, 2) . " disbursed. First repayment: " . $this->firstRepaymentLabel() . ". " . url('/member');
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return "✅ *FundFlow – Loan Disbursed*\n\nDear {$notifiable->name},\n\n"
            . "*Amount:* SAR " . number_format($this->loan->amount_approved, 2) . "\n"
            . "*Installments:* {$this->loan->installments_count} months\n"
            . "*First Repayment:* " . $this->firstRepaymentLabel() . "\n"
            . "*Exempted Cycle:* " . $this->exemptedLabel() . "\n\n"
            . url('/member');
    }

    private function firstRepaymentLabel(): string
    {
        if (! $this->loan->first_repayment_month) return '—';
        return date('F', mktime(0, 0, 0, $this->loan->first_repayment_month, 1)) . ' ' . $this->loan->first_repayment_year;
    }

    private function exemptedLabel(): string
    {
        if (! $this->loan->exempted_month) return '—';
        return date('F', mktime(0, 0, 0, $this->loan->exempted_month, 1)) . ' ' . $this->loan->exempted_year;
    }
}
