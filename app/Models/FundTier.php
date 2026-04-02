<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundTier extends Model
{
    protected $fillable = [
        'tier_number',
        'label',
        'loan_tier_id',
        'percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    public function loanTier(): BelongsTo
    {
        return $this->belongsTo(LoanTier::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function getLabelAttribute($value): string
    {
        return $value ?? ($this->tier_number === 0 ? 'Emergency' : "Tier {$this->tier_number}");
    }

    public function isEmergency(): bool
    {
        return $this->tier_number === 0;
    }

    /**
     * Allocated amount = master fund balance × (percentage / 100).
     * Available = allocated - active exposure.
     */
    public function getAllocatedAmountAttribute(): float
    {
        $masterBalance = (float) (Account::masterFund()?->balance ?? 0);
        return $masterBalance * ($this->percentage / 100);
    }

    public function getActiveExposureAttribute(): float
    {
        return (float) Loan::where('fund_tier_id', $this->id)
            ->whereIn('status', ['approved', 'active'])
            ->sum('amount_approved');
    }

    public function getAvailableAmountAttribute(): float
    {
        return max(0, $this->allocated_amount - $this->active_exposure);
    }

    public function getActiveLoansCountAttribute(): int
    {
        return Loan::where('fund_tier_id', $this->id)
            ->whereIn('status', ['approved', 'active'])
            ->count();
    }

    /** Next queue position for a new loan in this fund tier. */
    public function nextQueuePosition(): int
    {
        return (Loan::where('fund_tier_id', $this->id)
            ->whereIn('status', ['pending', 'approved'])
            ->max('queue_position') ?? 0) + 1;
    }
}
