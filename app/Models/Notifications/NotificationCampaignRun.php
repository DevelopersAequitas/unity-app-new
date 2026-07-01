<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationCampaignRun extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'campaign_id', 'run_type', 'status', 'audience_count', 'sent_count', 'failed_count', 'skipped_count',
        'started_at', 'finished_at', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }
}
