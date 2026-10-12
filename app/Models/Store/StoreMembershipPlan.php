<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreMembershipPlan extends Model
{
    use HasUuids;

    protected $table = 'membership_plans';

    protected $fillable = [
        'name',
        'duration_months',
        'price_coins',
        'active',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price_coins' => 'integer',
        'active' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
    ];

    protected $appends = [
        'price_coins',
        'duration_months',
    ];

    protected function priceCoins(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value, $attributes) {
                $val = (int) ($attributes['price_coins'] ?? ($attributes['coins'] ?? ($attributes['coins_price'] ?? 0)));
                if ($val <= 0 && isset($attributes['price']) && (float) $attributes['price'] > 0) {
                    $val = (int) ((float) $attributes['price']);
                }

                return $val > 0 ? $val : 500;
            },
            set: fn ($value) => ['price_coins' => (int) $value]
        );
    }

    protected function durationMonths(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value, $attributes) {
                $val = (int) ($attributes['duration_months'] ?? 0);
                if ($val <= 0 && isset($attributes['duration_days']) && (int) $attributes['duration_days'] > 0) {
                    $val = max(1, (int) round($attributes['duration_days'] / 30));
                }

                return $val > 0 ? $val : 12;
            },
            set: fn ($value) => ['duration_months' => (int) $value]
        );
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MembershipLedger::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)->orWhere('active', true)->orWhereNull('is_active');
        });
    }
}
