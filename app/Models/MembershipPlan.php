<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $table = 'membership_plans';

    protected $keyType = 'string';

    public $incrementing = false;

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
}
