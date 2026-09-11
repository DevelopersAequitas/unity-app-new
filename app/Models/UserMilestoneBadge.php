<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Notifications\MilestoneCatalystWhatsappService;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
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

        static::saved(function (self $model): void {
            if ($model->status !== self::STATUS_EARNED) {
                return;
            }

            // Only trigger on newly created earned badge or transition to earned
            if (! $model->wasRecentlyCreated && ! $model->wasChanged('status')) {
                return;
            }

            try {
                $user = $model->user ?: User::find($model->user_id);
                if (! $user) {
                    return;
                }

                $achievedCount = (int) $model->achieved_count;
                $milestoneType = strtoupper(trim((string) ($model->milestone_type ?? '')));
                $badgeTitle = $model->badge ? strtolower(trim((string) $model->badge->title)) : '';
                $requiredCount = $model->badge ? (int) $model->badge->required_count : 0;

                // Connector milestone (count = 1)
                if ($achievedCount === 1 || $requiredCount === 1 || $milestoneType === 'CONNECTOR' || $badgeTitle === 'connector') {
                    app(MilestoneConnectorWhatsappService::class)->handleFirstIntroduction($user);
                }

                // Catalyst milestone (count = 3)
                if ($achievedCount === 3 || $requiredCount === 3 || $milestoneType === 'CATALYST' || $badgeTitle === 'catalyst') {
                    app(MilestoneCatalystWhatsappService::class)->handleCatalystMilestone($user);
                }
            } catch (\Throwable $e) {
                Log::error('[UserMilestoneBadge::saved] Failed auto-triggering milestone notification: '.$e->getMessage(), [
                    'id' => $model->id,
                    'user_id' => $model->user_id,
                ]);
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
