<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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
     * Set/overwrite the active session ID for a user.
     *
     * @param  string  $userId  Unique ID of the user
     * @param  string  $sessionId  Unique session ID (jti)
     * @param  int  $ttlSeconds  Time to live in seconds
     */
    public function setActiveSession(string $userId, string $sessionId, int $ttlSeconds = self::DEFAULT_TTL): void
    {
        $key = $this->getKey($userId);

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

        return $sessionId ? (string) $sessionId : null;
    }

    /**
     * Invalidate and delete the active session entry for a user.
     */
    public function invalidateSession(string $userId): void
    {
        $key = $this->getKey($userId);

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
