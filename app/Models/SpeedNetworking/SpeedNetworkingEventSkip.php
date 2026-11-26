<?php

declare(strict_types=1);

namespace App\Models\SpeedNetworking;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SpeedNetworkingEventSkip extends Model
{
    use HasFactory;

    protected $table = 'speed_networking_event_skips';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'event_id',
        'user_id',
        'skipped_peer_id',
        'skip_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skip_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SpeedNetworkingEventSkip $model): void {
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function skippedPeer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'skipped_peer_id');
    }
}
