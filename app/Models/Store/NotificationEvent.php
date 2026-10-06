<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends Model
{
    use HasUuids;

    protected $table = 'notification_events';

    const UPDATED_AT = null;

    protected $fillable = [
        'event_key',
        'user_id',
        'reference_type',
        'reference_id',
        'payload',
        'status',
        'available_at',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'event_id');
    }
}
