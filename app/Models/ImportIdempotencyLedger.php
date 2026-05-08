<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportIdempotencyLedger extends Model
{
    protected $fillable = [
        'scope',
        'idempotency_key',
        'file_fingerprint',
        'member_id',
        'line_number',
        'context',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

