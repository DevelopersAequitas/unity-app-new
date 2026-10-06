<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PickupPoint extends Model
{
    use HasUuids;

    protected $table = 'pickup_points';

    protected $fillable = [
        'name',
        'address',
        'map_link',
        'timings',
        'contact_person',
        'contact_phone',
        'status',
    ];

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'ACTIVE' || $this->status === '1';
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'ACTIVE')
                ->orWhere('status', '1')
                ->orWhereRaw("COALESCE(status, 'ACTIVE') = 'ACTIVE'");
        });
    }
}
