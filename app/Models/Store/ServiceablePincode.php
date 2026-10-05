<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
        'delivery_days' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('serviceable', true)
              ->orWhere('serviceable', 1);
        });
    }
}
