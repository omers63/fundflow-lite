<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYearClosure extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'action',
        'status',
        'archive_connection',
        'archive_batch_id',
        'started_by_id',
        'finished_by_id',
        'started_at',
        'finished_at',
        'row_counts',
        'integrity_checks',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'row_counts' => 'array',
            'integrity_checks' => 'array',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_id');
    }

    public function finishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by_id');
    }
}
