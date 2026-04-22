<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSubmittedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

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
        return [
            'title' => $this->tr('Loan Application Received', 'تم استلام طلب القرض'),
            'body'  => $this->tr(
                'Your loan application for SAR :amount has been received and is under review.',
                'تم استلام طلب قرضك بمبلغ SAR :amount وهو الآن قيد المراجعة.',
                ['amount' => number_format($this->loan->amount_requested, 2)],
            ),
            'icon'  => 'heroicon-o-document-check',
            'color' => 'info',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_requested, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Application Received', 'تم استلام طلب القرض'), 'body' => "Application for {$amount} received.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)
            ->subject($this->tr('FundFlow — Loan Application Received', 'FundFlow — تم استلام طلب القرض'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Your loan application for **:amount** has been received.', 'تم استلام طلب قرضك بمبلغ **:amount**.', ['amount' => $amount]))
            ->line($this->tr('You will be notified once it is reviewed.', 'سيتم إشعارك فور الانتهاء من المراجعة.'))
            ->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }
}
