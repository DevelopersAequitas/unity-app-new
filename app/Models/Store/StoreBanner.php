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
        'sort_order',
        'status',
        'is_active',
        'start_at',
        'end_at',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function scopeActive($query)
    {
        // Support both old status column and new is_active column
        $query->where(function ($q) {
            $q->where('status', 'ACTIVE')
              ->orWhere('status', '1')
              ->orWhereRaw("COALESCE(status, 'ACTIVE') = 'ACTIVE'");
        });

        // Time window check
        return $query->where(function ($q) {
            $q->whereNull('start_at')->orWhere('start_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('end_at')->orWhere('end_at', '>=', now());
        });
    }
}
