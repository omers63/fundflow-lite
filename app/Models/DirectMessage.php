<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'parent_id',
        'subject',
        'body',
        'attachments',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DirectMessage::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DirectMessage::class, 'parent_id')->orderBy('created_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /** Root messages (not replies) */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /** All messages in a conversation thread: root + all children of given root */
    public function scopeThread($query, int $rootId)
    {
        return $query->where(function ($q) use ($rootId) {
            $q->where('id', $rootId)->orWhere('parent_id', $rootId);
        });
    }
}
