<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\Contribution;
use App\Services\ContributionCycleService;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class ContributionAppliedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly Contribution $contribution,
        public readonly float $cashBalance,
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::CONTRIBUTIONS,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    private function periodLabel(): string
    {
        return $this->tr(
            date('F', mktime(0, 0, 0, $this->contribution->month, 1)) . ' ' . $this->contribution->year,
            Carbon::create($this->contribution->year, $this->contribution->month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }

    private function shortBody(): string
    {
        return $this->tr(
            'Your contribution of SAR :amount for :period has been applied:late_suffix. Remaining cash balance: SAR :balance.',
            'تم تطبيق مساهمتك بمبلغ SAR :amount لفترة :period:late_suffix. الرصيد النقدي المتبقي: SAR :balance.',
            [
                'amount' => number_format((float) $this->contribution->amount, 2),
                'period' => $this->periodLabel(),
                'late_suffix' => $this->contribution->is_late
                    ? $this->tr(' (late)', ' (متأخرة)')
                    : '',
                'balance' => number_format($this->cashBalance, 2),
            ],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->tr('Contribution Applied – :period', 'تم تطبيق المساهمة – :period', ['period' => $this->periodLabel()]),
            'body' => $this->shortBody(),
            'icon' => $this->contribution->is_late ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle',
            'color' => $this->contribution->is_late ? 'warning' : 'success',
            'actions' => [
                ['label' => $this->tr('View My Contributions', 'عرض مساهماتي'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $amount = number_format((float) $this->contribution->amount, 2);

        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — Account Statement: :period Contribution', 'FundFlow — كشف الحساب: مساهمة :period', ['period' => $this->periodLabel()]))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Your monthly contribution for **:period** has been successfully applied.', 'تم تطبيق مساهمتك الشهرية لفترة **:period** بنجاح.', ['period' => $this->periodLabel()]))
            ->line("---")
            ->line($this->tr('**Account Statement**', '**كشف الحساب**'))
            ->line($this->tr('• Period: :period', '• الفترة: :period', ['period' => $this->periodLabel()]))
            ->line($this->tr('• Contribution Amount: SAR :amount', '• مبلغ المساهمة: SAR :amount', ['amount' => $amount]))
            ->line($this->tr('• Applied On: :date', '• تاريخ التطبيق: :date', ['date' => now()->format('d F Y H:i')]))
            ->line($this->tr('• Remaining Cash Balance: SAR :amount', '• الرصيد النقدي المتبقي: SAR :amount', ['amount' => number_format($this->cashBalance, 2)]));

        if ($this->contribution->is_late) {
            $deadline = app(ContributionCycleService::class)->cycleDueEndAt(
                (int) $this->contribution->month,
                (int) $this->contribution->year,
            )->format('j F Y');
            $mail->line($this->tr(
                '⚠️ This contribution was recorded as **late** (applied after the deadline for this period: :deadline).',
                '⚠️ تم تسجيل هذه المساهمة على أنها **متأخرة** (تم تطبيقها بعد موعد الاستحقاق لهذه الفترة: :deadline).',
                ['deadline' => $deadline],
            ));
        }

        $mail->action($this->tr('View My Account', 'عرض حسابي'), url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('FundFlow — Account Statement: :period Contribution', 'FundFlow — كشف الحساب: مساهمة :period', ['period' => $this->periodLabel()]),
            'body' => $this->shortBody(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = 'FundFlow: ' . $this->shortBody() . ' ' . url('/member');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'sms',
            'subject' => null,
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return (new TwilioSmsMessage())->content($body);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        $amount = number_format((float) $this->contribution->amount, 2);
        $icon = $this->contribution->is_late ? '⚠️' : '✅';

        return $this->tr(
            "{$icon} *FundFlow – Contribution Applied*\n\nDear :name,\n\n*Period:* :period\n*Amount:* SAR :amount\n*Applied On:* :applied\n*Remaining Cash Balance:* SAR :balance:late\n\n:url",
            "{$icon} *FundFlow – تم تطبيق المساهمة*\n\nعزيزي/عزيزتي :name،\n\n*الفترة:* :period\n*المبلغ:* SAR :amount\n*تاريخ التطبيق:* :applied\n*الرصيد النقدي المتبقي:* SAR :balance:late\n\n:url",
            [
                'name' => $notifiable->name,
                'period' => $this->periodLabel(),
                'amount' => $amount,
                'applied' => now()->format('d F Y H:i'),
                'balance' => number_format($this->cashBalance, 2),
                'late' => $this->contribution->is_late ? $this->tr("\n\n⚠️ This contribution was recorded as late.", "\n\n⚠️ تم تسجيل هذه المساهمة كمتأخرة.") : '',
                'url' => url('/member'),
            ],
        );
    }
}
