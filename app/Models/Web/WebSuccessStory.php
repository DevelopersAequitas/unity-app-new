<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebSuccessStory extends Model
{
    use HasFactory;

    protected $table = 'web_success_stories';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'person_name',
        'designation',
        'company',
        'story_title',
        'quote',
        'youtube_url',
        'youtube_video_id',
        'custom_cover_image',
        'youtube_thumbnail_url',
        'sort_order',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebSuccessStory $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Extract 11-character YouTube video ID from various YouTube URL formats.
     */
    public static function extractYouTubeId(string $url): ?string
    {
        $trimmed = trim($url);

        // Pattern matching standard watch?v=, embed, shorts, youtu.be, etc.
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([\w-]{11})/', $trimmed, $matches)) {
            return $matches[1];
        }

        // Handle case where user provided raw 11-char ID directly
        if (preg_match('/^[\w-]{11}$/', $trimmed)) {
            return $trimmed;
        }

        return null;
    }

    /**
     * Generate high-resolution YouTube thumbnail URL.
     */
    public static function buildYouTubeThumbnailUrl(string $videoId): string
    {
        return 'https://img.youtube.com/vi/'.$videoId.'/maxresdefault.jpg';
    }

    /**
     * Get the active cover image URL (custom uploaded photo if available, fallback to YouTube thumbnail).
     */
    public function getCoverImageUrlAttribute(): string
    {
        if (! empty($this->custom_cover_image)) {
            return asset($this->custom_cover_image);
        }

        if (! empty($this->youtube_thumbnail_url)) {
            return $this->youtube_thumbnail_url;
        }

        if (! empty($this->youtube_video_id)) {
            return self::buildYouTubeThumbnailUrl($this->youtube_video_id);
        }

        return asset('images/avatar-placeholder.png');
    }

    /**
     * Determine if a custom cover image was uploaded.
     */
    public function getHasCustomCoverAttribute(): bool
    {
        return ! empty($this->custom_cover_image);
    }

    /**
     * YouTube privacy-enhanced embed URL for modals / iframe players.
     */
    public function getYoutubeEmbedUrlAttribute(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.($this->youtube_video_id ?? '').'?autoplay=1&rel=0';
    }
}
