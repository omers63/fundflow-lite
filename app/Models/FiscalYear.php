<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    protected $fillable = [
        'code',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by_id',
        'close_metadata',
        'archive_database_path',
        'purged_primary_at',
        'purge_metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'purged_primary_at' => 'datetime',
            'close_metadata' => 'array',
            'purge_metadata' => 'array',
        ];
    }

    public function closures(): HasMany
    {
        return $this->hasMany(FiscalYearClosure::class);
    }

    public function accountSnapshots(): HasMany
    {
        return $this->hasMany(FiscalYearAccountSnapshot::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }
}
