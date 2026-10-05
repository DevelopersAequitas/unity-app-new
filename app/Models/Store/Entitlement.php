<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entitlement extends Model
{
    use HasUuids;

    protected $table = 'entitlements';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'source_type',
        'source_id',
        'feature_key',
        'start_date',
        'end_date',
        'status',
        'access_method',
        'content_reference',
        'is_active',
        'metadata',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
