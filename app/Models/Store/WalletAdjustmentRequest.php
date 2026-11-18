<?php

namespace App\Models\Store;

use App\Models\CoinsLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class WalletAdjustmentRequest extends Model
{
    use HasUuids;

    protected $table = 'wallet_adjustment_requests';

    public $timestamps = false;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'user_id',
        'requested_by',
        'approved_by',
        'adjustment_type',
        'action',
        'coins',
        'amount',
        'bucket',
        'coin_type',
        'reason',
        'status',
        'ledger_transaction_id',
        'requested_at',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'coins' => 'integer',
        'amount' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $request): bool {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('wallet_adjustment_requests')) {
                        static::$existingColumns = Schema::getColumnListing('wallet_adjustment_requests');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = [];
                }
            }

            if (empty(static::$existingColumns)) {
                return false;
            }

            // Sync coins <-> amount
            $coins = $request->attributes['coins'] ?? ($request->attributes['amount'] ?? 0);
            if (in_array('coins', static::$existingColumns, true)) {
                $request->attributes['coins'] = (int) $coins;
            }
            if (in_array('amount', static::$existingColumns, true)) {
                $request->attributes['amount'] = (int) $coins;
            }

            // Sync bucket <-> coin_type
            $bucket = strtoupper((string) ($request->attributes['bucket'] ?? ($request->attributes['coin_type'] ?? 'EARNED')));
            if (in_array('bucket', static::$existingColumns, true)) {
                $request->attributes['bucket'] = $bucket;
            }
            if (in_array('coin_type', static::$existingColumns, true)) {
                $request->attributes['coin_type'] = strtolower($bucket);
            }

            // Sync adjustment_type <-> action
            $action = $request->attributes['action'] ?? null;
            $type = $request->attributes['adjustment_type'] ?? null;
            if ($type) {
                $type = str_contains(strtoupper((string) $type), 'CREDIT') ? 'CREDIT' : 'DEBIT';
            } elseif ($action) {
                $type = (strtolower((string) $action) === 'credit') ? 'CREDIT' : 'DEBIT';
            } else {
                $type = 'CREDIT';
            }
            $action = ($type === 'CREDIT') ? 'credit' : 'debit';

            if (in_array('adjustment_type', static::$existingColumns, true)) {
                $request->attributes['adjustment_type'] = $type;
            }
            if (in_array('action', static::$existingColumns, true)) {
                $request->attributes['action'] = $action;
            }

            foreach (array_keys($request->attributes) as $attr) {
                if (! in_array($attr, static::$existingColumns, true)) {
                    unset($request->attributes[$attr]);
                }
            }

            return true;
        });
    }

    public function getAmountAttribute(): int
    {
        return (int) ($this->attributes['coins'] ?? ($this->attributes['amount'] ?? 0));
    }

    public function getActionAttribute(): string
    {
        if (isset($this->attributes['action'])) {
            return (string) $this->attributes['action'];
        }

        $type = (string) ($this->attributes['adjustment_type'] ?? 'ADJUST_CREDIT');

        return str_contains(strtoupper($type), 'CREDIT') ? 'credit' : 'debit';
    }

    public function getCoinTypeAttribute(): string
    {
        if (isset($this->attributes['coin_type'])) {
            return (string) $this->attributes['coin_type'];
        }

        return strtolower((string) ($this->attributes['bucket'] ?? 'earned'));
    }

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
