<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityReminderSetting extends Model
{
    use HasFactory;

    protected $table = 'activity_reminder_settings';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'activity_key',
        'activity_type',
        'activity_name',
        'threshold_days',
        'is_enabled',
        'priority',
        'notification_title',
        'notification_body',
        'target_screen',
    ];

    protected $casts = [
        'threshold_days' => 'integer',
        'is_enabled' => 'boolean',
        'priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }
}
