<?php

namespace App\Models\Store;

use App\Models\CoinsLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $table = 'orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'quote_id',
        'status',
        'total_coins',
        'coins_from_earned',
        'coins_from_bonus',
        'delivery_type',
        'shipping_address',
        'pickup_point_id',
        'ledger_transaction_id',
        'policy_version_id',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'pickup_ready_at',
        'pickup_expires_at',
        'pickup_code_hash',
        'notes',
        'courier_name',
        'tracking_number',
        'delivery_person_name',
        'delivery_person_phone',
        'slip_url',
    ];

    protected $casts = [
        'total_coins' => 'integer',
        'coins_from_earned' => 'integer',
        'coins_from_bonus' => 'integer',
        'shipping_address' => 'array',
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pickup_ready_at' => 'datetime',
        'pickup_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'quote_id');
    }

    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class, 'pickup_point_id');
    }

    public function policyPage(): BelongsTo
    {
        return $this->belongsTo(PolicyPage::class, 'policy_version_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(CoinsLedger::class, 'ledger_transaction_id', 'transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'order_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at', 'desc');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'order_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StoreReturn::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class, 'order_id');
    }

    public function getOrderNumberAttribute(): ?string
    {
        return $this->order_no;
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function getResolvedAddressAttribute(): array
    {
        if (!empty($this->shipping_address) && is_array($this->shipping_address)) {
            return $this->shipping_address;
        }

        if (!empty($this->address_snapshot) && is_array($this->address_snapshot)) {
            return $this->address_snapshot;
        }

        if ($this->relationLoaded('address') && $this->address) {
            return $this->address->toArray();
        }

        if ($this->address_id) {
            $addr = Address::find($this->address_id);
            if ($addr) {
                return $addr->toArray();
            }
        }

        return [];
    }

    public function getShippingAddressLine1Attribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['line1'] ?? $addr['address_line1'] ?? null;
    }

    public function getShippingAddressLine2Attribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['line2'] ?? $addr['address_line2'] ?? null;
    }

    public function getShippingLandmarkAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['landmark'] ?? null;
    }

    public function getShippingCityAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['city'] ?? null;
    }

    public function getShippingStateAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['state'] ?? null;
    }

    public function getShippingPincodeAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['pincode'] ?? $addr['postal_code'] ?? null;
    }

    public function getShippingCountryAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['country'] ?? 'India';
    }

    public function getShippingPhoneAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['phone'] ?? $addr['phone_number'] ?? $this->user?->phone_number;
    }

    public function getShippingRecipientNameAttribute(): ?string
    {
        $addr = $this->resolved_address;
        return $addr['name'] ?? $addr['full_name'] ?? $this->user?->name;
    }
}
