<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'status',
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
}
