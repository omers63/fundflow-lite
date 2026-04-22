<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanEarlySettledNotification extends Notification
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
        return ['title' => $this->tr('Loan Early Settlement Complete', 'تمت التسوية المبكرة للقرض'), 'body' => $this->tr('Your loan of SAR :amount has been fully settled. Congratulations!', 'تمت تسوية قرضك بمبلغ SAR :amount بالكامل. تهانينا!', ['amount' => number_format($this->loan->amount_approved, 2)]), 'icon' => 'heroicon-o-check-badge', 'color' => 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Early Settlement', 'التسوية المبكرة للقرض'), 'body' => "Loan {$amount} early settled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject($this->tr('FundFlow — Loan Early Settlement Complete', 'FundFlow — تمت التسوية المبكرة للقرض'))->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))->line($this->tr('🎉 Congratulations! Your loan of **:amount** has been fully settled.', '🎉 تهانينا! تمت تسوية قرضك بمبلغ **:amount** بالكامل.', ['amount' => $amount]))->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }
}
