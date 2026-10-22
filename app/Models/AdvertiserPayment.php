<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertiserPayment extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_CREATED = 'created';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUND_PENDING = 'refund_pending';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'advertiser_payments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'subscription_id',
        'provider',
        'provider_order_id',
        'provider_payment_id',
        'provider_signature',
        'amount',
        'currency',
        'status',
        'failure_code',
        'failure_description',
        'refund_id',
        'refund_amount',
        'verified_at',
        'captured_at',
        'refunded_at',
        'idempotency_key',
        'provider_event_id',
        'provider_payload',
    ];

    protected $casts = [
        'amount' => 'float',
        'refund_amount' => 'float',
        'provider_payload' => 'array',
        'verified_at' => 'datetime',
        'captured_at' => 'datetime',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AdvertiserSubscription::class, 'subscription_id');
    }
}
