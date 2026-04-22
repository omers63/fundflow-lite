<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipRejectedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(public readonly ?string $reason = null)
    {
    }

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::MEMBERSHIP,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'   => $this->tr('Membership Application Update', 'تحديث طلب العضوية'),
            'body'    => $this->reason
                ? $this->tr('Your membership application was not approved. Reason: :reason', 'لم تتم الموافقة على طلب العضوية. السبب: :reason', ['reason' => $this->reason])
                : $this->tr('Your membership application could not be approved at this time.', 'تعذر الموافقة على طلب العضوية في الوقت الحالي.'),
            'icon'    => 'heroicon-o-x-circle',
            'color'   => 'danger',
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->tr('FundFlow — Membership Application Update', 'FundFlow — تحديث طلب العضوية'))
            ->greeting($this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name]))
            ->line($this->tr('Thank you for your interest in joining FundFlow.', 'شكرًا لاهتمامك بالانضمام إلى FundFlow.'))
            ->line($this->tr('After careful review, we regret to inform you that your membership application could not be approved at this time.', 'بعد مراجعة دقيقة، نأسف لإبلاغك بأنه تعذر الموافقة على طلب العضوية في الوقت الحالي.'));

        if ($this->reason) {
            $mail->line($this->tr('**Reason:** :reason', '**السبب:** :reason', ['reason' => $this->reason]));
        }

        $mail->line($this->tr('If you believe this decision was made in error or have any questions, please contact us at admin@fundflow.sa.', 'إذا كنت تعتقد أن هذا القرار تم بالخطأ أو كانت لديك أي أسئلة، يرجى التواصل معنا عبر admin@fundflow.sa.'))
             ->action($this->tr('Contact Us', 'تواصل معنا'), 'mailto:admin@fundflow.sa');

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $this->tr('FundFlow — Membership Application Update', 'FundFlow — تحديث طلب العضوية'),
            'body' => "Membership rejected for {$notifiable->name}. Reason: {$this->reason}",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $mail;
    }
}
