<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ServiceablePincode extends Model
{
    use HasUuids;

    protected $table = 'serviceable_pincodes';

    protected $fillable = [
        'pincode',
        'city',
        'state',
        'serviceable',
        'is_active',
        'delivery_days',
        'courier_code',
        'last_checked_at',
    ];

    protected $casts = [
        'serviceable' => 'boolean',
        'is_active' => 'boolean',
        'delivery_days' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)
                ->orWhere('is_active', 1)
                ->orWhere('serviceable', true)
                ->orWhere('serviceable', 1);
        });
    }

    public function getIsActiveAttribute($value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }
        return (bool) ($this->attributes['serviceable'] ?? true);
    }
}
