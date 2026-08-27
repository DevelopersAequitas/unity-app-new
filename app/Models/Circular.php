<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Circular extends Model
{
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'event',
        'announcement',
        'update',
        'opportunity',
        'recognition',
        'policy',
        'collaboration_opportunity',
        'investor_opportunity',
        'partnership_announcement',
        'member_achievement',
        'event_invitation',
        'industry_update',
    ];

    public const PRIORITY_OPTIONS = ['normal', 'important', 'urgent'];

    public const AUDIENCE_OPTIONS = ['all_members', 'circle_members', 'fempreneur', 'greenpreneur'];

    public const STATUS_OPTIONS = ['draft', 'published', 'archived'];

    protected $table = 'circulars';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'summary',
        'category',
        'priority',
        'featured_image_url',
        'content',
        'attachment_url',
        'video_url',
        'audience_type',
        'target_roles',
        'city_id',
        'circle_id',
        'send_push_notification',
        'allow_comments',
        'is_pinned',
        'status',
        'publish_date',
        'expiry_date',
        'cta_label',
        'cta_url',
        'notification_sent_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'send_push_notification' => 'boolean',
        'allow_comments' => 'boolean',
        'is_pinned' => 'boolean',
        'target_roles' => 'array',
        'publish_date' => 'datetime',
        'expiry_date' => 'datetime',
        'notification_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $circular): void {
            if (empty($circular->id)) {
                $circular->id = Str::uuid()->toString();
            }
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
