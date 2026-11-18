<?php

declare(strict_types=1);

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PickupPoint extends Model
{
    use HasUuids;

    protected $table = 'pickup_points';

    protected static ?bool $hasStatusColumn = null;

    protected static ?bool $hasAddressColumn = null;

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

    protected static function booted(): void
    {
        static::creating(function ($point): void {
            if (empty($point->code)) {
                $point->code = 'HUB-'.strtoupper(Str::random(6));
            }
        });

        static::saving(function (self $point): void {
            if (static::$hasAddressColumn === null) {
                try {
                    static::$hasAddressColumn = Schema::hasTable('pickup_points') && Schema::hasColumn('pickup_points', 'address');
                } catch (\Throwable) {
                    static::$hasAddressColumn = false;
                }
            }

            if (static::$hasAddressColumn) {
                if (empty($point->attributes['address'])) {
                    $parts = array_filter([
                        $point->address_line1 ?? null,
                        $point->address_line2 ?? null,
                        $point->city ?? null,
                        $point->state ?? null,
                        $point->pincode ?? null,
                    ]);
                    $point->attributes['address'] = implode(', ', $parts);
                }
            } else {
                unset($point->attributes['address']);
            }
        });
    }

    public function getAddressAttribute(): string
    {
        if (! empty($this->attributes['address'])) {
            return (string) $this->attributes['address'];
        }

        $parts = array_filter([
            $this->address_line1 ?? null,
            $this->address_line2 ?? null,
            $this->city ?? null,
            $this->state ?? null,
            $this->pincode ?? null,
        ]);

        return implode(', ', $parts);
    }

    public function getIsActiveAttribute(): bool
    {
        if (isset($this->attributes['status'])) {
            return in_array(strtoupper((string) $this->attributes['status']), ['ACTIVE', '1', 'TRUE'], true);
        }

        if (isset($this->attributes['is_active'])) {
            return (bool) $this->attributes['is_active'];
        }

        return true;
    }

    public function scopeActive($query)
    {
        try {
            if (static::$hasStatusColumn === null) {
                static::$hasStatusColumn = Schema::hasTable('pickup_points') && Schema::hasColumn('pickup_points', 'status');
            }

            if (static::$hasStatusColumn) {
                return $query->where(function ($q): void {
                    $q->where('status', 'ACTIVE')
                        ->orWhere('status', '1')
                        ->orWhereRaw("COALESCE(status, 'ACTIVE') = 'ACTIVE'");
                });
            }

            if (Schema::hasTable('pickup_points') && Schema::hasColumn('pickup_points', 'is_active')) {
                return $query->where('is_active', true);
            }
        } catch (\Throwable) {
            // Silently return query without filter if schema inspection fails
        }

        return $query;
    }
}
