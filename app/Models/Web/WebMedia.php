<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebMedia extends Model
{
    use HasFactory;

    protected $table = 'web_media';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'title',
        'subtitle',
        'tag',
        'media_format',
        'duration',
        'youtube_url',
        'youtube_video_id',
        'spotify_url',
        'apple_podcast_url',
        'external_url',
        'cover_image',
        'guest_name',
        'guest_title',
        'guest_avatar',
        'show_notes',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'category',
        'alt_text',
        'dimensions',
        'metadata',
        'sort_order',
        'is_featured',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'show_notes' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebMedia $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (! empty($model->youtube_url) && empty($model->youtube_video_id)) {
                $model->youtube_video_id = self::extractYouTubeId($model->youtube_url);
            }
        });

        static::updating(function (WebMedia $model): void {
            if ($model->isDirty('youtube_url') && ! empty($model->youtube_url)) {
                $model->youtube_video_id = self::extractYouTubeId($model->youtube_url);
            }
        });
    }

    /**
     * Extract 11-character YouTube video ID.
     */
    public static function extractYouTubeId(string $url): ?string
    {
        $trimmed = trim($url);

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([\w-]{11})/', $trimmed, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^[\w-]{11}$/', $trimmed)) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Build YouTube thumbnail URL.
     */
    public static function buildYouTubeThumbnailUrl(string $videoId): string
    {
        return 'https://img.youtube.com/vi/'.$videoId.'/maxresdefault.jpg';
    }

    public function getUrlAttribute(): string
    {
        if (empty($this->file_path)) {
            return '';
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        return url(ltrim($this->file_path, '/'));
    }

    public function getCoverImageUrlAttribute(): string
    {
        if (! empty($this->cover_image)) {
            if (Str::startsWith($this->cover_image, ['http://', 'https://'])) {
                return $this->cover_image;
            }

            return url(ltrim($this->cover_image, '/'));
        }

        if (! empty($this->youtube_video_id)) {
            return self::buildYouTubeThumbnailUrl($this->youtube_video_id);
        }

        if (! empty($this->file_path) && $this->file_type === 'image') {
            return $this->url;
        }

        return url('images/podcast-placeholder.jpg');
    }

    public function getGuestAvatarUrlAttribute(): string
    {
        if (empty($this->guest_avatar)) {
            return url('images/avatar-placeholder.png');
        }

        if (Str::startsWith($this->guest_avatar, ['http://', 'https://'])) {
            return $this->guest_avatar;
        }

        return url(ltrim($this->guest_avatar, '/'));
    }
}
