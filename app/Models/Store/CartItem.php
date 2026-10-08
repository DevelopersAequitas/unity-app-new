<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasUuids;

    protected $table = 'cart_items';

    const CREATED_AT = 'added_at';

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'price_seen_coins',
        'unit_coin_price',
        'added_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_seen_coins' => 'integer',
        'added_at' => 'datetime',
    ];

    protected $appends = [
        'unit_coin_price',
    ];

    protected function unitCoinPrice(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if ($this->relationLoaded('variant') && $this->variant) {
                    $vPrice = (int) ($this->variant->coin_price ?? ($this->variant->price_coins ?? 0));
                    if ($vPrice > 0) {
                        return $vPrice;
                    }
                }
                if ($this->relationLoaded('product') && $this->product) {
                    $pPrice = (int) ($this->product->coin_price ?? ($this->product->price_coins ?? 0));
                    if ($pPrice > 0) {
                        return $pPrice;
                    }
                }

                return (int) ($attributes['price_seen_coins'] ?? ($attributes['unit_coin_price'] ?? 0));
            },
            set: fn ($value) => ['price_seen_coins' => (int) $value]
        );
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
