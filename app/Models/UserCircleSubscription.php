<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCircleSubscription extends Model
{
    use HasFactory;

    protected $table = 'user_circle_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'paid_starts_at' => 'datetime',
        'paid_ends_at' => 'datetime',
        'last_event_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }
}
