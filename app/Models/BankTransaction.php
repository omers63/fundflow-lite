<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_id', 'import_session_id',
        'member_id',
        'transaction_date', 'amount', 'transaction_type',
        'description', 'reference',
        'is_duplicate', 'duplicate_of_id',
        'raw_data',
        'posted_at', 'posted_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
        'is_duplicate'     => 'boolean',
        'raw_data'         => 'array',
        'posted_at'        => 'datetime',
    ];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(BankImportSession::class, 'import_session_id');
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
        return $this->belongsTo(BankTransaction::class, 'duplicate_of_id');
    }
}
