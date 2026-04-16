<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSettledNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ACTIVITY,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => 'Loan Fully Settled', 'body' => "Your loan of SAR " . number_format($this->loan->amount_approved, 2) . " is now fully settled. Congratulations!", 'icon' => 'heroicon-o-check-badge', 'color' => 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Settled', 'body' => "Loan {$amount} settled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — Loan Fully Settled')->greeting("Dear {$notifiable->name},")->line("🎉 Your loan of **{$amount}** has been fully settled. Monthly contributions resume next cycle.")->action('View My Account', url('/member'));
    }
}
