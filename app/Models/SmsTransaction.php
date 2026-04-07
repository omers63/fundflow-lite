<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bank_id',
        'import_session_id',
        'member_id',
        'transaction_date',
        'amount',
        'transaction_type',
        'reference',
        'raw_sms',
        'raw_data',
        'is_duplicate',
        'duplicate_of_id',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_duplicate' => 'boolean',
        'raw_data' => 'array',
        'posted_at' => 'datetime',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(SmsImportSession::class, 'import_session_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(SmsTransaction::class, 'duplicate_of_id');
    }
}
