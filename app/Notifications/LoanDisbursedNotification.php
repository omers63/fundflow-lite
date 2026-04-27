<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanDisbursedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly Loan $loan)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::LOAN_ACTIVITY,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->tr('Loan Disbursed', 'تم صرف القرض'),
            'body' => $this->tr(
                'Your loan of SAR :amount has been disbursed. First repayment: :repayment',
                'تم صرف قرضك بمبلغ SAR :amount. أول سداد: :repayment',
                ['amount' => number_format((float) $this->loan->amount_approved, 2), 'repayment' => $this->firstRepaymentLabel()],
            ),
            'icon' => 'heroicon-o-banknotes',
            'color' => 'success',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format((float) $this->loan->amount_approved, 2);
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = [
            'name' => $notifiable->name,
            'amount' => $amount,
            'count' => $this->loan->installments_count,
            'min_installment' => number_format((float) ($this->loan->loanTier?->min_monthly_installment ?? 0), 2),
            'first_cycle' => $this->firstRepaymentLabel(),
            'exempted_cycle' => $this->exemptedLabel(),
        ];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get('loan_disbursed', 'subject', $locale, $this->tr('FundFlow — Loan Disbursed', 'FundFlow — تم صرف القرض')),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('loan_disbursed', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('loan_disbursed', 'body', $locale, implode("\n", [
                $this->tr('Your loan of **:amount** has been disbursed.', 'تم صرف قرضك بمبلغ **:amount**.', ['amount' => $amount]),
                $this->tr('**Installments:** :count monthly payments', '**الأقساط:** :count دفعات شهرية', ['count' => $this->loan->installments_count]),
                $this->tr('**Minimum monthly installment:** SAR :amount', '**الحد الأدنى للقسط الشهري:** SAR :amount', ['amount' => number_format((float) ($this->loan->loanTier?->min_monthly_installment ?? 0), 2)]),
                $this->tr('**First repayment cycle:** :cycle', '**دورة السداد الأولى:** :cycle', ['cycle' => $this->firstRepaymentLabel()]),
                $this->tr('**Exempted contribution cycle:** :cycle', '**دورة المساهمة المعفاة:** :cycle', ['cycle' => $this->exemptedLabel()]),
            ])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('loan_disbursed', 'action_label', $locale, $this->tr('View My Loans', 'عرض قروضي')),
            $vars
        );

        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $subject, 'body' => "Loan {$amount} disbursed.", 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }
        return $mail->action($actionLabel, url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr(
            'FundFlow: Loan of SAR :amount disbursed. First repayment: :repayment. :url',
            'FundFlow: تم صرف قرض بمبلغ SAR :amount. أول سداد: :repayment. :url',
            ['amount' => number_format((float) $this->loan->amount_approved, 2), 'repayment' => $this->firstRepaymentLabel(), 'url' => url('/member')],
        );
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return $this->tr(
            "✅ *FundFlow – Loan Disbursed*\n\nDear :name,\n\n*Amount:* SAR :amount\n*Installments:* :count months\n*First Repayment:* :repayment\n*Exempted Cycle:* :cycle\n\n:url",
            "✅ *FundFlow – تم صرف القرض*\n\nعزيزي/عزيزتي :name،\n\n*المبلغ:* SAR :amount\n*الأقساط:* :count شهر\n*أول سداد:* :repayment\n*الدورة المعفاة:* :cycle\n\n:url",
            ['name' => $notifiable->name, 'amount' => number_format((float) $this->loan->amount_approved, 2), 'count' => $this->loan->installments_count, 'repayment' => $this->firstRepaymentLabel(), 'cycle' => $this->exemptedLabel(), 'url' => url('/member')],
        );
    }

    private function firstRepaymentLabel(): string
    {
        if (!$this->loan->first_repayment_month)
            return $this->tr('—', '—');
        return $this->tr(
            date('F', mktime(0, 0, 0, $this->loan->first_repayment_month, 1)) . ' ' . $this->loan->first_repayment_year,
            Carbon::create($this->loan->first_repayment_year, $this->loan->first_repayment_month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }

    private function exemptedLabel(): string
    {
        if (!$this->loan->exempted_month)
            return $this->tr('—', '—');
        return $this->tr(
            date('F', mktime(0, 0, 0, $this->loan->exempted_month, 1)) . ' ' . $this->loan->exempted_year,
            Carbon::create($this->loan->exempted_year, $this->loan->exempted_month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }
}
