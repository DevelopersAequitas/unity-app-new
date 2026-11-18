<?php

declare(strict_types=1);

namespace App\Models\Store;

use App\Models\CoinsLedger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OrderPayment extends Model
{
    use HasUuids;

    protected $table = 'order_payments';

    public $timestamps = false;

    protected static ?array $existingColumns = null;

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

    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('order_payments')) {
                        static::$existingColumns = Schema::getColumnListing('order_payments');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = null;
                }
            }

            if (is_array(static::$existingColumns) && ! empty(static::$existingColumns)) {
                foreach (array_keys($payment->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($payment->attributes[$attr]);
                    }
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(CoinsLedger::class, 'ledger_transaction_id', 'transaction_id');
    }
}
