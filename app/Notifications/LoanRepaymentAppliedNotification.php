<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanRepaymentAppliedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly Loan $loan,
        public readonly LoanInstallment $installment,
        public readonly float $cashBalance,
        public readonly bool $isLate = false,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_REPAYMENT,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    private function shortBody(): string
    {
        return $this->tr(
            'Installment :number/:count (SAR :amount) applied:late_suffix. Remaining: SAR :remaining. Cash balance: SAR :balance.',
            'تم تطبيق القسط :number/:count (SAR :amount):late_suffix. المتبقي: SAR :remaining. الرصيد النقدي: SAR :balance.',
            [
                'number' => $this->installment->installment_number,
                'count' => $this->loan->installments_count,
                'amount' => number_format((float) $this->installment->amount, 2),
                'late_suffix' => $this->isLate ? $this->tr(' (late)', ' (متأخر)') : '',
                'remaining' => number_format($this->loan->remaining_amount, 2),
                'balance' => number_format($this->cashBalance, 2),
            ],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return ['title' => $this->tr('Loan Repayment Applied – Installment #:number', 'تم تطبيق سداد القرض – القسط رقم :number', ['number' => $this->installment->installment_number]), 'body' => $this->shortBody(), 'icon' => $this->isLate ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle', 'color' => $this->isLate ? 'warning' : 'success'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = [
            'name' => $notifiable->name,
            'number' => $this->installment->installment_number,
            'count' => $this->loan->installments_count,
            'amount' => number_format((float) $this->installment->amount, 2),
            'remaining' => number_format($this->loan->remaining_amount, 2),
            'balance' => number_format($this->cashBalance, 2),
        ];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_repayment_applied', 'subject', $locale, $this->tr('FundFlow — Loan Repayment Account Statement', 'FundFlow — كشف حساب سداد القرض')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_repayment_applied', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_repayment_applied', 'body', $locale, implode("\n", [
                $this->tr('**Installment #:number** of **:count** has been applied.', '**تم تطبيق القسط رقم :number** من **:count**.', ['number' => $this->installment->installment_number, 'count' => $this->loan->installments_count]),
                $this->tr('**Amount:** SAR :amount', '**المبلغ:** SAR :amount', ['amount' => number_format((float) $this->installment->amount, 2)]),
                $this->tr('**Remaining Loan Balance:** SAR :amount', '**الرصيد المتبقي للقرض:** SAR :amount', ['amount' => number_format($this->loan->remaining_amount, 2)]),
                $this->tr('**Remaining Cash Balance:** SAR :amount', '**الرصيد النقدي المتبقي:** SAR :amount', ['amount' => number_format($this->cashBalance, 2)]),
            ])),
            $vars
        );
        $lateLine = EmailTemplateService::render(
            EmailTemplateService::get('loan_repayment_applied', 'late_line', $locale, $this->tr('⚠️ This repayment was recorded as late.', '⚠️ تم تسجيل هذا السداد كمتأخر.')),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_repayment_applied', 'action_label', $locale, $this->tr('View My Loans', 'عرض قروضي')),
            $vars
        );
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Repayment Applied', 'تم تطبيق سداد القرض'), 'body' => $this->shortBody(), 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        if ($this->isLate) {
            $mail->line($lateLine);
        }
        return $mail->action($actionLabel, url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: " . $this->shortBody() . " " . url('/member');
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        $icon = $this->isLate ? '⚠️' : '✅';
        return $this->tr(
            "{$icon} *FundFlow – Loan Repayment Applied*\n\nDear :name,\n\n:body\n\n:url",
            "{$icon} *FundFlow – تم تطبيق سداد القرض*\n\nعزيزي/عزيزتي :name،\n\n:body\n\n:url",
            ['name' => $notifiable->name, 'body' => $this->shortBody(), 'url' => url('/member')],
        );
    }
}
