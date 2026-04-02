<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    protected $fillable = [
        'member_id',
        'amount',
        'month',
        'year',
        'paid_at',
        'payment_method',
        'reference_number',
        'notes',
        'is_late',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
            'is_late' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
