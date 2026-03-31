<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'member_id',
        'amount_requested',
        'amount_approved',
        'purpose',
        'installments_count',
        'status',
        'applied_at',
        'approved_at',
        'approved_by_id',
        'disbursed_at',
        'due_date',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'amount_approved' => 'decimal:2',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    public function account(): ?Account
    {
        return Account::where('loan_id', $this->id)->where('type', Account::TYPE_LOAN)->first();
    }

    public function getPaidInstallmentsCountAttribute(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->installments()->whereIn('status', ['pending', 'overdue'])->sum('amount');
    }

    public function hasOverdueInstallments(): bool
    {
        return $this->installments()->where('status', 'overdue')->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
