<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdvertiserSubscription extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAYMENT_FAILED = 'payment_failed';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'advertiser_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_id',
        'user_id',
        'brand_partner_id',
        'package_id',
        'package_name_snapshot',
        'duration_months',
        'amount',
        'currency',
        'status',
        'starts_at',
        'expires_at',
        'activated_at',
        'cancelled_at',
        'auto_renew',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'amount' => 'float',
        'auto_renew' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdvertiserApplication::class, 'application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(AdvertiserPackage::class, 'package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdvertiserPayment::class, 'subscription_id')->orderByDesc('created_at');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(AdvertiserPayment::class, 'subscription_id')->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE &&
            ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
