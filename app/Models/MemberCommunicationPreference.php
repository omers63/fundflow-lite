<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class MemberCommunicationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Cache helpers ────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saved(fn(self $m) => Cache::forget("comm_pref:{$m->user_id}:{$m->notification_type}"));
        static::deleted(fn(self $m) => Cache::forget("comm_pref:{$m->user_id}:{$m->notification_type}"));
    }

    /**
     * Get the channels array for a (user, type) pair.
     * Falls back to the supplied default if no record exists.
     */
    public static function channelsFor(int $userId, string $type, array $default): array
    {
        return Cache::remember("comm_pref:{$userId}:{$type}", 600, function () use ($userId, $type, $default): array {
            $row = static::where('user_id', $userId)->where('notification_type', $type)->first();
            return $row ? (array) $row->channels : $default;
        });
    }

    /**
     * Save channels for a user + type, enforcing forced channels.
     */
    public static function saveFor(int $userId, string $type, array $channels, array $forced = []): void
    {
        $effective = array_values(array_unique(array_merge($forced, $channels)));
        static::updateOrCreate(
            ['user_id' => $userId, 'notification_type' => $type],
            ['channels' => $effective],
        );
    }
}
