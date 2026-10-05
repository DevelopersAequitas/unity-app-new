<?php

namespace App\Models\Store;

use App\Models\CoinsLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAdjustmentRequest extends Model
{
    use HasUuids;

    protected $table = 'wallet_adjustment_requests';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'requested_by',
        'approved_by',
        'adjustment_type',
        'coins',
        'bucket',
        'reason',
        'status',
        'ledger_transaction_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'coins' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(CoinsLedger::class, 'ledger_transaction_id', 'transaction_id');
    }
}
