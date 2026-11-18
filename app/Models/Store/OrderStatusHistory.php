<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OrderStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'order_status_history';

    public $timestamps = false;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'reason',
        'changed_by',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $history): void {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('order_status_history')) {
                        static::$existingColumns = Schema::getColumnListing('order_status_history');
                    } else {
                        static::$existingColumns = [];
                    }
                } catch (\Throwable) {
                    static::$existingColumns = null;
                }
            }

            if (is_array(static::$existingColumns) && ! empty(static::$existingColumns)) {
                foreach (array_keys($history->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($history->attributes[$attr]);
                    }
                }
            }
        });
    }

    public function getStatusAttribute(): ?string
    {
        return $this->to_status;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->reason ?? $this->note;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
