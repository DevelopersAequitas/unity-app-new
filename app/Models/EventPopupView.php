<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPopupView extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'event_id',
        'popup_version',
        'seen_at',
    ];

    protected $casts = [
        'popup_version' => 'integer',
        'seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
