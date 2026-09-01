<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class UserPushToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_push_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'usr_id',
        'token',
        'platform',
        'device_id',
        'app_version',
        'last_seen_at',
        'last_used_at',
        'is_active',
        'last_update_notification_sent_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'last_update_notification_sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public static function getUserIdColumn(): string
    {
        static $column = null;
        if ($column === null) {
            $column = Schema::hasColumn('user_push_tokens', 'usr_id') ? 'usr_id' : 'user_id';
        }

        return $column;
    }

    public function getUserIdAttribute()
    {
        $col = self::getUserIdColumn();

        return $this->attributes[$col] ?? null;
    }

    public function setUserIdAttribute($value)
    {
        $col = self::getUserIdColumn();
        $this->attributes[$col] = $value;
    }

    public static function registerTokenForUser($user, array $attributes): self
    {
        $userId = $user->id;
        $token = $attributes['token'];
        $deviceId = $attributes['device_id'] ?? null;

        // Delete token if it belongs to a different user
        self::where('token', $token)
            ->where(self::getUserIdColumn(), '!=', $userId)
            ->delete();

        // Clean up duplicates for the same user on the same device
        if (filled($deviceId)) {
            self::where('device_id', $deviceId)
                ->where(self::getUserIdColumn(), $userId)
                ->where('token', '!=', $token)
                ->delete();
        }

        $updates = [
            self::getUserIdColumn() => $userId,
            'platform' => isset($attributes['platform']) ? strtolower((string) $attributes['platform']) : null,
            'device_id' => $deviceId,
            'app_version' => $attributes['app_version'] ?? null,
        ];

        if (Schema::hasColumn('user_push_tokens', 'is_active')) {
            $updates['is_active'] = true;
        }
        if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
            $updates['last_used_at'] = now();
        }

        if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
            $updates['last_seen_at'] = now();
        }
        if (Schema::hasColumn('user_push_tokens', 'status')) {
            $updates['status'] = 'active';
        }
        if (Schema::hasColumn('user_push_tokens', 'token_status')) {
            $updates['token_status'] = 'active';
        }
        if (Schema::hasColumn('user_push_tokens', 'failed_at')) {
            $updates['failed_at'] = null;
        }
        if (Schema::hasColumn('user_push_tokens', 'failure_reason')) {
            $updates['failure_reason'] = null;
        }

        return self::updateOrCreate(
            ['token' => $token],
            $updates
        );
    }

    protected static function booted(): void
    {
        static::saved(function (self $pushToken): void {
            $user = $pushToken->user;
            if ($user) {
                $platform = strtolower((string) $pushToken->platform);
                $token = $pushToken->token;
                $isActive = (bool) ($pushToken->is_active ?? true);

                $hasAndroid = Schema::hasColumn('users', 'android_fcm_token');
                $hasIos = Schema::hasColumn('users', 'ios_fcm_token');

                if ($isActive) {
                    if ($platform === 'android' && $hasAndroid) {
                        if ($user->android_fcm_token !== $token) {
                            $user->android_fcm_token = $token;
                            $user->save();
                        }
                    } elseif (in_array($platform, ['ios', 'apple', 'iphone']) && $hasIos) {
                        if ($user->ios_fcm_token !== $token) {
                            $user->ios_fcm_token = $token;
                            $user->save();
                        }
                    }
                } else {
                    if ($hasAndroid && $user->android_fcm_token === $token) {
                        $user->android_fcm_token = null;
                        $user->save();
                    }
                    if ($hasIos && $user->ios_fcm_token === $token) {
                        $user->ios_fcm_token = null;
                        $user->save();
                    }
                }
            }
        });

        static::deleted(function (self $pushToken): void {
            $user = $pushToken->user;
            if ($user) {
                $token = $pushToken->token;
                $hasAndroid = Schema::hasColumn('users', 'android_fcm_token');
                $hasIos = Schema::hasColumn('users', 'ios_fcm_token');

                if ($hasAndroid && $user->android_fcm_token === $token) {
                    $user->android_fcm_token = null;
                    $user->save();
                }
                if ($hasIos && $user->ios_fcm_token === $token) {
                    $user->ios_fcm_token = null;
                    $user->save();
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, self::getUserIdColumn());
    }
}
