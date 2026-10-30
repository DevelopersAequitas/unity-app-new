<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvertiserCampaign extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_CHANGES_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULED,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'advertiser_campaigns';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_id',
        'subscription_id',
        'user_id',
        'brand_partner_id',
        'campaign_number',
        'name',
        'objective',
        'description',
        'target_audience',
        'target_locations',
        'requested_placements',
        'cta_text',
        'destination_url',
        'status',
        'requested_starts_at',
        'requested_ends_at',
        'approved_at',
        'published_at',
        'paused_at',
        'completed_at',
        'admin_notes',
        'applicant_feedback',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected $casts = [
        'target_locations' => 'array',
        'requested_placements' => 'array',
        'metadata' => 'array',
        'requested_starts_at' => 'datetime',
        'requested_ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdvertiserApplication::class, 'application_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AdvertiserSubscription::class, 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaignAds(): HasMany
    {
        return $this->hasMany(AdvertiserCampaignAd::class, 'campaign_id');
    }

    public function ads(): BelongsToMany
    {
        return $this->belongsToMany(Ad::class, 'advertiser_campaign_ads', 'campaign_id', 'ad_id')
            ->withPivot([
                'id',
                'creative_role',
                'status',
                'is_primary',
                'approved_by',
                'approved_at',
                'published_at',
                'detached_at',
                'admin_notes',
                'metadata',
            ])
            ->withTimestamps();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AdvertiserCampaignStatusHistory::class, 'campaign_id')->orderByDesc('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }
}
