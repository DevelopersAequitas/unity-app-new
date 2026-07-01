<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignLog extends Model
{
    use HasFactory;

    protected $table = 'campaign_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'delivery_id',
        'user_id',
        'email',
        'email_status',
        'notification_status',
        'email_sent',
        'notification_sent',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'email_sent' => 'boolean',
        'notification_sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CampaignLog $log): void {
            if (empty($log->id)) {
                $log->id = (string) Str::uuid();
            }
        });
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CampaignDelivery::class, 'delivery_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getSentAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setSentAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['sent_at'] = null;
        } else {
            $this->attributes['sent_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }
}
