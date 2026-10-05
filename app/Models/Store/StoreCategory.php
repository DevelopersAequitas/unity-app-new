<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCategory extends Model
{
    use HasUuids;

    protected $table = 'store_categories';

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'image_url',
        'description',
        'sort_order',
        'status',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
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
