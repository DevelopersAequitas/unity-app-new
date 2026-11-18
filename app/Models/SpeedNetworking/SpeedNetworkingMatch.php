<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SpeedNetworkingMatch extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const MATCH_STATUS_PREVIEW = 'preview';

    public const MATCH_STATUS_IN_CALL = 'in_call';

    public const MATCH_STATUS_COMPLETED = 'completed';

    public const MATCH_STATUS_SKIPPED = 'skipped';

    public const MATCH_STATUS_BLOCKED = 'blocked';

    public const MATCH_STATUS_EXPIRED = 'expired';

    public const DECISION_PENDING = 'pending';

    public const DECISION_ACCEPTED = 'accepted';

    public const DECISION_SKIP = 'skip';

    public const DECISION_BLOCK = 'block';

    public const MIN_QUALIFYING_DURATION_SECONDS = 120;

    protected $table = 'speed_networking_matches';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'round_number',
        'table_number',
        'channel_id',
        'agora_token',
        'user_a_id',
        'user_b_id',
        'status',
        'match_status',
        'user_a_decision',
        'user_b_decision',
        'preview_duration_seconds',
        'preview_expires_at',
        'call_started_at',
        'duration_seconds',
        'user_a_notes',
        'user_b_notes',
        'meeting_notes',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'table_number' => 'integer',
            'preview_duration_seconds' => 'integer',
            'preview_expires_at' => 'datetime',
            'call_started_at' => 'datetime',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpeedNetworkingMatch $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<SpeedNetworkingEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(SpeedNetworkingEvent::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    /**
     * Determine if this match represents an odd-man out break / lounge round.
     */
    public function isBreakRound(): bool
    {
        return empty($this->user_b_id);
    }

    /**
     * Determine if the given user is part of this match.
     */
    public function hasUser(string $userId): bool
    {
        return $this->user_a_id === $userId || $this->user_b_id === $userId;
    }

    /**
     * Get the peer user for the given user in this match.
     */
    public function getPartner(string $userId): ?User
    {
        if ($this->user_a_id === $userId) {
            return $this->userB;
        }

        if ($this->user_b_id === $userId) {
            return $this->userA;
        }

        return null;
    }

    /**
     * Resolve actual 1-on-1 call duration in seconds, counting strictly from call_started_at (or started_at).
     */
    public function resolveCallDurationSeconds(?int $maxRoundDurationSeconds = null): int
    {
        if ($this->duration_seconds !== null && (int) $this->duration_seconds >= 0) {
            $persisted = (int) $this->duration_seconds;
            if ($persisted > 0 || ($this->ended_at !== null && $this->call_started_at !== null)) {
                if ($maxRoundDurationSeconds !== null && $maxRoundDurationSeconds > 0) {
                    return min($persisted, $maxRoundDurationSeconds);
                }

                return $persisted;
            }
        }

        $callStart = $this->call_started_at ?? $this->started_at;
        if (! $callStart) {
            return 0;
        }

        $callEnd = $this->ended_at ?? Carbon::now();
        $duration = max(0, (int) $callStart->diffInSeconds($callEnd, false));

        if ($maxRoundDurationSeconds !== null && $maxRoundDurationSeconds > 0) {
            $duration = min($duration, $maxRoundDurationSeconds);
        }

        return $duration;
    }

    /**
     * Check whether this match lasted at least 120 seconds (2 minutes) in an actual 1-on-1 call.
     */
    public function qualifiesForP2pRewards(?int $maxRoundDurationSeconds = null): bool
    {
        if ($this->isBreakRound() || empty($this->user_a_id) || empty($this->user_b_id)) {
            return false;
        }

        if ($this->status === self::STATUS_SKIPPED) {
            return false;
        }

        $matchStatus = (string) ($this->match_status ?: '');
        if ($matchStatus !== '' && ! in_array($matchStatus, [
            self::MATCH_STATUS_IN_CALL,
            self::MATCH_STATUS_COMPLETED,
        ], true)) {
            return false;
        }

        return $this->resolveCallDurationSeconds($maxRoundDurationSeconds) >= self::MIN_QUALIFYING_DURATION_SECONDS;
    }

    /**
     * Get saved meeting notes for the given user (or fallback to shared meeting_notes).
     */
    public function getNotesForUser(?string $userId = null): string
    {
        if ($userId !== null && $userId !== '') {
            if ((string) $this->user_a_id === $userId && ! empty($this->user_a_notes)) {
                return trim((string) $this->user_a_notes);
            }
            if ((string) $this->user_b_id === $userId && ! empty($this->user_b_notes)) {
                return trim((string) $this->user_b_notes);
            }
        }

        if (! empty($this->meeting_notes)) {
            return trim((string) $this->meeting_notes);
        }

        if (! empty($this->user_a_notes)) {
            return trim((string) $this->user_a_notes);
        }

        if (! empty($this->user_b_notes)) {
            return trim((string) $this->user_b_notes);
        }

        return '';
    }
}
