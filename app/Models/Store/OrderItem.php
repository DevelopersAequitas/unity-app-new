<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasUuids;

    protected $table = 'order_items';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_snapshot',
        'quantity',
        'unit_coin_price',
        'total_coin_price',
        'status',
        'created_at',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'quantity' => 'integer',
        'unit_coin_price' => 'integer',
        'total_coin_price' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getProductNameAttribute(): string
    {
        return $this->attributes['product_name'] 
            ?? $this->attributes['name'] 
            ?? ($this->product_snapshot['name'] ?? ($this->product_snapshot['title'] ?? ($this->product?->name ?? 'Store Product')));
    }

    public function getSkuAttribute(): string
    {
        return $this->attributes['sku'] 
            ?? ($this->variant?->sku ?? ($this->product?->sku ?? ($this->product_snapshot['sku'] ?? '—')));
    }

    public function getUnitCoinsAttribute(): int
    {
        return (int) ($this->attributes['unit_price_coins'] ?? ($this->attributes['unit_coin_price'] ?? 0));
    }

    public function getTotalCoinsAttribute(): int
    {
        return (int) ($this->attributes['total_price_coins'] ?? ($this->attributes['total_coin_price'] ?? 0));
    }

    public function getVariantTitleAttribute(): ?string
    {
        return $this->variant?->name ?? ($this->variant_snapshot['name'] ?? ($this->variant_snapshot['title'] ?? null));
    }
}
