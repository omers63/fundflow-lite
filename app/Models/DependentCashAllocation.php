<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parent → dependent cash transfer for a specific contribution cycle (calendar month/year).
 * One completed allocation per dependent per cycle; blocks duplicate “Allocate” runs for that cycle.
 */
class DependentCashAllocation extends Model
{
    protected $fillable = [
        'parent_member_id',
        'dependent_member_id',
        'allocation_month',
        'allocation_year',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'allocation_month' => 'integer',
            'allocation_year' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_member_id');
    }

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'dependent_member_id');
    }
}
