<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MembershipPlan extends Model
{
    public const CIRCLE_SLUGS = [
        'circle_peer',
        'multi_circle_peer',
    ];

    protected $table = 'membership_plans';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
        'name',
        'slug',
        'price',
        'duration_days',
        'duration_months',
        'gst_percent',
        'is_active',
        'is_free',
        'sort_order',
        'coins',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'duration_days' => 'integer',
        'duration_months' => 'integer',
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
        'coins' => 'integer',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'membership_plan_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'membership_plan_id');
    }

    public function isCirclePlan(): bool
    {
        $slug = (string) $this->slug;

        return in_array($slug, self::CIRCLE_SLUGS, true)
            || str_starts_with($slug, 'circle_')
            || str_starts_with($slug, 'circle-');
    }

    public function scopeCircleOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereIn('slug', self::CIRCLE_SLUGS)
                ->orWhere('slug', 'like', 'circle\_%')
                ->orWhere('slug', 'like', 'circle-%');
        });
    }

    public function scopeMembershipOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNotIn('slug', self::CIRCLE_SLUGS)
                ->where('slug', 'not like', 'circle\_%')
                ->where('slug', 'not like', 'circle-%');
        });
    }
}
