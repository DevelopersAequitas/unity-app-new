<?php

declare(strict_types=1);

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
            'token' => ['nullable', 'required_without_all:fcm_token,live_activity_push_to_start_token,live_activity_token', 'string'],
            'fcm_token' => ['nullable', 'required_without_all:token,live_activity_push_to_start_token,live_activity_token', 'string'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'device_id' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'app' => ['nullable', 'string', 'max:50'],
            'app_type' => ['nullable', 'string', 'max:50'],
            'live_activity_token_type' => ['nullable', 'string', 'in:push_to_start,activity_update'],
            'live_activity_push_to_start_token' => ['nullable', 'string', 'max:512'],
            'live_activity_token' => ['nullable', 'string', 'max:512'],
            'event_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $user = $request->user();
            $rawToken = $validated['fcm_token'] ?? $validated['token'] ?? null;
            $token = is_string($rawToken) && trim($rawToken) !== '' ? trim($rawToken) : null;
            $app = (string) ($validated['app'] ?? $validated['app_type'] ?? 'peers');
            $deviceId = $validated['device_id'] ?? null;
            if ($app === 'leader' && filled($deviceId) && ! str_starts_with((string) $deviceId, 'leader_')) {
                $deviceId = 'leader_'.$deviceId;
            }

            $liveActivityUpdates = [];
            if (! empty($validated['live_activity_push_to_start_token']) && Schema::hasColumn('user_push_tokens', 'live_activity_push_to_start_token')) {
                $liveActivityUpdates['live_activity_push_to_start_token'] = trim((string) $validated['live_activity_push_to_start_token']);
            }
            if (! empty($validated['live_activity_token']) && Schema::hasColumn('user_push_tokens', 'live_activity_token')) {
                $liveActivityUpdates['live_activity_token'] = trim((string) $validated['live_activity_token']);
            }
            if (! empty($validated['event_id']) && Schema::hasColumn('user_push_tokens', 'live_activity_event_id')) {
                $liveActivityUpdates['live_activity_event_id'] = trim((string) $validated['event_id']);
            }

            // Handle iOS Live Activity token-only registration (without fcm_token/token)
            if ($token === null && $liveActivityUpdates !== []) {
                $userIdCol = UserPushToken::getUserIdColumn();
                $existingQuery = UserPushToken::query()
                    ->where($userIdCol, $user->id)
                    ->whereRaw('LOWER(platform) = ?', [strtolower((string) $validated['platform'])]);

                if (filled($deviceId) && Schema::hasColumn('user_push_tokens', 'device_id')) {
                    $existingQuery->where('device_id', $deviceId);
                }

                $pushToken = $existingQuery->latest('updated_at')->first();

                if (! $pushToken) {
                    $seed = (string) ($validated['live_activity_push_to_start_token'] ?? $validated['live_activity_token'] ?? uniqid('', true));
                    $syntheticToken = 'apns_live_activity:'.$user->id.':'.substr(hash('sha256', $seed), 0, 24);
                    $createData = array_merge([
                        $userIdCol => $user->id,
                        'token' => $syntheticToken,
                        'platform' => strtolower((string) $validated['platform']),
                    ], $liveActivityUpdates);

                    if (Schema::hasColumn('user_push_tokens', 'app')) {
                        $createData['app'] = $app;
                    }
                    if (filled($deviceId) && Schema::hasColumn('user_push_tokens', 'device_id')) {
                        $createData['device_id'] = $deviceId;
                    }
                    if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                        $createData['is_active'] = true;
                    }
                    if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                        $createData['last_seen_at'] = now();
                    }
                    if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                        $createData['last_used_at'] = now();
                    }

                    $pushToken = UserPushToken::create($createData);
                } else {
                    if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                        $liveActivityUpdates['last_seen_at'] = now();
                    }
                    if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                        $liveActivityUpdates['last_used_at'] = now();
                    }
                    $pushToken->update($liveActivityUpdates);
                }

                return $this->success([
                    'id' => $pushToken->id,
                    'platform' => $pushToken->platform,
                    'live_activity_token_type' => $validated['live_activity_token_type'] ?? null,
                    'live_activity_push_to_start_token' => $pushToken->live_activity_push_to_start_token ?? null,
                    'live_activity_token' => $pushToken->live_activity_token ?? null,
                    'event_id' => $pushToken->live_activity_event_id ?? ($validated['event_id'] ?? null),
                ], 'Live Activity push token saved successfully');
            }

            if (Schema::hasTable('user_push_tokens')) {
                UserPushToken::where('token', $token)
                    ->where(UserPushToken::getUserIdColumn(), '!=', $user->id)
                    ->delete();
            }

            if (filled($deviceId)) {
                UserPushToken::where('device_id', $deviceId)
                    ->where(UserPushToken::getUserIdColumn(), $user->id)
                    ->where('token', '!=', $token)
                    ->delete();
            }

            $updates = array_merge([
                'platform' => $validated['platform'],
            ], $liveActivityUpdates);

            if (Schema::hasColumn('user_push_tokens', 'app')) {
                $updates['app'] = $app;
            }

            if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                $updates['last_seen_at'] = now();
            }
            if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                $updates['last_used_at'] = now();
            }

            if (filled($deviceId) && Schema::hasColumn('user_push_tokens', 'device_id')) {
                $updates['device_id'] = $deviceId;
            }

            if (array_key_exists('app_version', $validated) && Schema::hasColumn('user_push_tokens', 'app_version')) {
                $updates['app_version'] = $validated['app_version'];
            }

            // Always activate/reset states when registered explicitly by the client device
            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $updates['is_active'] = true;
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
            Log::error('PushTokenController store exception: '.$throwable->getMessage(), [
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
