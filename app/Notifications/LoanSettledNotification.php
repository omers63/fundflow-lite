<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSettledNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly Loan $loan)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ACTIVITY,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => $this->tr('Loan Fully Settled', 'تمت تسوية القرض بالكامل'), 'body' => $this->tr('Your loan of SAR :amount is now fully settled. Congratulations!', 'تمت تسوية قرضك بمبلغ SAR :amount بالكامل. تهانينا!', ['amount' => number_format((float) $this->loan->amount_approved, 2)]), 'icon' => 'heroicon-o-check-badge', 'color' => 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format((float) $this->loan->amount_approved, 2);
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = ['name' => $notifiable->name, 'amount' => $amount];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_settled', 'subject', $locale, $this->tr('FundFlow — Loan Fully Settled', 'FundFlow — تمت تسوية القرض بالكامل')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_settled', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_settled', 'body', $locale, $this->tr('🎉 Your loan of **:amount** has been fully settled. Monthly contributions resume next cycle.', '🎉 تمت تسوية قرضك بمبلغ **:amount** بالكامل. ستُستأنف المساهمات الشهرية في الدورة القادمة.', ['amount' => $amount])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_settled', 'action_label', $locale, $this->tr('View My Account', 'عرض حسابي')),
            $vars
        );

        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $subject, 'body' => "Loan {$amount} settled.", 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        return $mail->action($actionLabel, url('/member'));
    }
}
