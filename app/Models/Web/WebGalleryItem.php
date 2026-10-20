<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebGalleryItem extends Model
{
    use HasFactory;

    protected $table = 'web_gallery_items';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'gallery_id',
        'title',
        'category',
        'city',
        'circle',
        'year',
        'image_path',
        'media_url',
        'media_type',
        'thumbnail_url',
        'caption',
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
        static::creating(function (WebGalleryItem $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<WebGallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(WebGallery::class, 'gallery_id');
    }

    public function getImageUrlAttribute(): string
    {
        $path = $this->image_path ?: $this->media_url;

        if (empty($path)) {
            return url('images/gallery-placeholder.jpg');
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }
}
