<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EventCoupon extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'event_coupons';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'event_id',
        'occurrence_id',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventCoupon $coupon): void {
            if (empty($coupon->id)) {
                $coupon->id = (string) Str::uuid();
            }
            if (! empty($coupon->code)) {
                $coupon->code = Str::upper(trim($coupon->code));
            }
        });

        static::updating(function (EventCoupon $coupon): void {
            if (! empty($coupon->code)) {
                $coupon->code = Str::upper(trim($coupon->code));
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'coupon_id');
    }

    public function isValidForEvent(?Event $event = null, ?EventOccurrence $occurrence = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        if ($this->valid_from !== null && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until !== null && $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($event !== null && $this->event_id !== null && (string) $this->event_id !== (string) $event->id) {
            return false;
        }

        if ($occurrence !== null && $this->occurrence_id !== null && (string) $this->occurrence_id !== (string) $occurrence->id) {
            return false;
        }

        return true;
    }
}
