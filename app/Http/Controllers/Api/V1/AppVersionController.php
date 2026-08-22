<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AppVersionRequest;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Throwable;

class AppVersionController extends Controller
{
    public function show(AppVersionRequest $request): JsonResponse
    {
        return $this->checkVersion($request);
    }

    public function checkVersion(AppVersionRequest $request): JsonResponse
    {
        try {
            $androidVersion = AppVersion::query()
                ->where('platform', 'android')
                ->where('is_active', true)
                ->first();

            $iosVersion = AppVersion::query()
                ->where('platform', 'ios')
                ->where('is_active', true)
                ->first();

            $requestedPlatform = $request->validatedPlatform();

            $version = match ($requestedPlatform) {
                'ios' => $iosVersion ?? $androidVersion,
                default => $androidVersion ?? $iosVersion,
            };

            $latestVersion = $version?->latest_version ?? (string) config('app_versions.latest', '2.0.0');
            $minVersion = $version?->min_version ?? (string) config('app_versions.min_required', '1.9.0');
            $updateType = $version?->update_type ?? (string) config('app_versions.update_type', 'force');

            $latestAndroid = $androidVersion?->latest_version ?? $latestVersion;
            $latestIos = $iosVersion?->latest_version ?? $latestVersion;

            return response()->json([
                'status' => true,
                'message' => 'Version check successful',
                'data' => [
                    'latest_version' => $latestVersion,
                    'min_version' => $minVersion,
                    'update_type' => $updateType,
                    'playstore_url' => $this->playStoreUrl(),
                    'appstore_url' => $this->appStoreUrl(),
                    'latest_version_android' => $latestAndroid,
                    'latest_version_ios' => $latestIos,
                ],
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch app version at the moment.',
                'data' => null,
            ], 500);
        }
    }

    private function playStoreUrl(): string
    {
        return (string) config('app_links.android.store_url', 'https://play.google.com/store/apps/details?id=com.peers.peersunity');
    }

    private function appStoreUrl(): string
    {
        return (string) config('app_links.ios.store_url', 'https://apps.apple.com/in/app/peers-global-unity/id6739198477');
    }
}
