<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class ContributionDueNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly int $month,
        public readonly int $year,
        public readonly float $amount,
        public readonly Carbon $deadline,
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
            date('F', mktime(0, 0, 0, $this->month, 1)) . ' ' . $this->year,
            Carbon::create($this->year, $this->month, 1)->locale('ar')->translatedFormat('F Y'),
        );
    }

    private function shortBody(): string
    {
        return $this->tr(
            'Your contribution for :period (SAR :amount) is due by :deadline. Current cash balance: SAR :balance.',
            'استحقاق مساهمتك لفترة :period (SAR :amount) بتاريخ :deadline. رصيدك النقدي الحالي: SAR :balance.',
            [
                'period' => $this->periodLabel(),
                'amount' => number_format($this->amount, 2),
                'deadline' => $this->deadline->format('d M Y'),
                'balance' => number_format($this->cashBalance, 2),
            ],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        $sufficient = $this->cashBalance >= $this->amount;

        return [
            'title' => $this->tr('Contribution Due – :period', 'استحقاق المساهمة – :period', ['period' => $this->periodLabel()]),
            'body' => $this->shortBody(),
            'icon' => $sufficient ? 'heroicon-o-bell' : 'heroicon-o-exclamation-triangle',
            'color' => $sufficient ? 'warning' : 'danger',
            'actions' => [
                ['label' => $this->tr('View Account', 'عرض الحساب'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $sufficient = $this->cashBalance >= $this->amount;
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();
        $vars = [
            'name' => $notifiable->name,
            'period' => $this->periodLabel(),
            'amount' => number_format($this->amount, 2),
            'date' => $this->deadline->format('d F Y'),
            'balance' => number_format($this->cashBalance, 2),
        ];

        $subject = EmailTemplateService::render(
            EmailTemplateService::get('contribution_due', 'subject', $locale, $this->tr('FundFlow — :period Contribution Due', 'FundFlow — استحقاق مساهمة :period', ['period' => $this->periodLabel()])),
            $vars
        );
        $greeting = EmailTemplateService::render(
            EmailTemplateService::get('contribution_due', 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])),
            $vars
        );
        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get('contribution_due', 'body', $locale, implode("\n", [
                $this->tr('Your monthly contribution for **:period** is due.', 'مساهمتك الشهرية لفترة **:period** مستحقة.', ['period' => $this->periodLabel()]),
                $this->tr('**Amount due:** SAR :amount', '**المبلغ المستحق:** SAR :amount', ['amount' => number_format($this->amount, 2)]),
                $this->tr('**Deadline:** :date', '**تاريخ الاستحقاق:** :date', ['date' => $this->deadline->format('d F Y')]),
                $this->tr('**Your current cash balance:** SAR :amount', '**رصيدك النقدي الحالي:** SAR :amount', ['amount' => number_format($this->cashBalance, 2)]),
            ])),
            $vars
        );
        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get('contribution_due', 'action_label', $locale, $this->tr('View My Account', 'عرض حسابي')),
            $vars
        );

        $mail = (new MailMessage)->subject($subject)->greeting($greeting);
        foreach ($bodyLines as $line) {
            $mail->line($line);
        }

        if (!$sufficient) {
            $shortfall = $this->amount - $this->cashBalance;
            $mail->line($this->tr(
                '⚠️ Your cash account is short by SAR :amount. Please fund your account before the deadline to avoid a late contribution.',
                '⚠️ يوجد عجز في حسابك النقدي بمقدار SAR :amount. يرجى تمويل حسابك قبل الموعد لتجنب تأخير المساهمة.',
                ['amount' => number_format($shortfall, 2)],
            ));
        }

        $mail->action($actionLabel, url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
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
        $sufficient = $this->cashBalance >= $this->amount;
        $icon = $sufficient ? '🔔' : '⚠️';

        return $this->tr(
            "{$icon} *FundFlow – Contribution Due*\n\nDear :name,\n\nYour contribution for *:period* is due.\n\n*Amount:* SAR :amount\n*Deadline:* :date\n*Cash Balance:* SAR :balance\n\n:url",
            "{$icon} *FundFlow – استحقاق المساهمة*\n\nعزيزي/عزيزتي :name،\n\nمساهمتك لفترة *:period* مستحقة.\n\n*المبلغ:* SAR :amount\n*تاريخ الاستحقاق:* :date\n*الرصيد النقدي:* SAR :balance\n\n:url",
            [
                'name' => $notifiable->name,
                'period' => $this->periodLabel(),
                'amount' => number_format($this->amount, 2),
                'date' => $this->deadline->format('d F Y'),
                'balance' => number_format($this->cashBalance, 2),
                'url' => url('/member'),
            ],
        );
    }
}
