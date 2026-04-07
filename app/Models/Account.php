<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    public const TYPE_MASTER_CASH = 'master_cash';
    public const TYPE_MASTER_FUND = 'master_fund';
    public const TYPE_MEMBER_CASH = 'member_cash';
    public const TYPE_MEMBER_FUND = 'member_fund';
    public const TYPE_LOAN = 'loan';

    protected $fillable = [
        'slug',
        'name',
        'type',
        'member_id',
        'loan_id',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    // -----------------------------------------------------------------------
    // Static finders for master accounts
    // -----------------------------------------------------------------------

    public static function masterCash(): self
    {
        return static::where('slug', 'master_cash')->firstOrFail();
    }

    public static function masterFund(): self
    {
        return static::where('slug', 'master_fund')->firstOrFail();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** For loan accounts, the outstanding amount is the absolute value of a negative balance. */
    public function getOutstandingAttribute(): float
    {
        return $this->type === self::TYPE_LOAN ? max(0, -(float) $this->balance) : 0.0;
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MASTER_CASH => 'info',
            self::TYPE_MASTER_FUND => 'success',
            self::TYPE_MEMBER_CASH => 'primary',
            self::TYPE_MEMBER_FUND => 'success',
            self::TYPE_LOAN => 'warning',
            default => 'gray',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_MASTER_CASH => 'Master Cash',
            self::TYPE_MASTER_FUND => 'Master Fund',
            self::TYPE_MEMBER_CASH => 'Member Cash',
            self::TYPE_MEMBER_FUND => 'Member Fund',
            self::TYPE_LOAN => 'Loan',
            default => $this->type,
        };
    }
}
