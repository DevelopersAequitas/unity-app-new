<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SpeedCollaborationLivePushService
{
    public function __construct(
        protected FirebaseFcmService $firebaseFcm
    ) {}

    /**
     * Broadcast persistent LIVE sticky notification (Android FCM data-only)
     * and remote Live Activity start (iOS APNs HTTP/2) when an event goes live.
     *
     * @return array{android_sent: int, android_failed: int, ios_sent: int, ios_failed: int}
     */
    public function broadcastLiveStarted(SpeedNetworkingEvent $event): array
    {
        $stats = ['android_sent' => 0, 'android_failed' => 0, 'ios_sent' => 0, 'ios_failed' => 0];

        try {
            $userIds = $this->resolveTargetUserIds($event);
            if ($userIds === []) {
                return $stats;
            }

            $nowUtc = Carbon::now()->utc();
            $endTimeUtc = ($event->scheduled_end_time ?? $nowUtc->copy()->addSeconds($event->calculateDurationSeconds()))
                ->copy()
                ->utc();
            $participantsCount = max(1, count($userIds));

            // 1. Android: High-priority FCM data-only push
            $androidTokens = $this->resolveAndroidTokens($userIds);
            $fcmData = [
                'type' => 'speed_collaboration_live',
                'event_id' => (string) $event->id,
                'event_title' => (string) ($event->title ?: 'Speed Collaboration'),
                'end_time' => $endTimeUtc->format('Y-m-d\TH:i:s\Z'),
                'participants_count' => (string) $participantsCount,
            ];

            foreach ($androidTokens as $token) {
                $res = $this->firebaseFcm->sendDataOnlyToDevice($token, $fcmData, [
                    'notification_type' => 'speed_collaboration_live',
                    'event_id' => (string) $event->id,
                    'platform' => 'android',
                ]);
                if ($res['success'] ?? false) {
                    $stats['android_sent']++;
                } else {
                    $stats['android_failed']++;
                }
            }

            // 2. iOS: APNs HTTP/2 push-to-start Live Activity
            $iosStartTokens = $this->resolveIosLiveActivityTokens($userIds, 'live_activity_push_to_start_token');
            if ($iosStartTokens !== []) {
                $apnsPayload = [
                    'aps' => [
                        'timestamp' => $nowUtc->timestamp,
                        'event' => 'start',
                        'attributes-type' => 'SpeedCollaborationActivityAttributes',
                        'attributes' => [
                            'eventId' => (string) $event->id,
                            'eventTitle' => (string) ($event->title ?: 'Speed Collaboration'),
                            'deepLinkUrl' => 'peersunity://speed-collaboration?event_id='.$event->id,
                        ],
                        'content-state' => [
                            'statusText' => 'LIVE NOW',
                            'endTimeEpochSeconds' => $endTimeUtc->timestamp,
                            'participantsCount' => $participantsCount,
                            'isEnded' => false,
                        ],
                        'alert' => [
                            'title' => 'Speed Collaboration is LIVE',
                            'body' => 'Tap to join '.($event->title ?: 'Speed Collaboration').' now.',
                        ],
                    ],
                ];

                foreach ($iosStartTokens as $apnsToken) {
                    $ok = $this->sendApnsLiveActivity($apnsToken, $apnsPayload);
                    if ($ok) {
                        $stats['ios_sent']++;
                    } else {
                        $stats['ios_failed']++;
                    }
                }
            }

            Log::info('[SpeedCollaborationLivePushService] broadcastLiveStarted completed', [
                'event_id' => (string) $event->id,
                'stats' => $stats,
            ]);
        } catch (Throwable $e) {
            Log::warning('[SpeedCollaborationLivePushService] broadcastLiveStarted error: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * Dismiss sticky notification (Android FCM data-only)
     * and end Live Activity (iOS APNs HTTP/2) when an event finishes.
     *
     * @return array{android_sent: int, android_failed: int, ios_sent: int, ios_failed: int}
     */
    public function broadcastLiveEnded(SpeedNetworkingEvent $event): array
    {
        $stats = ['android_sent' => 0, 'android_failed' => 0, 'ios_sent' => 0, 'ios_failed' => 0];

        try {
            $userIds = $this->resolveTargetUserIds($event, includeLiveActivityEventTokens: true);
            if ($userIds === []) {
                return $stats;
            }

            $nowUtc = Carbon::now()->utc();

            // 1. Android: High-priority FCM data-only dismiss push
            $androidTokens = $this->resolveAndroidTokens($userIds);
            $fcmData = [
                'type' => 'speed_collaboration_ended',
                'event_id' => (string) $event->id,
            ];

            foreach ($androidTokens as $token) {
                $res = $this->firebaseFcm->sendDataOnlyToDevice($token, $fcmData, [
                    'notification_type' => 'speed_collaboration_ended',
                    'event_id' => (string) $event->id,
                    'platform' => 'android',
                ]);
                if ($res['success'] ?? false) {
                    $stats['android_sent']++;
                } else {
                    $stats['android_failed']++;
                }
            }

            // 2. iOS: APNs HTTP/2 end Live Activity
            $iosActivityTokens = $this->resolveIosLiveActivityTokens($userIds, 'live_activity_token', (string) $event->id);
            if ($iosActivityTokens !== []) {
                $apnsPayload = [
                    'aps' => [
                        'timestamp' => $nowUtc->timestamp,
                        'event' => 'end',
                        'dismissal-date' => $nowUtc->timestamp,
                        'content-state' => [
                            'statusText' => 'ENDED',
                            'endTimeEpochSeconds' => $nowUtc->timestamp,
                            'participantsCount' => 0,
                            'isEnded' => true,
                        ],
                    ],
                ];

                foreach ($iosActivityTokens as $apnsToken) {
                    $ok = $this->sendApnsLiveActivity($apnsToken, $apnsPayload);
                    if ($ok) {
                        $stats['ios_sent']++;
                    } else {
                        $stats['ios_failed']++;
                    }
                }
            }

            Log::info('[SpeedCollaborationLivePushService] broadcastLiveEnded completed', [
                'event_id' => (string) $event->id,
                'stats' => $stats,
            ]);
        } catch (Throwable $e) {
            Log::warning('[SpeedCollaborationLivePushService] broadcastLiveEnded error: '.$e->getMessage());
        }

        return $stats;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveTargetUserIds(SpeedNetworkingEvent $event, bool $includeLiveActivityEventTokens = false): array
    {
        $userIds = [];

        if (Schema::hasTable('speed_networking_participants')) {
            $userIds = SpeedNetworkingParticipant::where('event_id', $event->id)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        if (Schema::hasTable('event_registrations')) {
            try {
                $regQuery = EventRegistration::where('event_id', $event->id)
                    ->where('status', '!=', 'cancelled')
                    ->whereNull('deleted_at');

                if (($event->ticket_price ?? 0) > 0) {
                    $regQuery->where(function ($q): void {
                        $q->where('status', 'registered')->orWhere('payment_status', 'paid');
                    });
                }

                $regIds = $regQuery->pluck('user_id')->map(fn ($id) => (string) $id)->all();
                $userIds = array_merge($userIds, $regIds);
            } catch (Throwable) {
            }
        }

        if (
            $includeLiveActivityEventTokens
            && Schema::hasTable('user_push_tokens')
            && Schema::hasColumn('user_push_tokens', 'live_activity_event_id')
        ) {
            try {
                $userCol = UserPushToken::getUserIdColumn();
                $laUserIds = UserPushToken::where('live_activity_event_id', (string) $event->id)
                    ->pluck($userCol)
                    ->map(fn ($id) => (string) $id)
                    ->all();
                $userIds = array_merge($userIds, $laUserIds);
            } catch (Throwable) {
            }
        }

        return array_values(array_filter(array_unique($userIds)));
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<int, string>
     */
    protected function resolveAndroidTokens(array $userIds): array
    {
        $tokens = [];

        if (Schema::hasTable('user_push_tokens')) {
            try {
                $userCol = UserPushToken::getUserIdColumn();
                $query = UserPushToken::query()
                    ->whereIn($userCol, $userIds)
                    ->whereRaw('LOWER(platform) = ?', ['android'])
                    ->whereNotNull('token')
                    ->where('token', '!=', '')
                    ->where('token', 'not like', 'apns_live_activity:%');

                if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                    $query->where('is_active', true);
                }
                if (Schema::hasColumn('user_push_tokens', 'app')) {
                    $query->where(function ($q): void {
                        $q->whereNull('app')->orWhere('app', '')->orWhere('app', '!=', 'leader');
                    });
                }

                $tokens = array_merge($tokens, $query->pluck('token')->map(fn ($t) => (string) $t)->all());
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'android_fcm_token')) {
            try {
                $fallback = User::whereIn('id', $userIds)
                    ->whereNotNull('android_fcm_token')
                    ->where('android_fcm_token', '!=', '')
                    ->pluck('android_fcm_token')
                    ->map(fn ($t) => (string) $t)
                    ->all();
                $tokens = array_merge($tokens, $fallback);
            } catch (Throwable) {
            }
        }

        return array_values(array_filter(array_unique($tokens)));
    }

    /**
     * @param  array<int, string>  $userIds
     * @return array<int, string>
     */
    protected function resolveIosLiveActivityTokens(array $userIds, string $column, ?string $eventId = null): array
    {
        if (! Schema::hasTable('user_push_tokens') || ! Schema::hasColumn('user_push_tokens', $column)) {
            return [];
        }

        try {
            $userCol = UserPushToken::getUserIdColumn();
            $query = UserPushToken::query()
                ->whereIn($userCol, $userIds)
                ->whereRaw('LOWER(platform) = ?', ['ios'])
                ->whereNotNull($column)
                ->where($column, '!=', '');

            if ($eventId !== null && $column === 'live_activity_token' && Schema::hasColumn('user_push_tokens', 'live_activity_event_id')) {
                $query->where(function ($q) use ($eventId): void {
                    $q->where('live_activity_event_id', $eventId)
                        ->orWhereNull('live_activity_event_id');
                });
            }

            return array_values(array_filter(array_unique(
                $query->pluck($column)->map(fn ($t) => trim((string) $t))->all()
            )));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Send an HTTP/2 APNs Live Activity push notification signed with ES256 JWT.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function sendApnsLiveActivity(string $deviceToken, array $payload): bool
    {
        $jwt = $this->generateApnsJwt();
        if ($jwt === null) {
            return false;
        }

        $isSandbox = filter_var(env('APNS_USE_SANDBOX', app()->environment('local', 'development', 'staging')), FILTER_VALIDATE_BOOLEAN);
        $bundleId = $isSandbox
            ? (string) (env('APNS_BUNDLE_ID') ?: config('services.apple.dev_bundle_id', 'com.brapps.pgu.dev'))
            : (string) (env('APNS_BUNDLE_ID') ?: config('services.apple.bundle_id', 'com.brapps.pgu'));

        $host = $isSandbox ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com';
        $url = "{$host}/3/device/{$deviceToken}";
        $topic = "{$bundleId}.push-type.liveactivity";

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_HTTPHEADER => [
                'authorization: bearer '.$jwt,
                'apns-push-type: liveactivity',
                'apns-topic: '.$topic,
                'apns-priority: 10',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        }

        Log::warning('[SpeedCollaborationLivePushService] APNs Live Activity push failed', [
            'status_code' => $statusCode,
            'topic' => $topic,
            'response' => is_string($response) ? $response : null,
            'curl_error' => $curlErr ?: null,
        ]);

        return false;
    }

    /**
     * Build an ES256-signed APNs provider authentication JWT from APNS_* or APPLE_* env config.
     */
    protected function generateApnsJwt(): ?string
    {
        $teamId = trim((string) (env('APNS_TEAM_ID') ?: config('services.apple.team_id', '')));
        $keyId = trim((string) (env('APNS_KEY_ID') ?: config('services.apple.key_id', '')));
        $rawKey = (string) (env('APNS_PRIVATE_KEY') ?: config('services.apple.private_key', ''));

        if ($teamId === '' || $keyId === '' || trim($rawKey) === '') {
            return null;
        }

        $pem = str_replace('\n', "\n", $rawKey);
        $privateKey = openssl_pkey_get_private($pem);
        if ($privateKey === false) {
            Log::warning('[SpeedCollaborationLivePushService] Invalid APNs/Apple ES256 private key.');

            return null;
        }

        $header = $this->base64UrlEncode((string) json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = $this->base64UrlEncode((string) json_encode(['iss' => $teamId, 'iat' => time()]));
        $signingInput = "{$header}.{$claims}";

        $derSignature = '';
        if (! openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $rawSignature = $this->derToRawEs256Signature($derSignature);
        if ($rawSignature === null) {
            return null;
        }

        return "{$signingInput}.".$this->base64UrlEncode($rawSignature);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert ASN.1 DER ECDSA signature from openssl_sign into 64-byte raw (R || S) format required by JWT ES256.
     */
    protected function derToRawEs256Signature(string $der): ?string
    {
        $offset = 0;
        $length = strlen($der);
        if ($length < 8 || ord($der[$offset++]) !== 0x30) {
            return null;
        }

        $seqLen = ord($der[$offset++]);
        if (($seqLen & 0x80) !== 0) {
            $lenBytes = $seqLen & 0x7F;
            $offset += $lenBytes;
        }

        if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
            return null;
        }
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
            return null;
        }
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return substr($r, -32).substr($s, -32);
    }
}
