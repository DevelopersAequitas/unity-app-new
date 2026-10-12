<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class ActivityLoggerService
{
    /**
     * Keys containing sensitive data that must be scrubbed from audit metadata.
     */
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'bearer',
        'secret',
        'otp',
        'otp_code',
        'pin',
        'private_key',
        'api_key',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'authorization',
        'chat_message_body',
        'raw_token',
    ];

    /**
     * Primary log method. Safely logs an activity record into activity_logs.
     * Guaranteed non-blocking and exception-safe (adheres to Rule 29).
     *
     * @param array{
     *     user_id: string,
     *     activity_type: string,
     *     module: string,
     *     description: string,
     *     actor_id?: string|null,
     *     entity?: Model|null,
     *     entity_type?: string|null,
     *     entity_id?: string|null,
     *     metadata?: array|null,
     *     ip_address?: string|null,
     *     user_agent?: string|null,
     *     status?: string
     * } $data
     */
    public static function log(array $data): ?ActivityLog
    {
        try {
            $userId = $data['user_id'] ?? null;
            if (!$userId) {
                // If user_id is omitted, try actor or current auth user
                $userId = $data['actor_id'] ?? Auth::guard('sanctum')->id() ?? Auth::id();
            }

            if (!$userId) {
                Log::warning('ActivityLoggerService: Attempted to log activity without a valid user_id', [
                    'activity_type' => $data['activity_type'] ?? 'unknown',
                    'module' => $data['module'] ?? 'unknown',
                ]);
                return null;
            }

            $actorId = $data['actor_id'] ?? Auth::guard('sanctum')->id() ?? Auth::id();

            // Resolve polymorphic entity
            $entityType = $data['entity_type'] ?? null;
            $entityId = $data['entity_id'] ?? null;
            if (isset($data['entity']) && $data['entity'] instanceof Model) {
                $entityType = get_class($data['entity']);
                $entityId = (string) $data['entity']->getKey();
            }

            // Sanitize metadata to protect sensitive credentials and data
            $rawMetadata = $data['metadata'] ?? [];
            $sanitizedMetadata = !empty($rawMetadata) ? self::sanitizeMetadata((array) $rawMetadata) : null;

            $ipAddress = $data['ip_address'] ?? Request::ip();
            $userAgent = $data['user_agent'] ?? Request::userAgent();
            $status = $data['status'] ?? 'success';

            return ActivityLog::create([
                'user_id' => (string) $userId,
                'actor_id' => $actorId ? (string) $actorId : null,
                'activity_type' => (string) ($data['activity_type'] ?? 'general.event'),
                'module' => (string) ($data['module'] ?? 'general'),
                'description' => (string) ($data['description'] ?? 'System recorded activity'),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $sanitizedMetadata,
                'ip_address' => $ipAddress ? substr((string) $ipAddress, 0, 45) : null,
                'user_agent' => $userAgent ? (string) $userAgent : null,
                'status' => in_array($status, ['success', 'failed', 'denied'], true) ? $status : 'success',
            ]);
        } catch (Throwable $e) {
            // Never break primary transaction or crash the user experience
            Log::error('ActivityLoggerService failed to write audit log: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload_summary' => [
                    'type' => $data['activity_type'] ?? null,
                    'module' => $data['module'] ?? null,
                ],
            ]);
            return null;
        }
    }

    /**
     * Module 1: Auth & Identity Logger Shortcut
     */
    public static function logAuth(
        string $userId,
        string $activityType,
        string $description,
        ?array $metadata = null,
        string $status = 'success',
        ?string $actorId = null
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId ?? $userId,
            'activity_type' => $activityType,
            'module' => 'auth',
            'description' => $description,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 2: Networking & Connections Logger Shortcut
     */
    public static function logNetworking(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'networking',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 3: Content & Social Logger Shortcut
     */
    public static function logContent(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'posts',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 4: Circles & Governance Logger Shortcut
     */
    public static function logCircle(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'circles',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 5: Conclaves & Events Logger Shortcut
     */
    public static function logEvent(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'events',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 6: Communications & Peer Referrals Logger Shortcut
     */
    public static function logCommunication(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'communications',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 7: Economy & Rewards (Coins) Logger Shortcut
     */
    public static function logEconomy(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'coins',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 8: Memberships & Billing Logger Shortcut
     */
    public static function logBilling(
        string $userId,
        string $activityType,
        string $description,
        ?string $actorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'activity_type' => $activityType,
            'module' => 'billing',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Module 9: Administrative Actions Logger Shortcut
     */
    public static function logAdminAction(
        string $targetUserId,
        string $activityType,
        string $description,
        ?string $adminActorId = null,
        ?Model $entity = null,
        ?array $metadata = null,
        string $status = 'success'
    ): ?ActivityLog {
        return self::log([
            'user_id' => $targetUserId,
            'actor_id' => $adminActorId ?? Auth::guard('sanctum')->id() ?? Auth::id(),
            'activity_type' => $activityType,
            'module' => 'admin',
            'description' => $description,
            'entity' => $entity,
            'metadata' => $metadata,
            'status' => $status,
        ]);
    }

    /**
     * Recursively scrubs sensitive parameters from metadata arrays.
     */
    public static function sanitizeMetadata(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;

            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($lowerKey, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeMetadata($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = self::sanitizeMetadata((array) $value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
