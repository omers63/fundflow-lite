<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanEarlySettledNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

    public function via(mixed $notifiable): array { return ['mail', 'database']; }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => 'Loan Early Settlement Complete', 'body' => "Your loan of SAR " . number_format($this->loan->amount_approved, 2) . " has been fully settled. Congratulations!", 'icon' => 'heroicon-o-check-badge', 'color' => 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Early Settlement', 'body' => "Loan {$amount} early settled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — Loan Early Settlement Complete')->greeting("Dear {$notifiable->name},")->line("🎉 Congratulations! Your loan of **{$amount}** has been fully settled.")->action('View My Loans', url('/member'));
    }
}
