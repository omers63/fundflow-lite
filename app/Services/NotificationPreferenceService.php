<?php

namespace App\Services;

use App\Channels\TwilioWhatsAppChannel;
use App\Models\MemberCommunicationPreference;
use App\Models\Setting;
use NotificationChannels\Twilio\TwilioChannel;

/**
 * Central service for resolving which notification delivery channels to use
 * for a given user and notification category, respecting their saved preferences.
 *
 * Logical channels: 'in_app', 'email', 'sms', 'whatsapp'
 * These map to Laravel channel driver strings / class names.
 */
class NotificationPreferenceService
{
    // ── Notification type constants ──────────────────────────────────────────

    const CONTRIBUTIONS  = 'contributions';
    const LOAN_REPAYMENT = 'loan_repayment';
    const LOAN_ACTIVITY  = 'loan_activity';
    const LOAN_ALERTS    = 'loan_alerts';
    const MEMBERSHIP     = 'membership';
    const STATEMENTS     = 'statements';
    const BROADCASTS     = 'broadcasts';
    const ACCOUNT_ALERTS = 'account_alerts';
    const ALLOCATIONS    = 'allocations';

    // ── Channel name constants ───────────────────────────────────────────────

    const CH_IN_APP   = 'in_app';
    const CH_EMAIL    = 'email';
    const CH_SMS      = 'sms';
    const CH_WHATSAPP = 'whatsapp';

    /**
     * Map logical channel names → Laravel channel driver strings.
     */
    const CHANNEL_MAP = [
        'in_app'   => 'database',
        'email'    => 'mail',
        'sms'      => TwilioChannel::class,
        'whatsapp' => TwilioWhatsAppChannel::class,
    ];

    /**
     * Full metadata for every notification category.
     * Used by the preference UI to render the grid.
     *
     * Keys per entry:
     *   label       — human-readable name
     *   description — one-line explanation
     *   icon        — Heroicon name (for the UI)
     *   supported   — logical channels this category CAN deliver on
     *   defaults    — logical channels used when the member has NO saved preference
     *   forced      — logical channels always ON regardless of preference
     */
    const CATEGORIES = [
        'contributions' => [
            'label'       => 'Contributions',
            'description' => 'Monthly contribution reminders and payment confirmations.',
            'icon'        => 'heroicon-o-arrow-trending-up',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'loan_repayment' => [
            'label'       => 'Loan Repayments',
            'description' => 'Upcoming installment reminders and repayment applied confirmations.',
            'icon'        => 'heroicon-o-banknotes',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'loan_activity' => [
            'label'       => 'Loan Activity',
            'description' => 'Loan approvals, disbursements, settlements, and cancellations.',
            'icon'        => 'heroicon-o-document-text',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'loan_alerts' => [
            'label'       => 'Loan Alerts',
            'description' => 'Default warnings and guarantor liability notifications.',
            'icon'        => 'heroicon-o-exclamation-triangle',
            'supported'   => ['in_app', 'email'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app', 'email'],
        ],
        'membership' => [
            'label'       => 'Membership',
            'description' => 'Membership application approval or rejection status updates.',
            'icon'        => 'heroicon-o-identification',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'statements' => [
            'label'       => 'Monthly Statements',
            'description' => 'Monthly account statement generation and delivery.',
            'icon'        => 'heroicon-o-document-chart-bar',
            'supported'   => ['in_app', 'email'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'broadcasts' => [
            'label'       => 'Admin Announcements',
            'description' => 'Important messages and announcements from the fund administration.',
            'icon'        => 'heroicon-o-megaphone',
            'supported'   => ['in_app', 'email'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
        'account_alerts' => [
            'label'       => 'Account Alerts',
            'description' => 'Delinquency, suspension, and account restoration — critical notices.',
            'icon'        => 'heroicon-o-shield-exclamation',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email', 'sms', 'whatsapp'],
            'forced'      => ['in_app', 'email'],
        ],
        'allocations' => [
            'label'       => 'Allocation Changes',
            'description' => 'Notifications when your parent changes your monthly contribution allocation.',
            'icon'        => 'heroicon-o-adjustments-horizontal',
            'supported'   => ['in_app', 'email', 'sms', 'whatsapp'],
            'defaults'    => ['in_app', 'email'],
            'forced'      => ['in_app'],
        ],
    ];

    /**
     * Resolve Laravel channel strings for a notifiable user.
     *
     * @param  mixed   $notifiable          The notifiable object (User)
     * @param  string  $type                Category constant (e.g. self::CONTRIBUTIONS)
     * @param  array   $supportedLogical    Logical channels the notification class itself supports
     * @return array                         Laravel channel identifiers to pass to via()
     */
    public static function resolve(mixed $notifiable, string $type, array $supportedLogical): array
    {
        $meta   = self::CATEGORIES[$type] ?? null;
        $forced = $meta ? $meta['forced'] : ['in_app'];

        // Get the user's saved preferences (or category defaults)
        $preferred = self::getPreferred($notifiable, $type);

        // Merge forced + preferred, then intersect with what this notification supports
        $effective = array_values(array_unique(array_merge($forced, $preferred)));
        $toSend    = array_intersect($effective, $supportedLogical);

        // Always guarantee in_app if supported
        if (in_array('in_app', $supportedLogical, true) && !in_array('in_app', $toSend, true)) {
            $toSend[] = 'in_app';
        }

        // Remove any channel the admin has disabled system-wide
        $systemEnabled = Setting::commEnabledChannels();
        $toSend = array_values(array_intersect((array) $toSend, $systemEnabled));

        // Ensure we always have at least in_app when it is system-enabled,
        // so critical notifications are never silently swallowed.
        if (in_array('in_app', $systemEnabled, true) && in_array('in_app', $supportedLogical, true) && !in_array('in_app', $toSend, true)) {
            $toSend[] = 'in_app';
        }

        // Map to Laravel channel driver strings, filtering out nulls
        $drivers = [];
        foreach (array_unique($toSend) as $logical) {
            if (isset(self::CHANNEL_MAP[$logical])) {
                $drivers[] = self::CHANNEL_MAP[$logical];
            }
        }

        return array_values($drivers);
    }

    /**
     * Same as resolve(), but for a notification class that only supports
     * in_app + email (no SMS/WhatsApp methods).
     */
    public static function resolveMailOnly(mixed $notifiable, string $type): array
    {
        return self::resolve($notifiable, $type, ['in_app', 'email']);
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private static function getPreferred(mixed $notifiable, string $type): array
    {
        $meta     = self::CATEGORIES[$type] ?? [];
        $defaults = $meta['defaults'] ?? ['in_app', 'email'];

        if (!$notifiable || !isset($notifiable->id)) {
            return $defaults;
        }

        return MemberCommunicationPreference::channelsFor(
            $notifiable->id,
            $type,
            $defaults,
        );
    }
}
