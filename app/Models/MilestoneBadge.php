<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MilestoneBadge extends Model
{
    use HasFactory;

    public const TYPE_LIFE_IMPACT = 'life_impact';

    public const TYPE_COINS = 'coins';

    public const TYPE_MEMBER_INTRODUCTION = 'member_introduction';

    public const ALLOWED_TYPES = [
        self::TYPE_LIFE_IMPACT,
        self::TYPE_COINS,
        self::TYPE_MEMBER_INTRODUCTION,
    ];

    protected $table = 'milestone_badges';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'type',
        'title',
        'description',
        'required_count',
        'badge_image_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'required_count' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getBadgeImageUrlAttribute(?string $value): ?string
    {
        $title = trim((string) ($this->attributes['title'] ?? ''));
        $type = (string) ($this->attributes['type'] ?? '');

        // 1. Direct local file resolution by type and title (ensures both local and QA/prod always display the badge medal)
        if ($type === self::TYPE_LIFE_IMPACT && $title !== '') {
            return asset('images/life_impact_badges/'.rawurlencode($title).'.png?v=2');
        }

        if ($type === self::TYPE_MEMBER_INTRODUCTION && $title !== '') {
            return asset('images/member_introduce_badges/'.rawurlencode($title).'.png?v=2');
        }

        if (empty($value)) {
            return null;
        }

        $cleanPath = ltrim(preg_replace('#^(storage/|public/)+#i', '', $value), '/');
        if (str_starts_with($cleanPath, 'images/')) {
            return asset($cleanPath.'?v=2');
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            // If remote peersunity.com points to static images, resolve from current host asset
            if (str_contains($value, 'peersunity.com/images/')) {
                $sub = substr($value, strpos($value, '/images/') + 1);

                return asset($sub.'?v=2');
            }

            if (str_contains($value, 'peersunity.com/api/v1/files/')) {
                $filePath = substr($value, strpos($value, '/api/v1/files/') + 14);

                return url('/api/v1/files/'.ltrim($filePath, '/'));
            }

            if (str_contains($value, '/api/v1/files/')) {
                return $value;
            }
            if (str_contains($value, '/storage/')) {
                $path = substr($value, strpos($value, '/storage/') + 9);

                return url('/api/v1/files/'.ltrim($path, '/'));
            }

            return $value;
        }

        return url('/api/v1/files/'.ltrim($cleanPath, '/'));
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserMilestoneBadge::class, 'badge_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('required_count', 'asc');
    }
}
