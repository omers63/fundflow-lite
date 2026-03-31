<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyStatement extends Model
{
    protected $fillable = [
        'member_id',
        'period',
        'opening_balance',
        'total_contributions',
        'total_repayments',
        'closing_balance',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'total_contributions' => 'decimal:2',
            'total_repayments' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function getPeriodFormattedAttribute(): string
    {
        $parts = explode('-', $this->period);
        return \Carbon\Carbon::create((int)$parts[0], (int)$parts[1], 1)->format('F Y');
    }
}
