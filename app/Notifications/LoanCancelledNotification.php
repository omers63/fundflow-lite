<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanCancelledNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly Loan $loan, public readonly string $reason = '')
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
        return ['title' => $this->tr('Loan Request Cancelled', 'تم إلغاء طلب القرض'), 'body' => $this->tr('Your loan application for SAR :amount has been cancelled.:reason', 'تم إلغاء طلب قرضك بمبلغ SAR :amount.:reason', ['amount' => number_format((float) $this->loan->amount_requested, 2), 'reason' => $this->reason ? ' ' . $this->tr('Reason: :reason', 'السبب: :reason', ['reason' => $this->reason]) : '']), 'icon' => 'heroicon-o-x-circle', 'color' => 'gray'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format((float) $this->loan->amount_requested, 2);
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = ['name' => $notifiable->name, 'amount' => $amount, 'reason' => $this->reason];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_cancelled', 'subject', $locale, $this->tr('FundFlow — Loan Request Cancelled', 'FundFlow — تم إلغاء طلب القرض')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_cancelled', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_cancelled', 'body', $locale, $this->tr('Your loan application for **:amount** has been cancelled.', 'تم إلغاء طلب قرضك بمبلغ **:amount**.', ['amount' => $amount])),
            $vars
        );
        $reasonLine = EmailTemplateService::render(
            EmailTemplateService::get('loan_cancelled', 'reason_line', $locale, $this->tr('**Reason:** :reason', '**السبب:** :reason', ['reason' => $this->reason])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_cancelled', 'action_label', $locale, $this->tr('View My Loans', 'عرض قروضي')),
            $vars
        );

        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $subject, 'body' => "Loan {$amount} cancelled.", 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        if ($this->reason !== '') {
            $mail->line($reasonLine);
        }
        return $mail->action($actionLabel, url('/member'));
    }
}
