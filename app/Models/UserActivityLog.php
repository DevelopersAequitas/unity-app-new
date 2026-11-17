<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $table = 'user_activity_logs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'activity_type',
        'action',
        'description',
        'module',
        'entity_type',
        'entity_id',
        'status',
        'ip_address',
        'user_agent',
        'device_info',
        'platform',
        'app_version',
        'properties',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'device_info' => 'array',
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Booted hooks to assign UUID on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * User who performed or was affected by the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Associated polymorphic entity if present.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    /**
     * Scope: Free-text search across user, action, description, IP, and module.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($search, $like): void {
            $q->where('description', $like, "%{$search}%")
                ->orWhere('action', $like, "%{$search}%")
                ->orWhere('activity_type', $like, "%{$search}%")
                ->orWhere('module', $like, "%{$search}%")
                ->orWhere('ip_address', $like, "%{$search}%")
                ->orWhere('platform', $like, "%{$search}%")
                ->orWhere('app_version', $like, "%{$search}%")
                ->orWhereHas('user', function (Builder $uq) use ($search, $like): void {
                    $uq->where('first_name', $like, "%{$search}%")
                        ->orWhere('last_name', $like, "%{$search}%")
                        ->orWhere('display_name', $like, "%{$search}%")
                        ->orWhere('email', $like, "%{$search}%")
                        ->orWhere('phone', $like, "%{$search}%");
                });
        });
    }

    /**
     * Scope: Filter by activity type.
     */
    public function scopeActivityType(Builder $query, string $activityType): Builder
    {
        if ($activityType === '' || $activityType === 'all') {
            return $query;
        }

        return $query->where('activity_type', $activityType);
    }

    /**
     * Scope: Filter by module.
     */
    public function scopeModule(Builder $query, string $module): Builder
    {
        if ($module === '' || $module === 'all') {
            return $query;
        }

        return $query->where('module', $module);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        if ($status === '' || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by platform.
     */
    public function scopePlatform(Builder $query, string $platform): Builder
    {
        if ($platform === '' || $platform === 'all') {
            return $query;
        }

        return $query->where('platform', $platform);
    }

    /**
     * Scope: Filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (! empty($from)) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if (! empty($to)) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        return $query;
    }
}
