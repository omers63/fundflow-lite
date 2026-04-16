<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row written whenever a parent changes a dependent's monthly allocation amount.
 */
class DependentAllocationChange extends Model
{
    protected $fillable = [
        'parent_member_id',
        'dependent_member_id',
        'old_amount',
        'new_amount',
        'changed_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'integer',
            'new_amount' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_member_id');
    }

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'dependent_member_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isIncrease(): bool
    {
        return $this->new_amount > $this->old_amount;
    }

    public function isDecrease(): bool
    {
        return $this->new_amount < $this->old_amount;
    }

    public function delta(): int
    {
        return $this->new_amount - $this->old_amount;
    }

    public function deltaLabel(): string
    {
        $d = $this->delta();
        $prefix = $d > 0 ? '+' : '';

        return $prefix . 'SAR ' . number_format(abs($d));
    }
}
