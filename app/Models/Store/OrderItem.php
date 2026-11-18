<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OrderItem extends Model
{
    use HasUuids;

    protected $table = 'order_items';

    public $timestamps = false;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_snapshot',
        'quantity',
        'unit_coin_price',
        'unit_price_coins',
        'total_coin_price',
        'total_price_coins',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'quantity' => 'integer',
        'unit_coin_price' => 'integer',
        'unit_price_coins' => 'integer',
        'total_coin_price' => 'integer',
        'total_price_coins' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('order_items')) {
                        static::$existingColumns = Schema::getColumnListing('order_items');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = null;
                }
            }

            $unitPrice = $item->attributes['unit_price_coins'] ?? ($item->attributes['unit_coin_price'] ?? 0);
            $totalPrice = $item->attributes['total_price_coins'] ?? ($item->attributes['total_coin_price'] ?? 0);

            if (is_array(static::$existingColumns) && ! empty(static::$existingColumns)) {
                if (in_array('unit_coin_price', static::$existingColumns, true)) {
                    $item->attributes['unit_coin_price'] = $unitPrice;
                }
                if (in_array('unit_price_coins', static::$existingColumns, true)) {
                    $item->attributes['unit_price_coins'] = $unitPrice;
                }
                if (in_array('total_coin_price', static::$existingColumns, true)) {
                    $item->attributes['total_coin_price'] = $totalPrice;
                }
                if (in_array('total_price_coins', static::$existingColumns, true)) {
                    $item->attributes['total_price_coins'] = $totalPrice;
                }

                foreach (array_keys($item->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($item->attributes[$attr]);
                    }
                }
            }
        });
    }

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

    public function getIsCustomizedAttribute(): bool
    {
        return (bool) (
            $this->attributes['is_customized']
            ?? ($this->attributes['customised']
                ?? ($this->product?->customised
                    ?? ($this->product?->is_customized
                        ?? ($this->product_snapshot['customised']
                            ?? ($this->product_snapshot['is_customized'] ?? false)))))
        );
    }
}
