<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Receipt extends Model
{
    use HasUuids;

    protected $table = 'receipts';

    const UPDATED_AT = null;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'receipt_no',
        'order_id',
        'user_id',
        'coins_paid',
        'receipt_data',
        'policy_version_id',
        'issued_at',
        'created_at',
    ];

    protected $casts = [
        'coins_paid' => 'integer',
        'receipt_data' => 'array',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $receipt): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('receipts')) {
                        static::$existingColumns = Schema::getColumnListing('receipts');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = null;
                }
            }

            if (is_array(static::$existingColumns) && ! empty(static::$existingColumns)) {
                foreach (array_keys($receipt->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($receipt->attributes[$attr]);
                    }
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policyPage(): BelongsTo
    {
        return $this->belongsTo(PolicyPage::class, 'policy_version_id');
    }
}
