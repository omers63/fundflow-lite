<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'swift_code',
        'account_number',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function importTemplates(): HasMany
    {
        return $this->hasMany(BankImportTemplate::class);
    }

    public function defaultTemplate(): ?BankImportTemplate
    {
        return $this->importTemplates()->where('is_default', true)->first();
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(BankImportSession::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
