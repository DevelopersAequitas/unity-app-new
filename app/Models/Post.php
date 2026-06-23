<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'circle_id',
        'content_text',
        'media',
        'tags',
        'visibility',
        'moderation_status',
        'sponsored',
        'is_deleted',
        'active',
        'source_type',
        'source_id',
        'source_event',
    ];

    protected $casts = [
        'media' => 'array',
        'tags' => 'array',
        'sponsored' => 'boolean',
        'is_deleted' => 'boolean',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (empty($post->id)) {
                $post->id = Str::uuid()->toString();
            }
        });

        static::updated(function (self $post): void {
            if (! $post->wasChanged('moderation_status')) {
                return;
            }

            $previousStatus = strtolower((string) $post->getOriginal('moderation_status'));
            $currentStatus = strtolower((string) $post->moderation_status);

            if ($previousStatus === $currentStatus || ! in_array($currentStatus, ['approved', 'published', 'visible'], true)) {
                return;
            }

            try {
                app(NotificationService::class)->sendPostPublishedNotification($post->fresh() ?: $post);
            } catch (\Throwable $throwable) {
                Log::warning('Post approval notification failed', [
                    'post_id' => (string) $post->id,
                    'previous_status' => $previousStatus,
                    'current_status' => $currentStatus,
                    'error' => $throwable->getMessage(),
                ]);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function collaborationPost(): BelongsTo
    {
        return $this->belongsTo(CollaborationPost::class, 'source_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class, 'post_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_saves', 'post_id', 'user_id');
    }
}
