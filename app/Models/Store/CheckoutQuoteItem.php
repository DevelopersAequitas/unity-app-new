<?php

declare(strict_types=1);

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CheckoutQuoteItem extends Model
{
    use HasUuids;

    protected $table = 'checkout_quote_items';

    public $timestamps = false;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'quote_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_coins',
        'unit_coin_price',
        'total_price_coins',
        'total_coin_price',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_coins' => 'integer',
        'unit_coin_price' => 'integer',
        'total_price_coins' => 'integer',
        'total_coin_price' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('checkout_quote_items')) {
                        static::$existingColumns = Schema::getColumnListing('checkout_quote_items');
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

    public function getUnitPriceCoinsAttribute(): int
    {
        return (int) ($this->attributes['unit_price_coins'] ?? ($this->attributes['unit_coin_price'] ?? 0));
    }

    public function getTotalPriceCoinsAttribute(): int
    {
        return (int) ($this->attributes['total_price_coins'] ?? ($this->attributes['total_coin_price'] ?? 0));
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
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
