<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'is_late',
        'paid_by_guarantor',
    ];

    protected function casts(): array
    {
        return [
            'amount'            => 'decimal:2',
            'due_date'          => 'date',
            'paid_at'           => 'datetime',
            'is_late'           => 'boolean',
            'paid_by_guarantor' => 'boolean',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
