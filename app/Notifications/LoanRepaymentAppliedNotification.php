<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanRepaymentAppliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Loan            $loan,
        public readonly LoanInstallment $installment,
        public readonly float           $cashBalance,
        public readonly bool            $isLate = false,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', TwilioChannel::class, TwilioWhatsAppChannel::class];
    }

    private function shortBody(): string
    {
        $late = $this->isLate ? ' (late)' : '';
        return sprintf(
            'Installment %d/%d (SAR %s) applied%s. Remaining: SAR %s. Cash balance: SAR %s.',
            $this->installment->installment_number,
            $this->loan->installments_count,
            number_format((float) $this->installment->amount, 2),
            $late,
            number_format($this->loan->remaining_amount, 2),
            number_format($this->cashBalance, 2)
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => "Loan Repayment Applied – Installment #{$this->installment->installment_number}", 'body' => $this->shortBody(), 'icon' => $this->isLate ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle', 'color' => $this->isLate ? 'warning' : 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Repayment Applied', 'body' => $this->shortBody(), 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject("FundFlow — Loan Repayment Account Statement")->greeting("Dear {$notifiable->name},")->line("**Installment #{$this->installment->installment_number}** of **{$this->loan->installments_count}** has been applied.")->line("**Amount:** SAR " . number_format((float) $this->installment->amount, 2))->line("**Remaining Loan Balance:** SAR " . number_format($this->loan->remaining_amount, 2))->line("**Remaining Cash Balance:** SAR " . number_format($this->cashBalance, 2));
        if ($this->isLate) { $mail->line("⚠️ This repayment was recorded as late."); }
        return $mail->action('View My Loans', url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: " . $this->shortBody() . " " . url('/member');
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        $icon = $this->isLate ? '⚠️' : '✅';
        return "{$icon} *FundFlow – Loan Repayment Applied*\n\nDear {$notifiable->name},\n\n" . $this->shortBody() . "\n\n" . url('/member');
    }
}
