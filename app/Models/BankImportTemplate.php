<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankImportTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bank_id',
        'name',
        'is_default',
        'delimiter',
        'encoding',
        'has_header',
        'skip_rows',
        'date_column',
        'date_format',
        'amount_type',
        'amount_column',
        'credit_column',
        'debit_column',
        'type_column',
        'credit_indicator',
        'debit_indicator',
        'description_column',
        'reference_column',
        'balance_column',
        'optional_columns',
        'duplicate_match_fields',
        'duplicate_date_tolerance',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'has_header' => 'boolean',
        'skip_rows' => 'integer',
        'optional_columns' => 'array',
        'duplicate_match_fields' => 'array',
        'duplicate_date_tolerance' => 'integer',
    ];

    protected $attributes = [
        'duplicate_match_fields' => '["date","amount","reference"]',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(BankImportSession::class, 'template_id');
    }

    /** Human-readable delimiter label */
    public function getDelimiterLabelAttribute(): string
    {
        return match ($this->delimiter) {
            ',' => 'Comma (,)',
            ';' => 'Semicolon (;)',
            "\t" => 'Tab',
            '|' => 'Pipe (|)',
            default => $this->delimiter,
        };
    }
}
