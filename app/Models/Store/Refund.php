<?php

namespace App\Models\Store;

use App\Models\CoinsLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuids;

    protected $table = 'refunds';

    protected $fillable = [
        'refund_no',
        'order_id',
        'return_id',
        'user_id',
        'refund_coins',
        'reason',
        'status',
        'processed_by',
        'processed_at',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'refund_coins' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function returnModel(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'return_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(CoinsLedger::class, 'ledger_transaction_id', 'transaction_id');
    }
}
