<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class LoanApprovedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly float $amount,
        public readonly int $installments,
        public readonly string $dueDate
    ) {
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
            'title' => $this->tr('Loan Approved', 'تم اعتماد القرض'),
            'body' => $this->tr(
                'Your loan of SAR :amount has been approved with :installments monthly installments.',
                'تمت الموافقة على قرضك بمبلغ SAR :amount مع :installments أقساط شهرية.',
                ['amount' => number_format($this->amount, 2), 'installments' => $this->installments],
            ),
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'actions' => [
                ['label' => $this->tr('View Loans', 'عرض القروض'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : app()->getLocale();

        $amountFormatted = 'SAR ' . number_format($this->amount, 2);
        $subject = EmailTemplateService::render(
            EmailTemplateService::get(
                'loan_approved',
                'subject',
                $locale,
                $this->tr('FundFlow — Loan Approved!', 'FundFlow — تمت الموافقة على القرض!')
            ),
            [
                'name' => $notifiable->name,
                'amount' => $amountFormatted,
                'count' => $this->installments,
                'date' => $this->dueDate,
            ]
        );

        $greeting = EmailTemplateService::render(
            EmailTemplateService::get(
                'loan_approved',
                'greeting',
                $locale,
                $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])
            ),
            [
                'name' => $notifiable->name,
                'amount' => $amountFormatted,
                'count' => $this->installments,
                'date' => $this->dueDate,
            ]
        );

        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get(
                'loan_approved',
                'body',
                $locale,
                implode("\n", [
                    $this->tr('Your loan application for **:amount** has been approved.', 'تمت الموافقة على طلب قرضك بمبلغ **:amount**.', ['amount' => $amountFormatted]),
                    $this->tr('Repayment Details:', 'تفاصيل السداد:'),
                    $this->tr('• Amount: :amount', '• المبلغ: :amount', ['amount' => $amountFormatted]),
                    $this->tr('• Installments: :count monthly payments', '• الأقساط: :count دفعات شهرية', ['count' => $this->installments]),
                    $this->tr('• Final due date: :date', '• تاريخ الاستحقاق النهائي: :date', ['date' => $this->dueDate]),
                ])
            ),
            [
                'name' => $notifiable->name,
                'amount' => $amountFormatted,
                'count' => $this->installments,
                'date' => $this->dueDate,
            ]
        );

        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get(
                'loan_approved',
                'action_label',
                $locale,
                $this->tr('View Loan Details', 'عرض تفاصيل القرض')
            ),
            [
                'name' => $notifiable->name,
                'amount' => $amountFormatted,
                'count' => $this->installments,
                'date' => $this->dueDate,
            ]
        );

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);

        foreach ($bodyLines as $line) {
            $mail->line($line);
        }

        $mail->action($actionLabel, url('/member'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body' => "Loan approved for {$notifiable->name}. Amount: {$amountFormatted}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr(
            'FundFlow: Loan approved for SAR :amount (:count installments). Login to view details: :url',
            'FundFlow: تمت الموافقة على قرض بمبلغ SAR :amount (:count أقساط). سجّل الدخول لعرض التفاصيل: :url',
            ['amount' => number_format($this->amount, 2), 'count' => $this->installments, 'url' => url('/member')],
        );

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
        return $this->tr(
            "✅ *FundFlow Loan Approved*\n\nDear :name,\n\nYour loan of *SAR :amount* has been approved.\n\n*Installments:* :count monthly payments\n*Due Date:* :date\n\nView details: :url",
            "✅ *تمت الموافقة على قرض FundFlow*\n\nعزيزي/عزيزتي :name،\n\nتمت الموافقة على قرضك بمبلغ *SAR :amount*.\n\n*الأقساط:* :count دفعات شهرية\n*تاريخ الاستحقاق:* :date\n\nعرض التفاصيل: :url",
            ['name' => $notifiable->name, 'amount' => number_format($this->amount, 2), 'count' => $this->installments, 'date' => $this->dueDate, 'url' => url('/member')],
        );
    }
}
