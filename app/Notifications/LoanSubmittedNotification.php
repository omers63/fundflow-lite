<?php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Loan $loan) {}

    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => 'Loan Application Received',
            'body'  => "Your loan application for SAR " . number_format($this->loan->amount_requested, 2) . " has been received and is under review.",
            'icon'  => 'heroicon-o-document-check',
            'color' => 'info',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_requested, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => 'Loan Application Received', 'body' => "Application for {$amount} received.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject('FundFlow — Loan Application Received')->greeting("Dear {$notifiable->name},")->line("Your loan application for **{$amount}** has been received.")->line("You will be notified once it is reviewed.")->action('View My Loans', url('/member'));
    }
}
