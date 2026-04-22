<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\NotificationLog;
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

    public function __construct(public readonly Loan $loan) {}

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
            'body'  => $this->tr(
                'Your loan of SAR :amount has been disbursed. First repayment: :repayment',
                'تم صرف قرضك بمبلغ SAR :amount. أول سداد: :repayment',
                ['amount' => number_format($this->loan->amount_approved, 2), 'repayment' => $this->firstRepaymentLabel()],
            ),
            'icon'  => 'heroicon-o-banknotes',
            'color' => 'success',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = 'SAR ' . number_format($this->loan->amount_approved, 2);
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Disbursed', 'تم صرف القرض'), 'body' => "Loan {$amount} disbursed.", 'status' => 'sent', 'sent_at' => now()]);
        return (new MailMessage)
            ->subject($this->tr('FundFlow — Loan Disbursed', 'FundFlow — تم صرف القرض'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Your loan of **:amount** has been disbursed.', 'تم صرف قرضك بمبلغ **:amount**.', ['amount' => $amount]))
            ->line($this->tr('**Installments:** :count monthly payments', '**الأقساط:** :count دفعات شهرية', ['count' => $this->loan->installments_count]))
            ->line($this->tr('**Minimum monthly installment:** SAR :amount', '**الحد الأدنى للقسط الشهري:** SAR :amount', ['amount' => number_format($this->loan->loanTier?->min_monthly_installment ?? 0)]))
            ->line($this->tr('**First repayment cycle:** :cycle', '**دورة السداد الأولى:** :cycle', ['cycle' => $this->firstRepaymentLabel()]))
            ->line($this->tr('**Exempted contribution cycle:** :cycle', '**دورة المساهمة المعفاة:** :cycle', ['cycle' => $this->exemptedLabel()]))
            ->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr(
            'FundFlow: Loan of SAR :amount disbursed. First repayment: :repayment. :url',
            'FundFlow: تم صرف قرض بمبلغ SAR :amount. أول سداد: :repayment. :url',
            ['amount' => number_format($this->loan->amount_approved, 2), 'repayment' => $this->firstRepaymentLabel(), 'url' => url('/member')],
        );
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return $this->tr(
            "✅ *FundFlow – Loan Disbursed*\n\nDear :name,\n\n*Amount:* SAR :amount\n*Installments:* :count months\n*First Repayment:* :repayment\n*Exempted Cycle:* :cycle\n\n:url",
            "✅ *FundFlow – تم صرف القرض*\n\nعزيزي/عزيزتي :name،\n\n*المبلغ:* SAR :amount\n*الأقساط:* :count شهر\n*أول سداد:* :repayment\n*الدورة المعفاة:* :cycle\n\n:url",
            ['name' => $notifiable->name, 'amount' => number_format($this->loan->amount_approved, 2), 'count' => $this->loan->installments_count, 'repayment' => $this->firstRepaymentLabel(), 'cycle' => $this->exemptedLabel(), 'url' => url('/member')],
        );
    }

    private function firstRepaymentLabel(): string
    {
        if (! $this->loan->first_repayment_month) return $this->tr('—', '—');
        return $this->tr(
            date('F', mktime(0, 0, 0, $this->loan->first_repayment_month, 1)) . ' ' . $this->loan->first_repayment_year,
            Carbon::create($this->loan->first_repayment_year, $this->loan->first_repayment_month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }

    private function exemptedLabel(): string
    {
        if (! $this->loan->exempted_month) return $this->tr('—', '—');
        return $this->tr(
            date('F', mktime(0, 0, 0, $this->loan->exempted_month, 1)) . ' ' . $this->loan->exempted_year,
            Carbon::create($this->loan->exempted_year, $this->loan->exempted_month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }
}
