<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PeerNote extends Model
{
    public const SOURCE_PROFILE = 'profile';

    public const SOURCE_SPEED_NETWORKING = 'speed_networking';

    protected $table = 'peer_notes';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'peer_id',
        'note',
        'source',
    ];

    protected static function booted(): void
    {
        static::creating(function (PeerNote $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            if (empty($model->source)) {
                $model->source = self::SOURCE_PROFILE;
            }
        });
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
    public function peer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peer_id');
    }
}
