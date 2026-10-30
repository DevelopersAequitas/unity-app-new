<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\Circle;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * @var array<int, string>
     */
    protected $appends = [
        'duration_seconds',
        'duration_minutes',
        'end_time',
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

        static::saving(function (SpeedNetworkingEvent $model): void {
            $start = ! empty($model->attributes['scheduled_start_time']) ? Carbon::parse($model->attributes['scheduled_start_time']) : null;
            $meta = is_array($model->metadata) ? $model->metadata : (json_decode((string) $model->metadata, true) ?: []);
            $hasExplicitDuration = ! empty($meta['duration_seconds'])
                || ! empty($model->attributes['duration_seconds'])
                || ! empty($meta['duration_minutes'])
                || ! empty($model->attributes['duration_minutes'])
                || ! empty($meta['duration'])
                || ! empty($model->attributes['duration']);

            if ($start && ($hasExplicitDuration || empty($model->attributes['scheduled_end_time']))) {
                $model->attributes['scheduled_end_time'] = $start->copy()->addSeconds($model->calculateDurationSeconds());
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
     * Calculate total duration of this event in seconds.
     */
    public function calculateDurationSeconds(): int
    {
        $meta = is_array($this->metadata) ? $this->metadata : (json_decode((string) $this->metadata, true) ?: []);

        if (! empty($meta['duration_seconds'])) {
            return (int) $meta['duration_seconds'];
        }
        if (! empty($this->attributes['duration_seconds'])) {
            return (int) $this->attributes['duration_seconds'];
        }
        if (! empty($meta['duration_minutes'])) {
            return (int) $meta['duration_minutes'] * 60;
        }
        if (! empty($this->attributes['duration_minutes'])) {
            return (int) $this->attributes['duration_minutes'] * 60;
        }
        if (! empty($meta['duration'])) {
            return (int) ($meta['duration'] * 60);
        }
        if (! empty($this->attributes['duration'])) {
            return (int) ($this->attributes['duration'] * 60);
        }

        $rounds = (int) ($this->total_rounds ?: 0);
        $roundSec = (int) ($this->round_duration_seconds ?: config('services.speed_networking.default_round_seconds', 180));
        $breakSec = (int) ($this->break_duration_seconds ?: config('services.speed_networking.default_break_seconds', 20));

        if ($rounds > 0) {
            return ($rounds * $roundSec) + (max(0, $rounds - 1) * $breakSec);
        }

        $defaultRounds = 5;

        return ($defaultRounds * $roundSec) + (($defaultRounds - 1) * $breakSec);
    }

    /**
     * Calculate total duration of this event in minutes.
     */
    public function calculateDurationMinutes(): int
    {
        return (int) ceil($this->calculateDurationSeconds() / 60);
    }

    /**
     * Accessor for scheduled_end_time. If null in DB, automatically computes
     * from scheduled_start_time (or actual_start_time) + duration.
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function scheduledEndTime(): Attribute
    {
        return Attribute::make(
            get: function ($value): ?Carbon {
                $start = $this->scheduled_start_time
                    ?? (! empty($this->attributes['scheduled_start_time']) ? Carbon::parse($this->attributes['scheduled_start_time']) : null)
                    ?? $this->actual_start_time
                    ?? (! empty($this->attributes['actual_start_time']) ? Carbon::parse($this->attributes['actual_start_time']) : null)
                    ?? $this->created_at
                    ?? Carbon::now();

                $durSeconds = $this->calculateDurationSeconds();
                if ($durSeconds <= 0) {
                    $durSeconds = 1800;
                }

                $meta = is_array($this->metadata) ? $this->metadata : (json_decode((string) $this->metadata, true) ?: []);
                $hasExplicitDuration = ! empty($meta['duration_seconds'])
                    || ! empty($this->attributes['duration_seconds'])
                    || ! empty($meta['duration_minutes'])
                    || ! empty($this->attributes['duration_minutes'])
                    || ! empty($meta['duration'])
                    || ! empty($this->attributes['duration']);

                if ($hasExplicitDuration && $start) {
                    return $this->asDateTime($start)->copy()->addSeconds($durSeconds);
                }

                if (! empty($value)) {
                    return $this->asDateTime($value);
                }

                if ($start) {
                    return $this->asDateTime($start)->copy()->addSeconds($durSeconds);
                }

                return Carbon::now()->addSeconds($durSeconds);
            },
            set: fn ($value) => $value
        );
    }

    /**
     * @return Attribute<int, never>
     */
    protected function durationSeconds(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->calculateDurationSeconds()
        );
    }

    /**
     * @return Attribute<int, never>
     */
    protected function durationMinutes(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->calculateDurationMinutes()
        );
    }

    /**
     * @return Attribute<Carbon|null, never>
     */
    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Carbon => $this->scheduled_end_time
        );
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
