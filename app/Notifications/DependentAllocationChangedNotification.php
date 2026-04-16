<?php

namespace App\Notifications;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\DependentAllocationChange;
use App\Models\NotificationLog;
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

    public function __construct(
        public readonly DependentAllocationChange $change,
        public readonly string $role = 'dependent',
    ) {}

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
        $change    = $this->change;
        $depName   = $change->dependent?->user?->name ?? 'Dependent';
        $parentName= $change->parent?->user?->name ?? 'Parent';
        $oldFmt    = 'SAR ' . number_format($change->old_amount);
        $newFmt    = 'SAR ' . number_format($change->new_amount);
        $direction = $change->isIncrease() ? '↑' : '↓';
        $color     = $change->isIncrease() ? 'success' : 'warning';

        return match ($this->role) {
            'dependent' => [
                'title'   => "Monthly Allocation Updated {$direction}",
                'body'    => "Your monthly contribution allocation has been changed from {$oldFmt} to {$newFmt}.",
                'icon'    => 'heroicon-o-adjustments-horizontal',
                'color'   => $color,
                'actions' => [
                    ['label' => 'View Contribution Settings', 'url' => url('/member/my-contribution-settings-page')],
                ],
            ],
            'parent' => [
                'title'   => "Allocation Updated — {$depName}",
                'body'    => "You updated {$depName}'s monthly allocation from {$oldFmt} to {$newFmt}.",
                'icon'    => 'heroicon-o-check-circle',
                'color'   => 'success',
                'actions' => [
                    ['label' => 'View Dependents', 'url' => url('/member/my-dependents')],
                ],
            ],
            default => [ // admin
                'title'   => "Allocation Change: {$depName}",
                'body'    => "Parent {$parentName} changed {$depName}'s monthly allocation from {$oldFmt} to {$newFmt}.",
                'icon'    => 'heroicon-o-bell-alert',
                'color'   => 'info',
                'actions' => [
                    ['label' => 'View Members', 'url' => url('/admin/members')],
                ],
            ],
        };
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    public function toMail(mixed $notifiable): MailMessage
    {
        $change    = $this->change;
        $depName   = $change->dependent?->user?->name ?? 'Dependent';
        $parentName= $change->parent?->user?->name ?? 'Parent';
        $oldFmt    = 'SAR ' . number_format($change->old_amount);
        $newFmt    = 'SAR ' . number_format($change->new_amount);
        $changedAt = $change->created_at?->format('d F Y H:i') ?? now()->format('d F Y H:i');
        $changedBy = $change->changedBy?->name ?? $parentName;

        $subject = match ($this->role) {
            'dependent' => 'FundFlow — Your Monthly Allocation Has Changed',
            default     => "FundFlow — Allocation Updated for {$depName}",
        };

        NotificationLog::create([
            'user_id' => $notifiable->id,
            'channel' => 'mail',
            'subject' => $subject,
            'body'    => "Allocation for {$depName}: {$oldFmt} → {$newFmt}.",
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return match ($this->role) {
            'dependent' => (new MailMessage)
                ->subject($subject)
                ->greeting("Dear {$notifiable->name},")
                ->line("Your monthly contribution allocation has been updated by your parent member.")
                ->line("**Previous amount:** {$oldFmt}")
                ->line("**New amount:** {$newFmt}")
                ->line("**Effective:** {$changedAt}")
                ->when($change->note, fn($m) => $m->line("**Note:** {$change->note}"))
                ->line("This change will be reflected in your next contribution cycle.")
                ->action('View Contribution Settings', url('/member/my-contribution-settings-page')),

            default => (new MailMessage)
                ->subject($subject)
                ->greeting("Dear {$notifiable->name},")
                ->line("The monthly allocation for **{$depName}** has been updated.")
                ->line("**Changed by:** {$changedBy}")
                ->line("**Previous amount:** {$oldFmt}")
                ->line("**New amount:** {$newFmt}")
                ->line("**Changed at:** {$changedAt}")
                ->when($change->note, fn($m) => $m->line("**Note:** {$change->note}"))
                ->action('View Dependents', url('/member/my-dependents')),
        };
    }

    // ── SMS ───────────────────────────────────────────────────────────────────

    public function toTwilio(mixed $notifiable): TwilioSmsMessage
    {
        $change  = $this->change;
        $oldFmt  = 'SAR ' . number_format($change->old_amount);
        $newFmt  = 'SAR ' . number_format($change->new_amount);
        $depName = $change->dependent?->user?->name ?? 'Dependent';

        $body = match ($this->role) {
            'dependent' => "FundFlow: Your monthly allocation changed from {$oldFmt} to {$newFmt}. " . url('/member'),
            default     => "FundFlow: Allocation for {$depName} updated: {$oldFmt} → {$newFmt}. " . url('/member'),
        };

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

    // ── WhatsApp ─────────────────────────────────────────────────────────────

    public function toWhatsApp(mixed $notifiable): string
    {
        $change  = $this->change;
        $oldFmt  = 'SAR ' . number_format($change->old_amount);
        $newFmt  = 'SAR ' . number_format($change->new_amount);
        $depName = $change->dependent?->user?->name ?? 'Dependent';
        $arrow   = $change->isIncrease() ? '⬆️' : '⬇️';

        return match ($this->role) {
            'dependent' => "{$arrow} *FundFlow — Allocation Updated*\n\nDear {$notifiable->name},\n\nYour monthly contribution allocation has been updated.\n\n*Previous:* {$oldFmt}\n*New:* {$newFmt}\n\n" . url('/member'),
            default     => "{$arrow} *FundFlow — Allocation Change*\n\n*{$depName}*'s monthly allocation:\n\n*Previous:* {$oldFmt}\n*New:* {$newFmt}\n\n" . url('/member'),
        };
    }
}
