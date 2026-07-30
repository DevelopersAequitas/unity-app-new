<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpsertAppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Throwable;

class AppVersionController extends Controller
{
    private const PLATFORMS = ['android', 'ios'];

    public function upsert(UpsertAppVersionRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();

            $androidLatest = $payload['latest_version_android'] ?? $payload['latest_version'] ?? null;
            $iosLatest = $payload['latest_version_ios'] ?? $payload['latest_version'] ?? null;
            $defaultLatest = $payload['latest_version'] ?? $androidLatest ?? $iosLatest ?? '';

            foreach (self::PLATFORMS as $platform) {
                $latestForPlatform = match ($platform) {
                    'android' => $androidLatest ?? $defaultLatest,
                    'ios' => $iosLatest ?? $defaultLatest,
                    default => $defaultLatest,
                };

                AppVersion::updateOrCreate(
                    ['platform' => $platform],
                    [
                        'latest_version' => $latestForPlatform,
                        'min_version' => $payload['min_version'],
                        'update_type' => $payload['update_type'],
                        'is_active' => true,
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'App version updated successfully',
                'data' => [
                    'latest_version_android' => $androidLatest ?? $defaultLatest,
                    'latest_version_ios' => $iosLatest ?? $defaultLatest,
                    'min_version' => $payload['min_version'],
                    'update_type' => $payload['update_type'],
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update app version at the moment',
                'data' => null,
            ], 500);
        }
    }
}
