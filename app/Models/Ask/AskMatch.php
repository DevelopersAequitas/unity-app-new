<?php

declare(strict_types=1);

namespace App\Models\Ask;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AskMatch extends Model
{
    use HasFactory;

    protected $table = 'ask_matches';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_INTERESTED = 'interested';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'id',
        'ask_id',
        'matched_user_id',
        'match_score',
        'match_status',
        'matched_dimensions',
        'match_reason',
        'algorithm_version',
        'viewed_at',
    ];

    protected $casts = [
        'match_score' => 'float',
        'matched_dimensions' => 'array',
        'viewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->match_status)) {
                $model->match_status = self::STATUS_SUGGESTED;
            }
        });
    }

    public function ask(): BelongsTo
    {
        return $this->belongsTo(Ask::class, 'ask_id');
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }
}
