<?php

namespace App\Models\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'category', 'description', 'channel', 'trigger_type', 'frequency', 'priority',
        'audience_type', 'title_template', 'body_template', 'email_subject_template', 'email_body_template',
        'tap_screen', 'stop_rule', 'daily_limit', 'cooldown_hours', 'is_active', 'config', 'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(NotificationCampaignRun::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
