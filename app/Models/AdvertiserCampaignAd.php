<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertiserCampaignAd extends Model
{
    use HasFactory;
    use HasUuids;

    public const ROLE_PRIMARY = 'primary';
    public const ROLE_ALTERNATE = 'alternate';
    public const ROLE_PLACEMENT_VARIANT = 'placement_variant';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';
    public const STATUS_DETACHED = 'detached';

    protected $table = 'advertiser_campaign_ads';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'ad_id',
        'creative_role',
        'status',
        'is_primary',
        'approved_by',
        'approved_at',
        'published_at',
        'detached_at',
        'admin_notes',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'detached_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdvertiserCampaign::class, 'campaign_id');
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class, 'ad_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
