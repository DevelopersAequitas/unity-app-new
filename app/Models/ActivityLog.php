<?php

declare(strict_types=1);

namespace App\Models;

use BadMethodCallException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'actor_id',
        'activity_type',
        'module',
        'description',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Booted listeners to ensure strict immutability and automatic UUID assignment.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        // Enforce strict immutability: activity logs must never be updated or deleted.
        static::updating(function (): void {
            throw new BadMethodCallException('Activity logs are immutable audit records and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new BadMethodCallException('Activity logs are immutable audit records and cannot be deleted.');
        });
    }

    /**
     * Target or subject user of the activity.
     * Retains relation even if the user has been soft-deleted.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * The actor (User or Admin) who triggered the activity.
     * Retains relation even if the actor has been soft-deleted.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /**
     * Associated polymorphic entity if present.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /**
     * Scope query by module.
     */
    public function scopeModule(Builder $query, ?string $module): Builder
    {
        if (!empty($module) && $module !== 'all') {
            return $query->where('module', $module);
        }

        return $query;
    }

    /**
     * Scope query by activity type.
     */
    public function scopeActivityType(Builder $query, ?string $activityType): Builder
    {
        if (!empty($activityType) && $activityType !== 'all') {
            return $query->where('activity_type', $activityType);
        }

        return $query;
    }

    /**
     * Scope query by status (success, failed, denied).
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (!empty($status) && $status !== 'all') {
            return $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Scope query by target user UUID.
     */
    public function scopeUser(Builder $query, ?string $userId): Builder
    {
        if (!empty($userId) && $userId !== 'all') {
            return $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Scope query by actor UUID.
     */
    public function scopeActor(Builder $query, ?string $actorId): Builder
    {
        if (!empty($actorId) && $actorId !== 'all') {
            return $query->where('actor_id', $actorId);
        }

        return $query;
    }

    /**
     * Scope query by date range.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (!empty($from)) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if (!empty($to)) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        return $query;
    }

    /**
     * Search scope across description, activity type, IP address, and related actor/user identities.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
                ->orWhere('activity_type', 'like', "%{$term}%")
                ->orWhere('ip_address', 'like', "%{$term}%")
                ->orWhereHas('user', function (Builder $uq) use ($term) {
                    $uq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('display_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company_name', 'like', "%{$term}%");
                })
                ->orWhereHas('actor', function (Builder $aq) use ($term) {
                    $aq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('display_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }
}
