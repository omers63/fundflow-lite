<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSettledNotification extends Notification
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
        return ['title' => $this->tr('Loan Fully Settled', 'تمت تسوية القرض بالكامل'), 'body' => $this->tr('Your loan of SAR :amount is now fully settled. Congratulations!', 'تمت تسوية قرضك بمبلغ SAR :amount بالكامل. تهانينا!', ['amount' => number_format($this->loan->amount_approved, 2)]), 'icon' => 'heroicon-o-check-badge', 'color' => 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Settled', 'تمت تسوية القرض'), 'body' => "Loan {$amount} settled.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)->subject($this->tr('FundFlow — Loan Fully Settled', 'FundFlow — تمت تسوية القرض بالكامل'))->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))->line($this->tr('🎉 Your loan of **:amount** has been fully settled. Monthly contributions resume next cycle.', '🎉 تمت تسوية قرضك بمبلغ **:amount** بالكامل. ستُستأنف المساهمات الشهرية في الدورة القادمة.', ['amount' => $amount]))->action($this->tr('View My Account', 'عرض حسابي'), url('/member'));
    }
}
