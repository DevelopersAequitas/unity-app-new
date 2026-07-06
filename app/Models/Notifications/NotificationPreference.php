<?php

namespace App\Models\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'push_enabled', 'email_enabled', 'chat_enabled', 'event_enabled', 'circle_enabled',
        'business_enabled', 'campaign_enabled', 'quiet_hours_start', 'quiet_hours_end', 'config',
    ];

    protected $casts = [
        'push_enabled' => 'boolean', 'email_enabled' => 'boolean', 'chat_enabled' => 'boolean',
        'event_enabled' => 'boolean', 'circle_enabled' => 'boolean', 'business_enabled' => 'boolean',
        'campaign_enabled' => 'boolean', 'config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
