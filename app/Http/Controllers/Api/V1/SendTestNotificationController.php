<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\SendTestNotificationRequest;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Http\JsonResponse;

class SendTestNotificationController extends BaseApiController
{
    /**
     * POST /api/v1/notifications/send-test
     *
     * Sends a test push notification to a user by their email address.
     */
    public function __invoke(SendTestNotificationRequest $request, FcmService $fcmService): JsonResponse
    {
        $email = (string) $request->input('email');
        $title = (string) $request->input('title', 'Test Notification');
        $body = (string) $request->input('body', 'This is a test notification from local backend.');
        $channelId = $request->input('channel_id');

        // Dynamically override default android channel ID if specified in request
        if (is_string($channelId) && $channelId !== '') {
            config(['firebase.default_android_channel_id' => $channelId]);
        }

        $user = User::where('email', $email)->firstOrFail();

        // 1. Create the in-app notification row in app_notifications
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'type' => 'admin_test',
            'category' => 'admin_test',
            'title' => $title,
            'body' => $body,
            'channel' => 'push',
            'priority' => 'high',
            'screen' => 'home',
            'data' => ['screen' => 'home'],
            'status' => 'pending',
        ]);

        // 2. Fetch active push tokens
        $tokens = $fcmService->activeTokensForUser($user->id);

        $attempted = false;
        $success = false;
        $errors = [];
        $tokenResults = [];

        foreach ($tokens as $token) {
            $attempted = true;
            $tokenPreview = substr($token->token, 0, 15).'...';

            try {
                $result = $fcmService->sendToToken($token, $title, $body, $notification->dataPayload(), $notification);

                if ($result['success'] ?? false) {
                    $success = true;
                    $tokenResults[] = [
                        'token_preview' => $tokenPreview,
                        'success' => true,
                        'platform' => $token->platform,
                        'device_id' => $token->device_id,
                    ];
                } else {
                    $err = $result['error'] ?? 'Unknown FCM error';
                    $errors[] = $err;
                    $tokenResults[] = [
                        'token_preview' => $tokenPreview,
                        'success' => false,
                        'error' => $err,
                        'platform' => $token->platform,
                        'device_id' => $token->device_id,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                $tokenResults[] = [
                    'token_preview' => $tokenPreview,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'platform' => $token->platform,
                    'device_id' => $token->device_id,
                ];
            }
        }

        // Update main notification status based on delivery attempts
        $notification->update([
            'status' => $success ? 'sent' : ($attempted ? 'failed' : 'skipped'),
            'sent_at' => $success ? now() : null,
            'failed_at' => (! $success && $attempted) ? now() : null,
            'failure_reason' => (! $success && $attempted) ? implode(', ', $errors) : ($attempted ? null : 'No active push token'),
        ]);

        $message = $success
            ? 'Test notification sent successfully.'
            : ($attempted ? 'FCM push was attempted but failed.' : 'Notification created, but push skipped (no active push tokens).');

        return $this->success([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'title' => $title,
            'body' => $body,
            'channel_id' => config('firebase.default_android_channel_id'),
            'tokens_count' => $tokens->count(),
            'attempted' => $attempted,
            'success' => $success,
            'token_results' => $tokenResults,
        ], $message);
    }
}
