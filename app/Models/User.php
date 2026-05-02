<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasLocalePreference
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->role !== 'member') {
                return;
            }

            $memberRole = Role::firstOrCreate([
                'name' => 'member',
                'guard_name' => 'web',
            ]);

            if (!$user->hasRole($memberRole->name)) {
                $user->assignRole($memberRole);
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'avatar_path',
        'role',
        'status',
        'preferred_locale',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->status !== 'approved') {
            return false;
        }

        if ($panel->getId() === 'member') {
            $member = $this->member;
            if ($member === null) {
                return false;
            }
            if (in_array($member->status, ['suspended', 'terminated'], true)) {
                return false;
            }
        }

        return match ($panel->getId()) {
            'admin' => $this->role === 'admin',
            'member' => $this->member !== null,
            default => false,
        };
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function membershipApplication(): HasOne
    {
        return $this->hasOne(MembershipApplication::class);
    }

    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function routeNotificationForTwilio(): string
    {
        return $this->phone ?? '';
    }

    public function preferredLocale(): string
    {
        return in_array($this->preferred_locale, ['ar', 'en'], true)
            ? $this->preferred_locale
            : config('app.locale', 'en');
    }

    /**
     * Normalize a path for the `public` disk (strip duplicate `storage/` / `public/` prefixes).
     */
    public static function normalizePublicDiskRelativePath(?string $raw): ?string
    {
        if (!filled($raw)) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim((string) $raw, '/'));
        while (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        return filled($path) ? $path : null;
    }

    /**
     * Public URL for the stored avatar file (handles redundant `storage/` prefixes in DB).
     * Uses the public disk URL so it stays consistent with Filament file URLs and `APP_URL`.
     */
    public function avatarPublicUrl(): ?string
    {
        if (!filled($this->avatar_path)) {
            return null;
        }

        $raw = (string) $this->avatar_path;
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        $path = self::normalizePublicDiskRelativePath($raw);
        if ($path === null) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarPublicUrl();
    }
}
