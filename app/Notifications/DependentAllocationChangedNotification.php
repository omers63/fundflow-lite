<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesCommunication;
use App\Channels\TwilioWhatsAppChannel;
use App\Models\DependentAllocationChange;
use App\Models\NotificationLog;
use App\Services\EmailTemplateService;
use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

/**
 * Dispatched to three audiences via a single $role flag:
 *   'dependent' — the member whose allocation changed (full channel preferences)
 *   'parent'    — confirmation to the parent who made the change (in-app + email)
 *   'admin'     — informational alert to admin users (in-app only)
 */
class DependentAllocationChangedNotification extends Notification
{
    use Queueable;
    use LocalizesCommunication;

    public function __construct(
        public readonly DependentAllocationChange $change,
        public readonly string $role = 'dependent',
    ) {
    }

    public function via(mixed $notifiable): array
    {
        return match ($this->role) {
            'dependent' => NotificationPreferenceService::resolve(
                $notifiable,
                NotificationPreferenceService::ALLOCATIONS,
                ['in_app', 'email', 'sms', 'whatsapp'],
            ),
            'parent' => NotificationPreferenceService::resolveMailOnly(
                $notifiable,
                NotificationPreferenceService::ALLOCATIONS,
            ),
            default => ['database'],  // admin: in-app only
        };
    }

    // ── In-App ───────────────────────────────────────────────────────────────

    public function toDatabase(mixed $notifiable): array
    {
        $change = $this->change;
        $depName = $change->dependent?->user?->name ?? $this->tr('Dependent', 'تابع');
        $parentName = $change->parent?->user?->name ?? $this->tr('Parent', 'ولي الأمر');
        $oldFmt = 'SAR ' . number_format($change->old_amount);
        $newFmt = 'SAR ' . number_format($change->new_amount);
        $direction = $change->isIncrease() ? '↑' : '↓';
        $color = $change->isIncrease() ? 'success' : 'warning';

        return match ($this->role) {
            'dependent' => [
                'title' => $this->tr("Monthly Allocation Updated {$direction}", "تم تحديث التخصيص الشهري {$direction}"),
                'body' => $this->tr('Your monthly contribution allocation has been changed from :old to :new.', 'تم تغيير تخصيص مساهمتك الشهرية من :old إلى :new.', ['old' => $oldFmt, 'new' => $newFmt]),
                'icon' => 'heroicon-o-adjustments-horizontal',
                'color' => $color,
                'actions' => [
                    ['label' => $this->tr('View Contribution Settings', 'عرض إعدادات المساهمة'), 'url' => url('/member/my-contribution-settings-page')],
                ],
            ],
            'parent' => [
                'title' => $this->tr('Allocation Updated — :name', 'تم تحديث التخصيص — :name', ['name' => $depName]),
                'body' => $this->tr('You updated :name\'s monthly allocation from :old to :new.', 'قمت بتحديث التخصيص الشهري لـ :name من :old إلى :new.', ['name' => $depName, 'old' => $oldFmt, 'new' => $newFmt]),
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'actions' => [
                    ['label' => $this->tr('View Dependents', 'عرض التابعين'), 'url' => url('/member/my-dependents')],
                ],
            ],
            default => [ // admin
                'title' => $this->tr('Allocation Change: :name', 'تغيير تخصيص: :name', ['name' => $depName]),
                'body' => $this->tr('Parent :parent changed :name\'s monthly allocation from :old to :new.', 'قام ولي الأمر :parent بتغيير التخصيص الشهري لـ :name من :old إلى :new.', ['parent' => $parentName, 'name' => $depName, 'old' => $oldFmt, 'new' => $newFmt]),
                'icon' => 'heroicon-o-bell-alert',
                'color' => 'info',
                'actions' => [
                    ['label' => $this->tr('View Members', 'عرض الأعضاء'), 'url' => url('/admin/members')],
                ],
            ],
        };
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    public function toMail(mixed $notifiable): MailMessage
    {
        $change = $this->change;
        $depName = $change->dependent?->user?->name ?? $this->tr('Dependent', 'تابع');
        $parentName = $change->parent?->user?->name ?? $this->tr('Parent', 'ولي الأمر');
        $oldFmt = 'SAR ' . number_format($change->old_amount);
        $newFmt = 'SAR ' . number_format($change->new_amount);
        $changedAt = $change->created_at?->format('d F Y H:i') ?? now()->format('d F Y H:i');
        $changedBy = $change->changedBy?->name ?? $parentName;
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : app()->getLocale();

        $defaultSubject = match ($this->role) {
            'dependent' => $this->tr('FundFlow — Your Monthly Allocation Has Changed', 'FundFlow — تم تغيير تخصيصك الشهري'),
            default => $this->tr('FundFlow — Allocation Updated for :name', 'FundFlow — تم تحديث التخصيص لـ :name', ['name' => $depName]),
        };
        $templateKey = $this->role === 'dependent' ? 'dependent_allocation_changed' : 'parent_allocation_changed';
        $vars = [
            'name' => $notifiable->name,
            'dependent_name' => $depName,
            'changed_by' => $changedBy,
            'old_amount' => $oldFmt,
            'new_amount' => $newFmt,
            'changed_at' => $changedAt,
            'note' => (string) ($change->note ?? ''),
        ];
        $subject = EmailTemplateService::render(
            EmailTemplateService::get($templateKey, 'subject', $locale, $defaultSubject),
            $vars
        );

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body' => "Allocation for {$depName}: {$oldFmt} → {$newFmt}.",
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return match ($this->role) {
            'dependent' => (new MailMessage)
                ->subject($subject)
                ->greeting(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])), $vars))
                ->line(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'body', $locale, implode("\n", [
                    $this->tr('Your monthly contribution allocation has been updated by your parent member.', 'تم تحديث تخصيص مساهمتك الشهرية من قبل ولي الأمر.'),
                    $this->tr('**Previous amount:** :amount', '**المبلغ السابق:** :amount', ['amount' => $oldFmt]),
                    $this->tr('**New amount:** :amount', '**المبلغ الجديد:** :amount', ['amount' => $newFmt]),
                    $this->tr('**Effective:** :date', '**يسري من:** :date', ['date' => $changedAt]),
                    $this->tr('This change will be reflected in your next contribution cycle.', 'سيظهر هذا التغيير في دورة المساهمة القادمة.'),
                ])), $vars))
                ->when($change->note, fn($m) => $m->line(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'note_line', $locale, $this->tr('**Note:** :note', '**ملاحظة:** :note', ['note' => $change->note])), $vars)))
                ->action(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'action_label', $locale, $this->tr('View Contribution Settings', 'عرض إعدادات المساهمة')), $vars), url('/member/my-contribution-settings-page')),

            default => (new MailMessage)
                ->subject($subject)
                ->greeting(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'greeting', $locale, $this->tr('Dear :name,', 'عزيزي/عزيزتي :name،', ['name' => $notifiable->name])), $vars))
                ->line(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'body', $locale, implode("\n", [
                    $this->tr('The monthly allocation for **:name** has been updated.', 'تم تحديث التخصيص الشهري لـ **:name**.', ['name' => $depName]),
                    $this->tr('**Changed by:** :name', '**تم التغيير بواسطة:** :name', ['name' => $changedBy]),
                    $this->tr('**Previous amount:** :amount', '**المبلغ السابق:** :amount', ['amount' => $oldFmt]),
                    $this->tr('**New amount:** :amount', '**المبلغ الجديد:** :amount', ['amount' => $newFmt]),
                    $this->tr('**Changed at:** :date', '**وقت التغيير:** :date', ['date' => $changedAt]),
                ])), $vars))
                ->when($change->note, fn($m) => $m->line(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'note_line', $locale, $this->tr('**Note:** :note', '**ملاحظة:** :note', ['note' => $change->note])), $vars)))
                ->action(EmailTemplateService::render(EmailTemplateService::get($templateKey, 'action_label', $locale, $this->tr('View Dependents', 'عرض التابعين')), $vars), url('/member/my-dependents')),
        };
    }

    // ── SMS ───────────────────────────────────────────────────────────────────

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $change = $this->change;
        $oldFmt = 'SAR ' . number_format($change->old_amount);
        $newFmt = 'SAR ' . number_format($change->new_amount);
        $depName = $change->dependent?->user?->name ?? $this->tr('Dependent', 'تابع');

        $body = match ($this->role) {
            'dependent' => $this->tr('FundFlow: Your monthly allocation changed from :old to :new. :url', 'FundFlow: تم تغيير تخصيصك الشهري من :old إلى :new. :url', ['old' => $oldFmt, 'new' => $newFmt, 'url' => url('/member')]),
            default => $this->tr('FundFlow: Allocation for :name updated: :old → :new. :url', 'FundFlow: تم تحديث تخصيص :name: :old ← :new. :url', ['name' => $depName, 'old' => $oldFmt, 'new' => $newFmt, 'url' => url('/member')]),
        };

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

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    public function toWhatsApp(mixed $notifiable): string
    {
        $change = $this->change;
        $oldFmt = 'SAR ' . number_format($change->old_amount);
        $newFmt = 'SAR ' . number_format($change->new_amount);
        $depName = $change->dependent?->user?->name ?? $this->tr('Dependent', 'تابع');
        $arrow = $change->isIncrease() ? '⬆️' : '⬇️';

        return match ($this->role) {
            'dependent' => $this->tr(
                "{$arrow} *FundFlow — Allocation Updated*\n\nDear :name,\n\nYour monthly contribution allocation has been updated.\n\n*Previous:* :old\n*New:* :new\n\n:url",
                "{$arrow} *FundFlow — تم تحديث التخصيص*\n\nعزيزي/عزيزتي :name،\n\nتم تحديث تخصيص مساهمتك الشهرية.\n\n*السابق:* :old\n*الجديد:* :new\n\n:url",
                ['name' => $notifiable->name, 'old' => $oldFmt, 'new' => $newFmt, 'url' => url('/member')],
            ),
            default => $this->tr(
                "{$arrow} *FundFlow — Allocation Change*\n\n*:name*'s monthly allocation:\n\n*Previous:* :old\n*New:* :new\n\n:url",
                "{$arrow} *FundFlow — تغيير تخصيص*\n\nالتخصيص الشهري لـ *:name*:\n\n*السابق:* :old\n*الجديد:* :new\n\n:url",
                ['name' => $depName, 'old' => $oldFmt, 'new' => $newFmt, 'url' => url('/member')],
            ),
        };
    }
}
