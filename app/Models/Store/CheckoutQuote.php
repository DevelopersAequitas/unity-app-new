<?php

declare(strict_types=1);

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckoutQuote extends Model
{
    use HasUuids;

    protected $table = 'checkout_quotes';

    protected static ?array $existingColumns = null;

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

    protected static function booted(): void
    {
        static::saving(function (self $quote): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('checkout_quotes')) {
                        static::$existingColumns = Schema::getColumnListing('checkout_quotes');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = null;
                }
            }

            if (is_array(static::$existingColumns) && ! empty(static::$existingColumns)) {
                foreach (array_keys($quote->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($quote->attributes[$attr]);
                    }
                }
            }
        });
    }

    public function getQuoteNoAttribute(): ?string
    {
        return $this->attributes['quote_no'] ?? ('QUO-'.strtoupper(substr((string) ($this->id ?? Str::random(8)), 0, 8)));
    }

    public function getStatusAttribute(): string
    {
        return isset($this->attributes['status']) ? strtoupper((string) $this->attributes['status']) : 'ACTIVE';
    }

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
