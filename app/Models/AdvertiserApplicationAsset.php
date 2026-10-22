<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdvertiserApplicationAsset extends Model
{
    use HasFactory;
    use HasUuids;

    public const TYPE_LOGO = 'logo';
    public const TYPE_BANNER = 'banner';
    public const TYPE_COVER = 'cover_image';
    public const TYPE_CREATIVE = 'campaign_creative';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'advertiser_application_assets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_id',
        'asset_type',
        'original_filename',
        'storage_disk',
        'storage_key',
        'mime_type',
        'file_size_bytes',
        'width',
        'height',
        'alt_text',
        'display_order',
        'is_primary',
        'review_status',
        'review_notes',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'display_order' => 'integer',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'url',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdvertiserApplication::class, 'application_id');
    }

    public function getUrlAttribute(): ?string
    {
        if (empty($this->storage_key)) {
            return null;
        }

        if (Str::startsWith($this->storage_key, ['http://', 'https://'])) {
            return $this->storage_key;
        }

        $disk = $this->storage_disk ?: 'public';

        try {
            return Storage::disk($disk)->url($this->storage_key);
        } catch (\Throwable) {
            return Storage::disk('public')->url($this->storage_key);
        }
    }
}
