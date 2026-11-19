<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CelebrationSchedule extends Model
{
    use HasUuids;

    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_ANNIVERSARY = 'anniversary';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'celebration_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'event_type',
        'event_year',
        'source_date',
        'scheduled_date',
        'scheduled_for_at',
        'timezone',
        'milestone_years',
        'status',
        'custom_message',
        'post_id',
        'creative_file_id',
        'creative_url',
        'notification_id',
        'attempts',
        'max_attempts',
        'last_attempted_at',
        'published_at',
        'cancelled_at',
        'cancellation_reason',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'event_year' => 'integer',
        'milestone_years' => 'integer',
        'source_date' => 'date',
        'scheduled_date' => 'date',
        'scheduled_for_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id')->withTrashed();
    }

    public function creativeFile(): BelongsTo
    {
        return $this->belongsTo(FileModel::class, 'creative_file_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CelebrationPublishLog::class, 'celebration_schedule_id')
            ->orderByDesc('created_at');
    }

    public function dedupKey(): string
    {
        return "{$this->user_id}_{$this->event_type}_{$this->event_year}";
    }
}
