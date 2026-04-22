<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MemberDelinquencyRestoredNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function via(mixed $notifiable): array
    {
        return \App\Services\NotificationPreferenceService::resolveMailOnly(
            $notifiable,
            \App\Services\NotificationPreferenceService::ACCOUNT_ALERTS,
        );
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->tr('Membership restored', 'تمت إعادة تفعيل العضوية'),
            'body' => $this->tr('Your membership is active again. Contribution and repayment obligations are back in your name under the usual rules.', 'أصبحت عضويتك نشطة مرة أخرى. عادت التزامات المساهمة والسداد باسمك وفق القواعد المعتادة.'),
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'actions' => [
                ['label' => $this->tr('Member portal', 'بوابة الأعضاء'), 'url' => url('/member')],
            ],
        ];
    }
}
