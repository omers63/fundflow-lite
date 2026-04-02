<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanRepaymentDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Loan            $loan,
        public readonly LoanInstallment $installment,
        public readonly Carbon          $deadline,
        public readonly float           $cashBalance,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', TwilioChannel::class, TwilioWhatsAppChannel::class];
    }

    private function shortBody(): string
    {
        return sprintf(
            'Loan #%d installment %d/%d (SAR %s) is due by %s. Cash balance: SAR %s.',
            $this->loan->id,
            $this->installment->installment_number,
            $this->loan->installments_count,
            number_format((float) $this->installment->amount, 2),
            $this->deadline->format('d M Y'),
            number_format($this->cashBalance, 2)
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $sufficient = $this->cashBalance >= (float) $this->installment->amount;
        return ['title' => "Loan Repayment Due – Installment #{$this->installment->installment_number}", 'body' => $this->shortBody(), 'icon' => $sufficient ? 'heroicon-o-bell' : 'heroicon-o-exclamation-triangle', 'color' => $sufficient ? 'warning' : 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sufficient = $this->cashBalance >= (float) $this->installment->amount;
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Repayment Due', 'body' => $this->shortBody(), 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject("FundFlow — Loan Repayment Due")->greeting("Dear {$notifiable->name},")->line("Installment **#{$this->installment->installment_number}** of **{$this->loan->installments_count}** is due.")->line("**Amount:** SAR " . number_format((float) $this->installment->amount, 2))->line("**Deadline:** " . $this->deadline->format('d F Y'))->line("**Cash Balance:** SAR " . number_format($this->cashBalance, 2));
        if (!$sufficient) { $mail->line("⚠️ Insufficient cash balance. Please fund your account before the deadline."); }
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
        return "🔔 *FundFlow – Loan Repayment Due*\n\nDear {$notifiable->name},\n\n" . $this->shortBody() . "\n\n" . url('/member');
    }
}
