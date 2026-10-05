<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class StoreBanner extends Model
{
    use HasUuids;

    protected $table = 'store_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'deep_link',
        'link_type',
        'link_value',
        'action_type',
        'action_value',
        'sort_order',
        'is_active',
        'status',
        'start_at',
        'end_at',
        'starts_at',
        'ends_at',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function scopeActive($query)
    {
        if (Schema::hasColumn('store_banners', 'status')) {
            $query->where(function ($q) {
                $q->where('status', 'ACTIVE')
                    ->orWhere('status', '1');
            });
        } elseif (Schema::hasColumn('store_banners', 'is_active')) {
            $query->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('is_active', 1);
            });
        }

        if (Schema::hasColumn('store_banners', 'start_at')) {
            $query->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            });
        } elseif (Schema::hasColumn('store_banners', 'starts_at')) {
            $query->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
        }

        if (Schema::hasColumn('store_banners', 'end_at')) {
            $query->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
        } elseif (Schema::hasColumn('store_banners', 'ends_at')) {
            $query->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
        }

        return $query;
    }

    public function getIsActiveAttribute($value)
    {
        if ($value !== null) {
            return (bool) $value;
        }

        if (isset($this->attributes['status'])) {
            return strtoupper((string) $this->attributes['status']) === 'ACTIVE' || (string) $this->attributes['status'] === '1';
        }

        return true;
    }
}
