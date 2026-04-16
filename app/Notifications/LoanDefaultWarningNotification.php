<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDefaultWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Loan            $loan,
        public readonly LoanInstallment $installment,
        public readonly int             $defaultCount,
        public readonly int             $graceCount,
    ) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ALERTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $remaining = $this->graceCount - $this->defaultCount + 1;
        return ['title' => 'Loan Repayment Default Warning', 'body' => "You have missed {$this->defaultCount} repayment(s) on Loan #{$this->loan->id}. " . ($remaining > 0 ? "You have {$remaining} more chance(s) before your guarantor is held liable." : "Your guarantor will be notified on the next default."), 'icon' => 'heroicon-o-exclamation-triangle', 'color' => 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Default Warning', 'body' => "Default #{$this->defaultCount} on Loan #{$this->loan->id}.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — ⚠️ Loan Repayment Default Warning')->greeting("Dear {$notifiable->name},")->line("You have **{$this->defaultCount}** missed repayment(s) on **Loan #{$this->loan->id}**.")->line("**Overdue installment:** #{$this->installment->installment_number} — SAR " . number_format((float) $this->installment->amount, 2))->line("If you default on **" . ($this->graceCount + 1) . " or more** repayment cycles (consecutive or not), your guarantor will be held liable and the amount will be debited from their fund account.")->line("⚠️ Your membership may be cancelled if defaults continue.")->action('View My Loans', url('/member'));
    }
}
