<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleMemberSubscription extends Model
{
    use HasFactory;

    protected $table = 'circle_member_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'duration_months',
        'price',
        'currency',
        'status',
        'joined_at',
        'starts_at',
        'expires_at',
        'zoho_hostedpage_id',
        'zoho_subscription_id',
        'zoho_payment_id',
        'payload',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price' => 'decimal:2',
        'joined_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (CircleMemberSubscription $subscription): void {
            if (! $subscription->id) {
                $subscription->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
