<?php

namespace App\Models\Store;

use App\Models\CoinsLedger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use HasUuids;

    protected $table = 'order_payments';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'bucket',
        'coins',
        'ledger_transaction_id',
        'created_at',
    ];

    protected $casts = [
        'coins' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(CoinsLedger::class, 'ledger_transaction_id', 'transaction_id');
    }
}
