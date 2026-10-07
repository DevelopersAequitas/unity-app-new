<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasUuids;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'coin_price',
        'price_coins',
        'stock_qty',
        'stock_quantity',
        'reserved_quantity',
        'low_stock_threshold',
        'allow_backorder',
        'is_active',
        'status',
    ];

    protected $casts = [
        'attributes' => 'array',
        'coin_price' => 'integer',
        'price_coins' => 'integer',
        'stock_qty' => 'integer',
        'stock_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_backorder' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected function coinPrice(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value, $attributes) => (int) ($attributes['coin_price'] ?? ($attributes['price_coins'] ?? 0)),
            set: fn ($value) => [
                'coin_price' => (int) $value,
                'price_coins' => (int) $value,
            ]
        );
    }

    protected function priceCoins(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value, $attributes) => (int) ($attributes['price_coins'] ?? ($attributes['coin_price'] ?? 0)),
            set: fn ($value) => [
                'price_coins' => (int) $value,
                'coin_price' => (int) $value,
            ]
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }

    public function getIsActiveAttribute(): bool
    {
        return ($this->attributes['is_active'] ?? null) !== null
            ? (bool) $this->attributes['is_active']
            : ($this->status === 'ACTIVE');
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
