<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\LoanDisbursement;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanPartialDisbursementNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly LoanDisbursement $disbursement,
        public readonly float $totalDisbursed,
        public readonly float $amountApproved,
    ) {}

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
            'title' => $this->tr('Loan Partially Disbursed', 'تم صرف القرض جزئيًا'),
            'body'  => $this->tr('SAR :portion of your loan has been disbursed. Total disbursed so far: SAR :total of SAR :approved.', 'تم صرف SAR :portion من قرضك. إجمالي ما تم صرفه حتى الآن: SAR :total من SAR :approved.', ['portion' => number_format($this->disbursement->amount, 2), 'total' => number_format($this->totalDisbursed, 2), 'approved' => number_format($this->amountApproved, 2)]),
            'icon'  => 'heroicon-o-banknotes',
            'color' => 'info',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $portion   = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total     = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved  = 'SAR ' . number_format($this->amountApproved, 2);
        $remaining = 'SAR ' . number_format(max(0, $this->amountApproved - $this->totalDisbursed), 2);

        NotificationLog::create([
            'user_id'  => $notifiable->id,
            'channel'  => 'mail',
            'subject'  => $this->tr('Loan Partially Disbursed', 'تم صرف القرض جزئيًا'),
            'body'     => "Partial disbursement of {$portion}.",
            'status'   => 'sent',
            'sent_at'  => now(),
        ]);

        return (new MailMessage)
            ->subject($this->tr('FundFlow — Loan Partially Disbursed', 'FundFlow — تم صرف القرض جزئيًا'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('A partial disbursement of **:amount** has been credited against your approved loan.', 'تم قيد دفعة جزئية بمبلغ **:amount** على قرضك المعتمد.', ['amount' => $portion]))
            ->line($this->tr('**Total disbursed so far:** :total of :approved', '**إجمالي المصروف حتى الآن:** :total من :approved', ['total' => $total, 'approved' => $approved]))
            ->line($this->tr('**Remaining to disburse:** :remaining', '**المتبقي للصرف:** :remaining', ['remaining' => $remaining]))
            ->line($this->tr('Repayment will begin only after the loan is fully disbursed.', 'سيبدأ السداد فقط بعد صرف القرض بالكامل.'))
            ->action($this->tr('View My Loans', 'عرض قروضي'), url('/member'));
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $portion  = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total    = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved = 'SAR ' . number_format($this->amountApproved, 2);

        $body = $this->tr('FundFlow: Partial loan disbursement of :portion. Total disbursed: :total of :approved. Repayment starts after full disbursement. :url', 'FundFlow: تم صرف جزء من القرض بقيمة :portion. إجمالي المصروف: :total من :approved. يبدأ السداد بعد صرف القرض بالكامل. :url', ['portion' => $portion, 'total' => $total, 'approved' => $approved, 'url' => url('/member')]);

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'sms',
            'subject' => null,
            'body'    => $body,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        $portion   = 'SAR ' . number_format($this->disbursement->amount, 2);
        $total     = 'SAR ' . number_format($this->totalDisbursed, 2);
        $approved  = 'SAR ' . number_format($this->amountApproved, 2);
        $remaining = 'SAR ' . number_format(max(0, $this->amountApproved - $this->totalDisbursed), 2);

        return $this->tr("💰 *FundFlow – Partial Loan Disbursement*\n\nDear :name,\n\n*This portion:* :portion\n*Total disbursed:* :total of :approved\n*Remaining:* :remaining\n\nRepayment begins after the loan is fully disbursed.\n\n:url", "💰 *FundFlow – صرف جزئي للقرض*\n\nعزيزي/عزيزتي :name،\n\n*الدفعة الحالية:* :portion\n*إجمالي المصروف:* :total من :approved\n*المتبقي:* :remaining\n\nيبدأ السداد بعد صرف القرض بالكامل.\n\n:url", ['name' => $notifiable->name, 'portion' => $portion, 'total' => $total, 'approved' => $approved, 'remaining' => $remaining, 'url' => url('/member')]);
    }
}
