<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
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

        if ($panel->getId() === 'member' && $this->role === 'member') {
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
            'member' => $this->role === 'member',
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
}
