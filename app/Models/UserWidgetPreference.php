<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWidgetPreference extends Model
{
    protected $fillable = [
        'user_id',
        'panel',
        'page',
        'visible_widgets',
    ];

    protected function casts(): array
    {
        return [
            'visible_widgets' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

