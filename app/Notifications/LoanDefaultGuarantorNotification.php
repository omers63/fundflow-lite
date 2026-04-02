<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDefaultGuarantorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Loan            $loan,
        public readonly LoanInstallment $installment,
    ) {}

    public function via(mixed $notifiable): array { return ['mail', 'database']; }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => 'Guarantor Debit: Borrower Default', 'body' => "SAR " . number_format((float) $this->installment->amount, 2) . " has been debited from your fund account as guarantor for {$this->loan->member->user->name}'s Loan #{$this->loan->id}.", 'icon' => 'heroicon-o-exclamation-circle', 'color' => 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $borrower = $this->loan->member->user->name;
        $amount   = 'SAR ' . number_format((float) $this->installment->amount, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Guarantor Debit Notification', 'body' => "Debit {$amount} for {$borrower}.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — Guarantor Debit Notification')->greeting("Dear {$notifiable->name},")->line("As the guarantor for **{$borrower}** (Loan #{$this->loan->id}), the amount of **{$amount}** (installment #{$this->installment->installment_number}) has been debited from your fund account due to the borrower's missed repayment.")->line("You will be released from your guarantee once the borrower fully settles the fund's portion.")->action('View My Account', url('/member'));
    }
}
