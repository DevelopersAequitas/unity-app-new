<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SpeedNetworkingParticipant extends Model
{
    use HasFactory;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_LOBBY = 'lobby';

    public const STATUS_SEARCHING = 'searching';

    public const STATUS_IN_CALL = 'in_call';

    public const STATUS_BREAK = 'break';

    public const STATUS_LEFT = 'left';

    protected $table = 'speed_networking_participants';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'user_id',
        'status',
        'joined_at',
        'last_heartbeat_at',
        'break_started_at',
        'break_remaining_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'break_started_at' => 'datetime',
            'break_remaining_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpeedNetworkingParticipant $model): void {
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
