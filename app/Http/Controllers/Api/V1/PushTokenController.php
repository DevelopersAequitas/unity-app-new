<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PushTokenController extends BaseApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'required_without:fcm_token', 'string'],
            'fcm_token' => ['nullable', 'required_without:token', 'string'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'device_id' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $user = $request->user();
            $token = (string) ($validated['fcm_token'] ?? $validated['token']);

<<<<<<< Updated upstream
            UserPushToken::where('token', $token)
                ->where(UserPushToken::getUserIdColumn(), '!=', $user->id)
                ->delete();
=======
            if (Schema::hasTable('user_push_tokens')) {
                UserPushToken::where('token', $token)
                    ->where('user_id', '!=', $user->id)
                    ->delete();
            }
>>>>>>> Stashed changes

            if (filled($validated['device_id'] ?? null)) {
                UserPushToken::where('device_id', $validated['device_id'])
                    ->where(UserPushToken::getUserIdColumn(), $user->id)
                    ->where('token', '!=', $token)
                    ->delete();
            }

            $updates = [
                'platform' => $validated['platform'],
            ];

            if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                $updates['last_seen_at'] = now();
            }
            if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                $updates['last_used_at'] = now();
            }

            if (array_key_exists('device_id', $validated) && Schema::hasColumn('user_push_tokens', 'device_id')) {
                $updates['device_id'] = $validated['device_id'];
            }

            if (array_key_exists('app_version', $validated) && Schema::hasColumn('user_push_tokens', 'app_version')) {
                $updates['app_version'] = $validated['app_version'];
            }

<<<<<<< Updated upstream
            // Always activate/reset states when registered explicitly by the client device
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_push_tokens', 'is_active')) {
                $updates['is_active'] = true;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_push_tokens', 'status')) {
                $updates['status'] = 'active';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_push_tokens', 'token_status')) {
                $updates['token_status'] = 'active';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_push_tokens', 'failed_at')) {
                $updates['failed_at'] = null;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('user_push_tokens', 'failure_reason')) {
                $updates['failure_reason'] = null;
            }
=======
            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $updates['is_active'] = true;
            }
>>>>>>> Stashed changes

            $pushToken = UserPushToken::updateOrCreate(
                [
                    'token' => $token,
                ],
                array_merge($updates, [
                    UserPushToken::getUserIdColumn() => $user->id,
                ])
            );

            return $this->success([
                'id' => $pushToken->id,
                'token' => $pushToken->token,
                'fcm_token' => $pushToken->token,
                'platform' => $pushToken->platform,
                'device_id' => $pushToken->device_id ?? null,
                'app_version' => $pushToken->app_version ?? null,
                'last_seen_at' => $pushToken->last_seen_at ?? null,
            ], 'Push token saved successfully');
        } catch (Throwable $throwable) {
            Log::error('PushTokenController store exception: ' . $throwable->getMessage(), [
                'trace' => $throwable->getTraceAsString(),
                'user_id' => isset($user) ? $user->id : null,
                'token' => isset($token) ? $token : null,
            ]);
            report($throwable);

            return $this->error('Unable to save push token', 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'required_without:fcm_token', 'string'],
            'fcm_token' => ['nullable', 'required_without:token', 'string'],
        ]);

        try {
            $token = (string) ($validated['fcm_token'] ?? $validated['token']);

            $deleted = UserPushToken::where(UserPushToken::getUserIdColumn(), $request->user()->id)
                ->where('token', $token)
                ->delete();

            return $this->success([
                'deleted' => $deleted > 0,
            ], 'Push token deleted successfully');
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->error('Unable to delete push token', 500);
        }
    }
}
