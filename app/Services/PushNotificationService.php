<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PushNotificationService
{
    public function send(User $toUser, string $title, string $body, array $data = []): void
    {
        SendPushNotificationJob::dispatch($toUser, $title, $body, $data);
    }

    public function sendNow(User $user, string $title, string $body, array $data = [], bool $activeOnly = false): array
    {
        $tokens = $this->tokensForUser($user, $activeOnly);
        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($tokens as $token) {
            $results[] = $this->sendToToken($user, $token, $title, $body, $data);
            ($results[array_key_last($results)]['success'] ?? false) ? $sent++ : $failed++;
        }

        return [
            'success' => $sent > 0,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    public function tokensForUser(User $user, bool $activeOnly = false)
    {
        return $user->pushTokens()
            ->whereNotNull('token')
            ->where('token', '!=', '')
            ->when($activeOnly && Schema::hasColumn('user_push_tokens', 'is_active'), fn ($q) => $q->where('is_active', true))
            ->when(Schema::hasColumn('user_push_tokens', 'last_seen_at'), fn ($q) => $q->orderByDesc('last_seen_at'))
            ->when(Schema::hasColumn('user_push_tokens', 'last_used_at'), fn ($q) => $q->orderByDesc('last_used_at'))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();
    }

    private function sendToToken(User $user, UserPushToken $token, string $title, string $body, array $data): array
    {
        $imageUrl = $data['image_url'] ?? null;
        $hasImage = is_string($imageUrl) && $imageUrl !== '';

        try {
            Log::info('Sending push to token', [
                'token_id' => (string) $token->id,
                'token_masked' => $this->maskToken((string) $token->token),
                'user_id' => (string) $user->id,
                'device_id' => $token->device_id,
                'platform' => $token->platform,
                'notification_type' => $data['notification_type'] ?? ($data['type'] ?? null),
            ]);

            $result = app(FcmService::class)->sendToDevice(
                (string) $token->token,
                $title,
                $body,
                $data,
                null,
                1,
                [
                    'user_id' => (string) $user->id,
                    'device_id' => $token->device_id,
                    'platform' => $token->platform,
                    'device_type' => $token->platform,
                    'notification_type' => $data['notification_type'] ?? ($data['type'] ?? null),
                ],
                $hasImage ? (string) $imageUrl : null,
            );

            $invalidToken = ($result['error'] ?? null) === 'Invalid or unregistered Firebase device token.';

            if ($invalidToken) {
                $token->update(['is_active' => false, 'updated_at' => now()]);
            }

            return [
                'success' => (bool) ($result['success'] ?? false),
                'token_id' => (string) $token->id,
                'token' => (string) $token->token,
                'token_masked' => $this->maskToken((string) $token->token),
                'platform' => $token->platform,
                'device_id' => $token->device_id,
                'app_version' => $token->app_version,
                'provider_message_id' => $result['firebase_response']['name'] ?? null,
                'error' => $result['error'] ?? null,
                'invalid_token' => $invalidToken,
                'response' => $result,
            ];
        } catch (Throwable $throwable) {
            report($throwable);

            return [
                'success' => false,
                'token_id' => (string) $token->id,
                'token' => (string) $token->token,
                'token_masked' => $this->maskToken((string) $token->token),
                'platform' => $token->platform,
                'device_id' => $token->device_id,
                'app_version' => $token->app_version,
                'provider_message_id' => null,
                'error' => $throwable->getMessage(),
                'invalid_token' => false,
                'response' => [
                    'exception' => get_class($throwable),
                    'message' => $throwable->getMessage(),
                    'code' => $throwable->getCode(),
                ],
            ];
        }
    }

    private function maskToken(string $token): string
    {
        return strlen($token) <= 24 ? substr($token, 0, 12) . '...' : substr($token, 0, 12) . '...' . substr($token, -8);
    }

    public function storeAndSend(User $toUser, string $title, string $body, array $payload, array $pushData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $toUser->id,
            'type' => 'activity_update',
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        $this->send($toUser, $title, $body, $pushData);

        return $notification;
    }
}
