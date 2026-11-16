<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SpeedNetworkingBlockedPeer extends Model
{
    use HasFactory;

    protected $table = 'speed_networking_blocked_peers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'blocker_id',
        'blocked_id',
        'event_id',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (SpeedNetworkingBlockedPeer $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /**
     * @return BelongsTo<SpeedNetworkingEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(SpeedNetworkingEvent::class, 'event_id');
    }
}
