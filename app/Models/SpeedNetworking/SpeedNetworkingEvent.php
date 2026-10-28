<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\Circle;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SpeedNetworkingEvent extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LOBBY_OPEN = 'lobby_open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    protected $table = 'speed_networking_events';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'description',
        'banner_url',
        'status',
        'round_duration_seconds',
        'break_duration_seconds',
        'total_rounds',
        'current_round',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'ticket_price',
        'is_paid',
        'currency',
        'circle_id',
        'circle_restricted',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_duration_seconds' => 'integer',
            'break_duration_seconds' => 'integer',
            'total_rounds' => 'integer',
            'current_round' => 'integer',
            'scheduled_start_time' => 'datetime',
            'scheduled_end_time' => 'datetime',
            'actual_start_time' => 'datetime',
            'actual_end_time' => 'datetime',
            'ticket_price' => 'float',
            'is_paid' => 'boolean',
            'circle_restricted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpeedNetworkingEvent $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::deleting(function (SpeedNetworkingEvent $model): void {
            try {
                Event::where('id', $model->id)->delete();
            } catch (\Throwable $e) {
            }
        });
    }

    /**
     * @return HasMany<SpeedNetworkingParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SpeedNetworkingParticipant::class, 'event_id');
    }

    /**
     * @return HasMany<SpeedNetworkingMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(SpeedNetworkingMatch::class, 'event_id');
    }

    /**
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    /**
     * Check if event has ended or reached all rounds.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED ||
            ($this->total_rounds > 0 && $this->current_round >= $this->total_rounds && $this->status === self::STATUS_IN_PROGRESS);
    }
}
