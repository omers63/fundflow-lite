<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanCancelledNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

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
        return ['title' => $this->tr('Loan Request Cancelled', 'تم إلغاء طلب القرض'), 'body' => $this->tr('Your loan application for SAR :amount has been cancelled.:reason', 'تم إلغاء طلب قرضك بمبلغ SAR :amount.:reason', ['amount' => number_format($this->loan->amount_requested, 2), 'reason' => $this->reason ? ' ' . $this->tr('Reason: :reason', 'السبب: :reason', ['reason' => $this->reason]) : '']), 'icon' => 'heroicon-o-x-circle', 'color' => 'gray'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_requested, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Request Cancelled', 'تم إلغاء طلب القرض'), 'body' => "Loan {$amount} cancelled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject($this->tr('FundFlow — Loan Request Cancelled', 'FundFlow — تم إلغاء طلب القرض'))->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))->line($this->tr('Your loan application for **:amount** has been cancelled.', 'تم إلغاء طلب قرضك بمبلغ **:amount**.', ['amount' => $amount]))->when($this->reason, fn ($m) => $m->line($this->tr('**Reason:** :reason', '**السبب:** :reason', ['reason' => $this->reason])))->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }
}
