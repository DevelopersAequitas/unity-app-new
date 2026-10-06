<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreReturn extends Model
{
    use HasUuids;

    protected $table = 'returns';

    protected $fillable = [
        'return_no',
        'order_id',
        'user_id',
        'reason_code',
        'reason_detail',
        'status',
        'pickup_scheduled_at',
        'pickup_completed_at',
        'received_at',
        'quality_check_passed',
        'quality_check_notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'quality_check_passed' => 'boolean',
        'pickup_scheduled_at' => 'datetime',
        'pickup_completed_at' => 'datetime',
        'received_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReturnPhoto::class, 'return_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class, 'return_id');
    }
}
