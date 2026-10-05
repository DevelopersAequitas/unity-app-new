<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipLedger extends Model
{
    use HasUuids;

    protected $table = 'membership_ledger';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'plan_id',
        'order_id',
        'coins_paid',
        'start_date',
        'end_date',
        'status',
        'activated_at',
        'cancelled_at',
        'cancellation_reason',
        'created_at',
    ];

    protected $casts = [
        'coins_paid' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'activated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StoreMembershipPlan::class, 'plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
