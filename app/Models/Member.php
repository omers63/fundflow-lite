<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    /** Allowed monthly contribution amounts (multiples of 500, 500–3000). */
    public const CONTRIBUTION_STEPS = [500, 1000, 1500, 2000, 2500, 3000];

    protected $fillable = [
        'user_id',
        'parent_id',
        'member_number',
        'monthly_contribution_amount',
        'late_contributions_count',
        'late_contributions_amount',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_at'                   => 'date',
            'monthly_contribution_amount' => 'integer',
            'late_contributions_count'    => 'integer',
            'late_contributions_amount'   => 'decimal:2',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The member who sponsors/parents this member. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_id');
    }

    /** Members for whom this member is the parent. */
    public function dependents(): HasMany
    {
        return $this->hasMany(Member::class, 'parent_id');
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

    // -----------------------------------------------------------------------
    // Account shortcuts
    // -----------------------------------------------------------------------

    public function cashAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_CASH)->first();
    }

    public function fundAccount(): ?Account
    {
        return $this->accounts()->where('type', Account::TYPE_MEMBER_FUND)->first();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDelinquent(): bool
    {
        return $this->status === 'delinquent';
    }

    public function isParent(): bool
    {
        return $this->dependents()->exists();
    }

    /** Validate that the given amount is an allowed contribution step. */
    public static function isValidContributionAmount(int $amount): bool
    {
        return in_array($amount, self::CONTRIBUTION_STEPS, true);
    }

    /** Return the select-friendly options array for contribution amounts. */
    public static function contributionAmountOptions(): array
    {
        return array_combine(
            self::CONTRIBUTION_STEPS,
            array_map(fn ($v) => 'SAR ' . number_format($v), self::CONTRIBUTION_STEPS)
        );
    }

    public function getTotalContributionsAttribute(): float
    {
        return (float) $this->contributions()->sum('amount');
    }

    public function getActiveLoansAttribute()
    {
        return $this->loans()->where('status', 'active')->get();
    }

    public function getCashBalanceAttribute(): float
    {
        return (float) ($this->cashAccount()?->balance ?? 0);
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
