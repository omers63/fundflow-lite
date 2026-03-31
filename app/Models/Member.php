<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// Account model resolved at runtime — no import needed (same namespace layer)

class Member extends Model
{
    protected $fillable = [
        'user_id',
        'member_number',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(MonthlyStatement::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function cashAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_CASH)->first();
    }

    public function fundAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_FUND)->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDelinquent(): bool
    {
        return $this->status === 'delinquent';
    }

    public function getTotalContributionsAttribute(): float
    {
        return (float) $this->contributions()->sum('amount');
    }

    public function getActiveLoansAttribute()
    {
        return $this->loans()->where('status', 'active')->get();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDelinquent($query)
    {
        return $query->where('status', 'delinquent');
    }
}
