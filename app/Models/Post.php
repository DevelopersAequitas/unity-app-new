<?php

namespace App\Models;

use App\Models\Ask\Ask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'is_active',
        'source_type',
        'source_id',
        'source_event',
        'post_type',
        'template_id',
        'title',
        'description',
        'image',
        'status',
    ];

    protected $casts = [
        'media' => 'array',
        'tags' => 'array',
        'sponsored' => 'boolean',
        'is_deleted' => 'boolean',
        'active' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (empty($post->id)) {
                $post->id = Str::uuid()->toString();
            }
            if (empty($post->status)) {
                $post->status = 'active';
            }
            if ($post->active === null) {
                $post->active = $post->status === 'active';
            }
            if ($post->is_active === null) {
                $post->is_active = $post->status === 'active';
            }
        });

        static::saving(function (self $post): void {
            if ($post->isDirty('status')) {
                if ($post->status === 'active') {
                    $post->active = true;
                    $post->is_active = true;
                } elseif (in_array($post->status, ['inactive', 'rejected', 'hidden'], true)) {
                    $post->active = false;
                    $post->is_active = false;
                }
            } elseif ($post->isDirty('active')) {
                $post->is_active = (bool) $post->active;
                $post->status = $post->active ? 'active' : 'inactive';
            } elseif ($post->isDirty('is_active')) {
                $post->active = (bool) $post->is_active;
                $post->status = $post->is_active ? 'active' : 'inactive';
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('posts.status', 'active')
            ->where('posts.is_deleted', false);
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

    public function ask(): BelongsTo
    {
        return $this->belongsTo(Ask::class, 'source_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function postMentions(): HasMany
    {
        return $this->hasMany(PostMention::class, 'post_id');
    }

    public function mentionedPeers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_mentions', 'post_id', 'peer_id')->withTimestamps();
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

    public function getMediaAttribute($value)
    {
        if (! $value) {
            return [];
        }
        $media = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($media)) {
            return [];
        }

        return array_map(function ($item) {
            if (isset($item['id'])) {
                $item['url'] = url('/api/v1/files/'.$item['id']);
            }

            return $item;
        }, $media);
    }

    public function getImageAttribute($value)
    {
        if (! $value) {
            return null;
        }
        $path = parse_url($value, PHP_URL_PATH);
        $id = basename($path);
        if (Str::isUuid($id)) {
            return url('/api/v1/files/'.$id);
        }

        return $value;
    }

    /**
     * Derive the primary media type ('image' | 'video' | null) from the media array.
     * Inspects the first media item's 'type' field and normalises it.
     */
    public function getMediaTypeAttribute(): ?string
    {
        $media = $this->media;
        if (empty($media) || ! is_array($media)) {
            return null;
        }

        $first = reset($media);
        if (! is_array($first)) {
            return null;
        }

        $type = strtolower((string) ($first['type'] ?? ''));

        if (str_contains($type, 'video')) {
            return 'video';
        }

        if ($type !== '') {
            return 'image';
        }

        return null;
    }
}
