<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckoutQuote extends Model
{
    use HasUuids;

    protected $table = 'checkout_quotes';

    protected $fillable = [
        'user_id',
        'quote_no',
        'cart_id',
        'total_coins',
        'coins_from_earned',
        'coins_from_bonus',
        'delivery_type',
        'address_id',
        'pickup_point_id',
        'subtotal_coins',
        'delivery_coins',
        'balance_before',
        'balance_after',
        'shortfall_coins',
        'delivery_minimum_met',
        'serviceable',
        'stock_ok',
        'otp_required',
        'snapshot',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'total_coins' => 'integer',
        'coins_from_earned' => 'integer',
        'coins_from_bonus' => 'integer',
        'subtotal_coins' => 'integer',
        'delivery_coins' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'shortfall_coins' => 'integer',
        'delivery_minimum_met' => 'boolean',
        'serviceable' => 'boolean',
        'stock_ok' => 'boolean',
        'otp_required' => 'boolean',
        'snapshot' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckoutQuoteItem::class, 'quote_id');
    }
}
