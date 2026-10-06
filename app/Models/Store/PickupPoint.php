<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PickupPoint extends Model
{
    use HasUuids;

    protected $table = 'pickup_points';

    protected $fillable = [
        'code',
        'name',
        'address',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'map_link',
        'timings',
        'contact_person',
        'contact_phone',
        'status',
    ];

    protected static function booted()
    {
        static::creating(function ($point) {
            if (empty($point->code)) {
                $point->code = 'HUB-' . strtoupper(Str::random(6));
            }
        });
    }

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
