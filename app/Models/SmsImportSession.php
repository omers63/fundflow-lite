<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsImportSession extends Model
{
    protected $fillable = [
        'bank_id', 'template_id', 'imported_by',
        'filename', 'file_path', 'status',
        'total_rows', 'imported_count', 'duplicate_count', 'error_count',
        'notes', 'error_log', 'completed_at',
    ];

    protected $casts = [
        'error_log'    => 'array',
        'completed_at' => 'datetime',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsImportTemplate::class, 'template_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SmsTransaction::class, 'import_session_id');
    }
}
