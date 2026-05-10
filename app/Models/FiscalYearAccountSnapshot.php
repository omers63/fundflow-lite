<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYearAccountSnapshot extends Model
{
    protected $table = 'fiscal_year_account_snapshots';

    protected $fillable = [
        'fiscal_year_id',
        'account_id',
        'closing_balance',
    ];

    protected function casts(): array
    {
        return [
            'closing_balance' => 'decimal:2',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
