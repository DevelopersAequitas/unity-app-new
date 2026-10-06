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

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(MembershipLedger::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
