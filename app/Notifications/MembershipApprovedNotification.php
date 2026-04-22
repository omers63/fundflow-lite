<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\NotificationLog;
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
            'title'   => $this->tr('Membership Approved', 'تمت الموافقة على العضوية'),
            'body'    => $this->tr(
                'Welcome! Your membership has been approved. Member number: :number',
                'مرحبًا! تمت الموافقة على عضويتك. رقم العضوية: :number',
                ['number' => $this->memberNumber],
            ),
            'icon'    => 'heroicon-o-user-circle',
            'color'   => 'success',
            'actions' => [
                ['label' => $this->tr('Go to Portal', 'الانتقال إلى البوابة'), 'url' => url('/member')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->tr('Welcome to FundFlow — Membership Approved!', 'مرحبًا بك في FundFlow — تمت الموافقة على العضوية!'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Congratulations! Your membership application has been **approved**.', 'تهانينا! تمت **الموافقة** على طلب عضويتك.'))
            ->line($this->tr('Your member number is: **:number**', 'رقم عضويتك هو: **:number**', ['number' => $this->memberNumber]))
            ->line($this->tr('You can now log in to your member portal to:', 'يمكنك الآن تسجيل الدخول إلى بوابة الأعضاء من أجل:'))
            ->line($this->tr('• View your contribution history', '• عرض سجل المساهمات'))
            ->line($this->tr('• Apply for interest-free loans', '• التقديم على القروض الحسنة'))
            ->line($this->tr('• Download monthly statements', '• تنزيل الكشوفات الشهرية'))
            ->action($this->tr('Sign In to Member Portal', 'تسجيل الدخول إلى بوابة الأعضاء'), url('/login'))
            ->line($this->tr('Welcome to the family!', 'مرحبًا بك ضمن أسرة الصندوق!'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('Welcome to FundFlow — Membership Approved!', 'مرحبًا بك في FundFlow — تمت الموافقة على العضوية!'),
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
