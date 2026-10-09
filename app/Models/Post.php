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
    ];

    protected $appends = [
        'media_url',
        'media_type',
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
        });

        static::saving(function (self $post): void {
            if ($post->isDirty('status')) {
                if ($post->status === 'active') {
                    $post->active = true;
                } elseif (in_array($post->status, ['inactive', 'rejected', 'hidden'], true)) {
                    $post->active = false;
                }
            } elseif ($post->isDirty('active')) {
                $post->status = $post->active ? 'active' : 'inactive';
            }
        });
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->attributes['active'] ?? ($this->status === 'active'));
    }

    public function setIsActiveAttribute($value): void
    {
        $bool = (bool) $value;
        $this->attributes['active'] = $bool;
        $this->attributes['status'] = $bool ? 'active' : 'inactive';
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
        return $this->hasMany(PostComment::class, 'post_id');
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
        return $this->hasMany(PostLike::class, 'post_id');
    }

    public function likedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes', 'post_id', 'user_id')->withTimestamps();
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

    public function getMediaUrlAttribute(): ?string
    {
        $videoPath = $this->attributes['video_path'] ?? ($this->video_path ?? null);
        if (! empty($videoPath)) {
            $path = (string) $videoPath;

            return Str::startsWith($path, ['http://', 'https://']) ? $path : asset('storage/'.ltrim($path, '/'));
        }

        $mediaUrl = $this->attributes['media_url'] ?? ($this->media_url ?? null);
        if (! empty($mediaUrl)) {
            $url = (string) $mediaUrl;

            return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
        }

        $media = $this->media;
        if (! empty($media) && is_array($media)) {
            $first = reset($media);
            if (is_array($first)) {
                if (! empty($first['url'])) {
                    return (string) $first['url'];
                }
                if (! empty($first['id'])) {
                    return url('/api/v1/files/'.$first['id']);
                }
            }
        }

        if (! empty($this->image)) {
            $img = (string) $this->image;

            return Str::startsWith($img, ['http://', 'https://']) ? $img : url($img);
        }

        return null;
    }

    /**
     * Derive the primary media type ('image' | 'video' | null) from the media array or media attributes.
     * Inspects video_path, media_url file extensions, and the media array's 'type' field.
     */
    public function getMediaTypeAttribute(): ?string
    {
        $videoPath = $this->attributes['video_path'] ?? ($this->video_path ?? null);
        $mediaUrl = $this->attributes['media_url'] ?? ($this->media_url ?? null);
        $urlToCheck = $videoPath ?: ($mediaUrl ?: $this->getMediaUrlAttribute());

        if (! empty($videoPath) || preg_match('/\.(mp4|mov|webm|m4v)(\?.*)?$/i', (string) $urlToCheck)) {
            return 'video';
        }

        $media = $this->media;
        if (! empty($media) && is_array($media)) {
            $first = reset($media);
            if (is_array($first) && ! empty($first['type'])) {
                $type = strtolower((string) $first['type']);
                if (str_contains($type, 'video')) {
                    return 'video';
                }
            }
        }

        if (! empty($urlToCheck)) {
            return 'image';
        }

        return null;
    }

    public function isSystemCreativePost(): bool
    {
        return static::isSystemPostRow(
            $this->source_type,
            $this->post_type,
            $this->tags,
            $this->relationLoaded('user') ? $this->user?->email : null,
            $this->relationLoaded('user') ? $this->user?->display_name : null,
            $this->relationLoaded('author') ? $this->author?->email : null,
            $this->relationLoaded('author') ? $this->author?->display_name : null
        );
    }

    public static function isSystemPostRow(
        mixed $sourceType = null,
        mixed $postType = null,
        mixed $tags = null,
        ?string $authorEmail = null,
        ?string $authorDisplayName = null,
        ?string $secondAuthorEmail = null,
        ?string $secondAuthorDisplayName = null
    ): bool {
        $sourceType = strtolower(trim((string) $sourceType));
        $postType = strtolower(trim((string) $postType));

        $systemTypes = [
            'milestone_badge',
            'growth_honour',
            'life_impact',
            'life_impact_recognition',
            'member_introduction',
            'introduction',
            'peer_introduction',
            'birthday',
            'anniversary',
            'certification',
            'global_peer_certificate',
            'entrepreneur_certificate',
            'leadership_certificate',
            'recognition',
        ];

        if (in_array($sourceType, $systemTypes, true) || in_array($postType, $systemTypes, true)) {
            return true;
        }

        $decodedTags = [];
        if (is_array($tags)) {
            $decodedTags = $tags;
        } elseif (is_string($tags) && $tags !== '') {
            $decodedTags = json_decode($tags, true) ?? [];
        }

        $systemTags = [
            'milestone_honour',
            'growth_track',
            'growth_honour',
            'life_impact',
            'wedding_anniversary',
            'anniversary',
            'global_peer_certificate',
            'member_introduction',
            'birthday',
        ];

        foreach ($systemTags as $st) {
            if (in_array($st, $decodedTags, true)) {
                return true;
            }
        }

        $emails = array_filter([$authorEmail, $secondAuthorEmail]);
        foreach ($emails as $email) {
            if (strtolower(trim($email)) === 'info@peersglobal.com') {
                return true;
            }
        }

        $names = array_filter([$authorDisplayName, $secondAuthorDisplayName]);
        foreach ($names as $name) {
            $lowerName = strtolower(trim($name));
            if (str_contains($lowerName, 'genie') || str_contains($lowerName, 'peersglobal unity')) {
                return true;
            }
        }

        return false;
    }
}
