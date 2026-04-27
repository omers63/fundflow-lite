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

class MembershipApprovedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly string $memberNumber)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::MEMBERSHIP,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->tr('Membership Approved', 'تمت الموافقة على العضوية'),
            'body' => $this->tr(
                'Welcome! Your membership has been approved. Member number: :number',
                'مرحبًا! تمت الموافقة على عضويتك. رقم العضوية: :number',
                ['number' => $this->memberNumber],
            ),
            'icon' => 'heroicon-o-user-circle',
            'color' => 'success',
            'actions' => [
                ['label' => $this->tr('Go to Portal', 'الانتقال إلى البوابة'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : app()->getLocale();

        $subject = EmailTemplateService::render(
            EmailTemplateService::get(
                'membership_approved',
                'subject',
                $locale,
                $this->tr('Welcome to FundFlow — Membership Approved!', 'مرحبًا بك في FundFlow — تمت الموافقة على العضوية!')
            ),
            ['number' => $this->memberNumber, 'name' => $notifiable->name]
        );

        $greeting = EmailTemplateService::render(
            EmailTemplateService::get(
                'membership_approved',
                'greeting',
                $locale,
                $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])
            ),
            ['number' => $this->memberNumber, 'name' => $notifiable->name]
        );

        $bodyLines = EmailTemplateService::renderLines(
            EmailTemplateService::get(
                'membership_approved',
                'body',
                $locale,
                implode("\n", [
                    $this->tr('Congratulations! Your membership application has been **approved**.', 'تهانينا! تمت **الموافقة** على طلب عضويتك.'),
                    $this->tr('Your member number is: **:number**', 'رقم عضويتك هو: **:number**', ['number' => $this->memberNumber]),
                    $this->tr('You can now log in to your member portal to:', 'يمكنك الآن تسجيل الدخول إلى بوابة الأعضاء من أجل:'),
                    $this->tr('• View your contribution history', '• عرض سجل المساهمات'),
                    $this->tr('• Apply for interest-free loans', '• التقديم على القروض الحسنة'),
                    $this->tr('• Download monthly statements', '• تنزيل الكشوفات الشهرية'),
                ])
            ),
            ['number' => $this->memberNumber, 'name' => $notifiable->name]
        );

        $actionLabel = EmailTemplateService::render(
            EmailTemplateService::get(
                'membership_approved',
                'action_label',
                $locale,
                $this->tr('Sign In to Member Portal', 'تسجيل الدخول إلى بوابة الأعضاء')
            ),
            ['number' => $this->memberNumber, 'name' => $notifiable->name]
        );

        $closing = EmailTemplateService::render(
            EmailTemplateService::get(
                'membership_approved',
                'closing',
                $locale,
                $this->tr('Welcome to the family!', 'مرحبًا بك ضمن أسرة الصندوق!')
            ),
            ['number' => $this->memberNumber, 'name' => $notifiable->name]
        );

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting);

        foreach ($bodyLines as $line) {
            $message->line($line);
        }

        $message
            ->action($actionLabel, url('/login'))
            ->line($closing);

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body' => "Membership approved for {$notifiable->name}. Member number: {$this->memberNumber}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $message;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr(
            'FundFlow: Congratulations :name! Your membership has been approved. Member No: :number. Login at: :url',
            'FundFlow: تهانينا :name! تمت الموافقة على عضويتك. رقم العضوية: :number. سجّل الدخول عبر: :url',
            ['name' => $notifiable->name, 'number' => $this->memberNumber, 'url' => url('/login')],
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
            "🎉 *FundFlow Membership Approved*\n\nDear :name,\n\nYour membership application has been approved!\n\n*Member Number:* :number\n\nLogin at: :url\n\nWelcome to the family!",
            "🎉 *تمت الموافقة على عضوية FundFlow*\n\nعزيزي/عزيزتي :name،\n\nتمت الموافقة على طلب العضوية!\n\n*رقم العضوية:* :number\n\nتسجيل الدخول: :url\n\nمرحبًا بك ضمن أسرة الصندوق!",
            ['name' => $notifiable->name, 'number' => $this->memberNumber, 'url' => url('/login')],
        );
    }
}
