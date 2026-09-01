<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserMilestoneBadge extends Model
{
    use HasFactory;

    public const STATUS_EARNED = 'earned';

    public const STATUS_REVOKED = 'revoked';

    public const ALLOWED_STATUSES = [
        self::STATUS_EARNED,
        self::STATUS_REVOKED,
    ];

    protected $table = 'user_milestone_badges';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'badge_id',
        'milestone_type',
        'achieved_count',
        'status',
        'earned_at',
        'revoked_at',
    ];

    protected $casts = [
        'achieved_count' => 'integer',
        'earned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(MilestoneBadge::class, 'badge_id');
    }
}
