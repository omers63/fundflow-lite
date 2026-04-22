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

class MemberDelinquencySuspensionNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly int $trailingConsecutive,
        public readonly int $rollingTotal,
    ) {}

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolve(
            $notifiable,
            \App\Services\NotificationPreferenceService::ACCOUNT_ALERTS,
            ['in_app', 'email', 'sms', 'whatsapp'],
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->tr('Membership suspended — delinquency', 'تم تعليق العضوية — تعثر'),
            'body' => $this->tr('Your membership has been suspended due to repeated missed contributions or loan repayments. Trailing consecutive misses: :trailing. Rolling total (configured window): :rolling. Repayment obligations may transfer to your guarantor. Contact the fund office.', 'تم تعليق عضويتك بسبب تكرار عدم الالتزام بالمساهمات أو سداد القروض. عدد حالات التعثر المتتالية: :trailing. الإجمالي ضمن النافذة المحددة: :rolling. قد تنتقل التزامات السداد إلى الكفيل. يرجى التواصل مع إدارة الصندوق.', ['trailing' => $this->trailingConsecutive, 'rolling' => $this->rollingTotal]),
            'icon' => 'heroicon-o-no-symbol',
            'color' => 'danger',
            'actions' => [
                ['label' => $this->tr('Contact office', 'التواصل مع الإدارة'), 'url' => url('/')],
            ],
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — Membership suspended (delinquency)', 'FundFlow — تم تعليق العضوية (تعثر)'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Your membership has been **suspended** because contribution or loan repayment obligations were not met under the fund’s delinquency policy.', 'تم **تعليق** عضويتك لعدم الالتزام بمتطلبات المساهمة أو سداد القرض وفق سياسة التعثر.'))
            ->line($this->tr('Consecutive missed cycles (trailing): **:count**.', 'الدورات المتتالية غير المسددة: **:count**.', ['count' => $this->trailingConsecutive]))
            ->line($this->tr('Total misses in the rolling window: **:count**.', 'إجمالي حالات التعثر ضمن النافذة المتحركة: **:count**.', ['count' => $this->rollingTotal]))
            ->line($this->tr('Outstanding loan repayments may be collected from your guarantor while you remain suspended. Please contact the fund office to regularize your account.', 'قد يتم تحصيل أقساط القرض المستحقة من كفيلك طوال فترة التعليق. يرجى التواصل مع إدارة الصندوق لتسوية الحساب.'))
            ->line($this->tr('You will not be able to sign in to the member portal until your status is restored by the administration.', 'لن تتمكن من تسجيل الدخول إلى بوابة الأعضاء حتى تتم إعادة حالتك بواسطة الإدارة.'));

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('FundFlow — Membership suspended (delinquency)', 'FundFlow — تم تعليق العضوية (تعثر)'),
            'body' => "Delinquency suspension for {$notifiable->name}.",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $body = $this->tr('FundFlow: Your membership is suspended due to missed contributions/repayments (consecutive :trailing, rolling :rolling). Contact the fund office.', 'FundFlow: تم تعليق عضويتك بسبب عدم سداد المساهمات/الأقساط (متتالية :trailing، إجمالي :rolling). يرجى التواصل مع إدارة الصندوق.', ['trailing' => $this->trailingConsecutive, 'rolling' => $this->rollingTotal]);

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
        return $this->tr("⚠️ *FundFlow — Membership suspended*\n\nDear :name,\n\nYour membership has been suspended due to repeated missed contributions or loan repayments.\n\nTrailing consecutive: *:trailing*\nRolling total (window): *:rolling*\n\nRepayment obligations may transfer to your guarantor. Contact the fund office.", "⚠️ *FundFlow — تم تعليق العضوية*\n\nعزيزي/عزيزتي :name،\n\nتم تعليق عضويتك بسبب تكرار عدم سداد المساهمات أو أقساط القرض.\n\nالمتتالية: *:trailing*\nالإجمالي (النافذة): *:rolling*\n\nقد تنتقل التزامات السداد إلى الكفيل. يرجى التواصل مع إدارة الصندوق.", ['name' => $notifiable->name, 'trailing' => $this->trailingConsecutive, 'rolling' => $this->rollingTotal]);
    }
}
