<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class NotificationEvent extends Model
{
    use HasUuids;

    protected $table = 'notification_events';

    const UPDATED_AT = null;

    protected static ?array $existingColumns = null;

    protected $fillable = [
        'event_key',
        'user_id',
        'reference_type',
        'reference_id',
        'payload',
        'status',
        'available_at',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $event): bool {
            if (static::$existingColumns === null) {
                try {
                    if (Schema::hasTable('notification_events')) {
                        static::$existingColumns = Schema::getColumnListing('notification_events');
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

            foreach (array_keys($event->attributes) as $attr) {
                if (! in_array($attr, static::$existingColumns, true)) {
                    unset($event->attributes[$attr]);
                }
            }

            return true;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'event_id');
    }
}
