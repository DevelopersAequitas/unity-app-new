<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CampaignSchedule extends Model
{
    use HasFactory;

    protected $table = 'campaign_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'campaign_id',
        'schedule_type',
        'start_date',
        'end_type',
        'end_date',
        'send_time',
        'timezone',
        'recurrence_type',
        'frequency_interval',
        'weekdays',
        'monthly_basis',
        'monthly_day_of_month',
        'monthly_position',
        'monthly_day_of_week',
        'yearly_month',
        'yearly_day',
        'custom_unit',
        'cycle_send_days',
        'cycle_pause_days',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CampaignSchedule $schedule): void {
            if (empty($schedule->id)) {
                $schedule->id = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdminCampaign::class, 'campaign_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class, 'schedule_id');
    }

    public function getNextRunAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setNextRunAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['next_run_at'] = null;
        } elseif ($value instanceof \DateTimeInterface) {
            $this->attributes['next_run_at'] = $value->format('Y-m-d H:i:s');
        } else {
            $this->attributes['next_run_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }

    public function getLastRunAtAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return \Carbon\Carbon::parse($value, 'UTC');
    }

    public function setLastRunAtAttribute($value)
    {
        if (! $value) {
            $this->attributes['last_run_at'] = null;
        } elseif ($value instanceof \DateTimeInterface) {
            $this->attributes['last_run_at'] = $value->format('Y-m-d H:i:s');
        } else {
            $this->attributes['last_run_at'] = \Carbon\Carbon::parse($value)->setTimezone('UTC');
        }
    }
}
