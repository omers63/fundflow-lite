<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan, public readonly string $reason = '') {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ACTIVITY,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => 'Loan Request Cancelled', 'body' => "Your loan application for SAR " . number_format($this->loan->amount_requested, 2) . " has been cancelled." . ($this->reason ? " Reason: {$this->reason}" : ''), 'icon' => 'heroicon-o-x-circle', 'color' => 'gray'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_requested, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Request Cancelled', 'body' => "Loan {$amount} cancelled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — Loan Request Cancelled')->greeting("Dear {$notifiable->name},")->line("Your loan application for **{$amount}** has been cancelled.")->when($this->reason, fn ($m) => $m->line("**Reason:** {$this->reason}"))->action('View My Loans', url('/member'));
    }
}
