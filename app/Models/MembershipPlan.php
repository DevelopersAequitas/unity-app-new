<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MembershipPlan extends Model
{
    protected static bool $monthlyPriceColumnChecked = false;

    public const CIRCLE_SLUGS = [
        'circle_peer',
        'multi_circle_peer',
    ];

    protected $table = 'membership_plans';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function ensureMonthlyPriceColumn(): void
    {
        if (self::$monthlyPriceColumnChecked) {
            return;
        }

        try {
            if (Schema::hasTable('membership_plans') && ! Schema::hasColumn('membership_plans', 'monthly_price')) {
                $driver = Schema::getConnection()->getDriverName();
                if ($driver === 'pgsql') {
                    Schema::getConnection()->statement('ALTER TABLE membership_plans ADD COLUMN IF NOT EXISTS monthly_price NUMERIC(10, 2) DEFAULT NULL');
                } else {
                    Schema::table('membership_plans', function (Blueprint $table): void {
                        $table->decimal('monthly_price', 10, 2)->nullable();
                    });
                }
            }
            self::$monthlyPriceColumnChecked = true;
        } catch (Throwable) {
            // Ignored if read-only connection
        }
    }

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
        'monthly_price',
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
        'monthly_price' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'duration_days' => 'integer',
        'duration_months' => 'integer',
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
        'coins' => 'integer',
    ];

    /**
     * Resolve the per-month display price for app plan cards (Hostinger-style pricing).
     * Does not affect actual billing price, GST, or invoice calculations.
     */
    public function resolveMonthlyPrice(): float
    {
        if ($this->is_free) {
            return 0.0;
        }

        if ($this->monthly_price !== null && (float) $this->monthly_price > 0) {
            return round((float) $this->monthly_price, 2);
        }

        $months = (int) ($this->duration_months ?? 0);
        if ($months > 1 && (float) $this->price > 0) {
            return round((float) $this->price / $months, 2);
        }

        return round((float) $this->price, 2);
    }

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
            $isPgsql = $q->getConnection()->getDriverName() === 'pgsql';
            if (Schema::hasTable('membership_plans') && Schema::hasColumn('membership_plans', 'plan_type')) {
                $q->where('plan_type', 'circle')
                    ->orWhere(function (Builder $inner) use ($isPgsql): void {
                        $inner->whereIn('slug', self::CIRCLE_SLUGS)
                            ->orWhere('slug', 'like', 'circle\_%')
                            ->orWhere('slug', 'like', 'circle-%');
                        if ($isPgsql) {
                            $inner->orWhere('slug', 'ilike', 'circle\_%')
                                ->orWhere('slug', 'ilike', 'circle-%');
                        }
                    });
            } else {
                $q->whereIn('slug', self::CIRCLE_SLUGS)
                    ->orWhere('slug', 'like', 'circle\_%')
                    ->orWhere('slug', 'like', 'circle-%');
                if ($isPgsql) {
                    $q->orWhere('slug', 'ilike', 'circle\_%')
                        ->orWhere('slug', 'ilike', 'circle-%');
                }
            }
        });
    }

    public function scopeMembershipOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $isPgsql = $q->getConnection()->getDriverName() === 'pgsql';
            if (Schema::hasTable('membership_plans') && Schema::hasColumn('membership_plans', 'plan_type')) {
                $q->where(function (Builder $inner): void {
                    $inner->where('plan_type', 'membership')
                        ->orWhereNull('plan_type');
                });
            }

            $q->whereNotIn('slug', self::CIRCLE_SLUGS)
                ->where('slug', 'not like', 'circle\_%')
                ->where('slug', 'not like', 'circle-%');
            if ($isPgsql) {
                $q->where('slug', 'not ilike', 'circle\_%')
                    ->where('slug', 'not ilike', 'circle-%');
            }
        });
    }
}
