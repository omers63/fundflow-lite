<?php

namespace App\Filament\Member\Pages;

use App\Models\MemberCommunicationPreference;
use App\Models\Setting;
use App\Services\NotificationPreferenceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class MyNotificationPreferencesPage extends Page
{
    protected string $view = 'filament.member.pages.my-notification-preferences';

    protected static ?string $navigationLabel = 'Notification Preferences';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('app.member.notification_preferences');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.nav.group.settings');
    }

    public function getTitle(): string
    {
        return __('app.member.notification_preferences');
    }

    public function getSubheading(): ?string
    {
        return __('app.member.notification_preferences_subheading');
    }

    // ── Livewire state ───────────────────────────────────────────────────────

    /** Keyed by category → array of enabled logical channels */
    public array $prefs = [];

    /** Flash state for per-row saved indicators */
    public ?string $savedAt = null;

    public function mount(): void
    {
        $this->loadPreferences();
    }

    public function loadPreferences(): void
    {
        $userId = auth()->id();

        foreach (NotificationPreferenceService::CATEGORIES as $type => $meta) {
            $saved = MemberCommunicationPreference::channelsFor($userId, $type, $meta['defaults']);
            // Always include forced channels in the loaded state
            $this->prefs[$type] = array_values(array_unique(
                array_merge($meta['forced'], $saved)
            ));
        }
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $userId = auth()->id();
        $categories = NotificationPreferenceService::CATEGORIES;

        foreach ($this->prefs as $type => $channels) {
            if (! isset($categories[$type])) {
                continue;
            }

            $meta = $categories[$type];
            $forced = $meta['forced'];
            $allowed = $meta['supported'];

            // Sanitize: only keep supported channels + merge forced
            $clean = array_values(array_unique(array_merge(
                $forced,
                array_intersect((array) $channels, $allowed),
            )));

            MemberCommunicationPreference::saveFor($userId, $type, $clean, $forced);
        }

        $this->savedAt = now()->toTimeString();

        Notification::make()
            ->title(__('app.member.preferences_saved'))
            ->body(__('app.member.preferences_saved_body'))
            ->success()
            ->send();
    }

    /**
     * Quick toggle for a single channel within a category.
     * Called via wire:click from the view.
     */
    public function toggleChannel(string $type, string $channel): void
    {
        $categories = NotificationPreferenceService::CATEGORIES;

        if (! isset($categories[$type])) {
            return;
        }

        $meta = $categories[$type];
        $forced = $meta['forced'];
        $current = $this->prefs[$type] ?? $meta['defaults'];

        // Can't toggle forced channels
        if (in_array($channel, $forced, true)) {
            return;
        }

        // Can't toggle unsupported channels
        if (! in_array($channel, $meta['supported'], true)) {
            return;
        }

        if (in_array($channel, $current, true)) {
            $current = array_values(array_filter($current, fn ($c) => $c !== $channel));
        } else {
            $current[] = $channel;
            $current = array_values(array_unique($current));
        }

        $this->prefs[$type] = $current;
    }

    // ── Computed helpers ─────────────────────────────────────────────────────

    #[Computed]
    public function categories(): array
    {
        return NotificationPreferenceService::CATEGORIES;
    }

    public function isEnabled(string $type, string $channel): bool
    {
        return in_array($channel, $this->prefs[$type] ?? [], true);
    }

    public function isForced(string $type, string $channel): bool
    {
        $meta = NotificationPreferenceService::CATEGORIES[$type] ?? [];

        return in_array($channel, $meta['forced'] ?? [], true);
    }

    public function isSupported(string $type, string $channel): bool
    {
        $meta = NotificationPreferenceService::CATEGORIES[$type] ?? [];

        return in_array($channel, $meta['supported'] ?? [], true);
    }

    /** True when the channel is enabled system-wide by the administrator. */
    public function isSystemEnabled(string $channel): bool
    {
        return Setting::commChannelEnabled($channel);
    }

    #[Computed]
    public function systemEnabledChannels(): array
    {
        return Setting::commEnabledChannels();
    }
}
