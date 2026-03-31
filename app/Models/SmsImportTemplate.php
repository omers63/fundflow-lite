<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsImportTemplate extends Model
{
    protected $fillable = [
        'bank_id', 'name', 'is_default',
        'delimiter', 'encoding', 'has_header', 'skip_rows',
        'sms_column', 'date_column', 'date_format',
        'amount_pattern', 'date_pattern', 'date_pattern_format', 'reference_pattern',
        'credit_keywords', 'debit_keywords', 'default_transaction_type',
        'duplicate_match_fields', 'duplicate_date_tolerance',
        'member_match_pattern', 'member_match_field',
    ];

    protected $casts = [
        'is_default'               => 'boolean',
        'has_header'               => 'boolean',
        'skip_rows'                => 'integer',
        'credit_keywords'          => 'array',
        'debit_keywords'           => 'array',
        'duplicate_match_fields'   => 'array',
        'duplicate_date_tolerance' => 'integer',
    ];

    protected $attributes = [
        'duplicate_match_fields' => '["date","amount","reference"]',
        'credit_keywords'        => '["credited","received","deposit","credit"]',
        'debit_keywords'         => '["debited","paid","purchase","debit","withdraw"]',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(SmsImportSession::class, 'template_id');
    }
}
