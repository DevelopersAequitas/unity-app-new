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
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $cleanPath = ltrim($value, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        }

        return asset('storage/'.ltrim($cleanPath, '/'));
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
