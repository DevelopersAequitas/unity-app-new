<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSessionService
{
    /**
     * Key prefix for user session storage.
     */
    private const KEY_PREFIX = 'user:session:';

    /**
     * Default TTL for session token storage (30 days in seconds).
     */
    private const DEFAULT_TTL = 2592000;

    /**
     * Set/overwrite the active session ID for a user in Cache/Redis and SQL Database.
     *
     * @param  string  $userId  Unique ID of the user
     * @param  string  $sessionId  Unique session ID (jti)
     * @param  int  $ttlSeconds  Time to live in seconds
     * @param  string|null  $deviceId  Optional client device identifier
     * @param  string|null  $ipAddress  Optional client IP address
     * @param  string|null  $userAgent  Optional client user agent
     */
    public function setActiveSession(
        string $userId,
        string $sessionId,
        int $ttlSeconds = self::DEFAULT_TTL,
        ?string $deviceId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $key = $this->getKey($userId);

        // 1. Write active session record to user_active_sessions database table
        try {
            if (Schema::hasTable('user_active_sessions')) {
                // Deactivate previous active sessions for this user
                DB::table('user_active_sessions')
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);

                // Insert new active session row
                DB::table('user_active_sessions')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'device_id' => $deviceId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent ? substr($userAgent, 0, 1000) : null,
                    'is_active' => true,
                    'logged_in_at' => now(),
                    'last_activity_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Also update current_session_id in users table if column exists
            if (Schema::hasColumn('users', 'current_session_id')) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'current_session_id' => $sessionId,
                        'last_login_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to persist user session to database: '.$e->getMessage());
        }

        // 2. Write to Redis if available, with Cache fallback
        try {
            if (class_exists(\Redis::class)) {
                Redis::setex($key, $ttlSeconds, $sessionId);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Redis operation failed, falling back to Cache: '.$e->getMessage());
        }

        Cache::put($key, $sessionId, $ttlSeconds);
    }

    /**
     * Fetch the currently active session ID for a user.
     *
     * @return string|null Returns the session ID or null if not found
     */
    public function getActiveSession(string $userId): ?string
    {
        $key = $this->getKey($userId);

        try {
            if (class_exists(\Redis::class)) {
                $sessionId = Redis::get($key);
                if ($sessionId) {
                    return (string) $sessionId;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Redis get failed, falling back to Cache: '.$e->getMessage());
        }

        $sessionId = Cache::get($key);

        if ($sessionId) {
            return (string) $sessionId;
        }

        // DB Fallback check if Cache misses
        try {
            if (Schema::hasTable('user_active_sessions')) {
                $dbSession = DB::table('user_active_sessions')
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->orderByDesc('created_at')
                    ->value('session_id');

                if ($dbSession) {
                    return (string) $dbSession;
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB fallback error
        }

        return null;
    }

    /**
     * Invalidate and delete the active session entry for a user.
     */
    public function invalidateSession(string $userId): void
    {
        $key = $this->getKey($userId);

        try {
            if (Schema::hasTable('user_active_sessions')) {
                DB::table('user_active_sessions')
                    ->where('user_id', $userId)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        try {
            if (class_exists(\Redis::class)) {
                Redis::del($key);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        Cache::forget($key);
    }

    /**
     * Verify if a given session ID matches the currently active session.
     */
    public function isSessionValid(string $userId, string $sessionId): bool
    {
        $activeSessionId = $this->getActiveSession($userId);

        if ($activeSessionId === null) {
            return false;
        }

        return hash_equals($activeSessionId, $sessionId);
    }

    /**
     * Generate a new unique session identifier (UUID v4).
     */
    public function generateSessionId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Build the key for a user ID.
     */
    private function getKey(string $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }
}
