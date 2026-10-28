<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\User;
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
        'duration_seconds',
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
}
