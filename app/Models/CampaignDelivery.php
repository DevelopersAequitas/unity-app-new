<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CampaignDelivery extends Model
{
    use HasFactory;

    protected $table = 'campaign_deliveries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'campaign_id',
        'schedule_id',
        'status',
        'total_recipients',
        'total_email_sent',
        'total_notification_sent',
        'total_failed',
        'error_message',
        'batch_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'triggered_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CampaignDelivery $delivery): void {
            if (empty($delivery->id)) {
                $delivery->id = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdminCampaign::class, 'campaign_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CampaignSchedule::class, 'schedule_id');
    }

    public function getScheduledAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setScheduledAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['scheduled_at'] = null;
        } else {
            $this->attributes['scheduled_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }

    public function getStartedAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setStartedAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['started_at'] = null;
        } else {
            $this->attributes['started_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }

    public function getCompletedAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setCompletedAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['completed_at'] = null;
        } else {
            $this->attributes['completed_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'delivery_id');
    }

    public function formatTimestamp(?\Carbon\Carbon $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }
        $campaign = $this->campaign;
        if ($campaign) {
            return $campaign->formatTimestamp($dateTime);
        }

        return $dateTime->copy()->setTimezone('Asia/Kolkata')->format('d M Y H:i');
    }
}
