<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutQuoteItem extends Model
{
    use HasUuids;

    protected $table = 'checkout_quote_items';

    public $timestamps = false;

    protected $fillable = [
        'quote_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_coins',
        'total_price_coins',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_coins' => 'integer',
        'total_price_coins' => 'integer',
        'created_at' => 'datetime',
    ];

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
