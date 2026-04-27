<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDefaultWarningNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly Loan $loan,
        public readonly LoanInstallment $installment,
        public readonly int $defaultCount,
        public readonly int $graceCount,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ALERTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $remaining = $this->graceCount - $this->defaultCount + 1;
        return ['title' => $this->tr('Loan Repayment Default Warning', 'تحذير تعثر سداد القرض'), 'body' => $this->tr('You have missed :count repayment(s) on Loan #:loan. :next', 'فاتك :count سداد/سدادات في القرض رقم :loan. :next', ['count' => $this->defaultCount, 'loan' => $this->loan->id, 'next' => $remaining > 0 ? $this->tr('You have :remaining more chance(s) before your guarantor is held liable.', 'لديك :remaining فرصة/فرص إضافية قبل تحميل الكفيل المسؤولية.', ['remaining' => $remaining]) : $this->tr('Your guarantor will be notified on the next default.', 'سيتم إشعار الكفيل عند التعثر التالي.')]), 'icon' => 'heroicon-o-exclamation-triangle', 'color' => 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = [
            'name' => $notifiable->name,
            'count' => $this->defaultCount,
            'loan' => $this->loan->id,
            'number' => $this->installment->installment_number,
            'amount' => number_format((float) $this->installment->amount, 2),
            'grace_count' => $this->graceCount + 1,
        ];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_warning', 'subject', $locale, $this->tr('FundFlow — ⚠️ Loan Repayment Default Warning', 'FundFlow — ⚠️ تحذير تعثر سداد القرض')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_warning', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_default_warning', 'body', $locale, implode("\n", [
                $this->tr('You have **:count** missed repayment(s) on **Loan #:loan**.', 'لديك **:count** سداد/سدادات فائتة في **القرض رقم :loan**.', ['count' => $this->defaultCount, 'loan' => $this->loan->id]),
                $this->tr('**Overdue installment:** #:number — SAR :amount', '**القسط المتأخر:** رقم :number — SAR :amount', ['number' => $this->installment->installment_number, 'amount' => number_format((float) $this->installment->amount, 2)]),
                $this->tr('If you default on **:count or more** repayment cycles (consecutive or not), your guarantor will be held liable and the amount will be debited from their fund account.', 'إذا تعثرت في **:count دورة سداد أو أكثر** (متتالية أو غير متتالية)، فسيتم تحميل الكفيل المسؤولية وخصم المبلغ من حساب صندوقه.', ['count' => $this->graceCount + 1]),
                $this->tr('⚠️ Your membership may be cancelled if defaults continue.', '⚠️ قد يتم إلغاء عضويتك إذا استمر التعثر.'),
            ])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_default_warning', 'action_label', $locale, $this->tr('View My Loans', 'عرض قروضي')),
            $vars
        );

        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $subject, 'body' => "Default #{$this->defaultCount} on Loan #{$this->loan->id}.", 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        return $mail->action($actionLabel, url('/member'));
    }
}
