<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanRepaymentDueNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly Loan            $loan,
        public readonly LoanInstallment $installment,
        public readonly Carbon          $deadline,
        public readonly float           $cashBalance,
    ) {}

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
            'Loan #:loan installment :number/:count (SAR :amount) is due by :deadline. Cash balance: SAR :balance.',
            'القرض رقم :loan القسط :number/:count (SAR :amount) مستحق بتاريخ :deadline. الرصيد النقدي: SAR :balance.',
            [
                'loan' => $this->loan->id,
                'number' => $this->installment->installment_number,
                'count' => $this->loan->installments_count,
                'amount' => number_format((float) $this->installment->amount, 2),
                'deadline' => $this->deadline->format('d M Y'),
                'balance' => number_format($this->cashBalance, 2),
            ],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $sufficient = $this->cashBalance >= (float) $this->installment->amount;
        return ['title' => $this->tr('Loan Repayment Due – Installment #:number', 'استحقاق سداد القرض – القسط رقم :number', ['number' => $this->installment->installment_number]), 'body' => $this->shortBody(), 'icon' => $sufficient ? 'heroicon-o-bell' : 'heroicon-o-exclamation-triangle', 'color' => $sufficient ? 'warning' : 'danger'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sufficient = $this->cashBalance >= (float) $this->installment->amount;
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'mail', 'subject' => $this->tr('Loan Repayment Due', 'استحقاق سداد القرض'), 'body' => $this->shortBody(), 'status' => 'sent', 'sent_at' => now()]);
        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — Loan Repayment Due', 'FundFlow — استحقاق سداد القرض'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Installment **#:number** of **:count** is due.', 'القسط **رقم :number** من **:count** مستحق.', ['number' => $this->installment->installment_number, 'count' => $this->loan->installments_count]))
            ->line($this->tr('**Amount:** SAR :amount', '**المبلغ:** SAR :amount', ['amount' => number_format((float) $this->installment->amount, 2)]))
            ->line($this->tr('**Deadline:** :date', '**تاريخ الاستحقاق:** :date', ['date' => $this->deadline->format('d F Y')]))
            ->line($this->tr('**Cash Balance:** SAR :amount', '**الرصيد النقدي:** SAR :amount', ['amount' => number_format($this->cashBalance, 2)]));
        if (!$sufficient) { $mail->line($this->tr('⚠️ Insufficient cash balance. Please fund your account before the deadline.', '⚠️ الرصيد النقدي غير كافٍ. يرجى تمويل حسابك قبل الموعد.')); }
        return $mail->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = "FundFlow: " . $this->shortBody() . " " . url('/member');
        NotificationLog::create(['user_id' => $notifiable->id, 'channel' => 'sms', 'subject' => null, 'body' => $body, 'status' => 'sent', 'sent_at' => now()]);
        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return $this->tr(
            "🔔 *FundFlow – Loan Repayment Due*\n\nDear :name,\n\n:body\n\n:url",
            "🔔 *FundFlow – استحقاق سداد القرض*\n\nعزيزي/عزيزتي :name،\n\n:body\n\n:url",
            ['name' => $notifiable->name, 'body' => $this->shortBody(), 'url' => url('/member')],
        );
    }
}
