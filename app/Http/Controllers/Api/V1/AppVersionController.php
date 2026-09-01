<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppVersionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->checkVersion($request);
    }

    public function checkVersion(Request $request): JsonResponse
    {
        try {
            // Read Headers & Query Parameters
            $product = strtolower((string) ($request->query('product') ?? $request->header('X-Product') ?? 'peers'));
            $requestedPlatform = strtolower((string) ($request->query('platform') ?? $request->header('X-Platform') ?? 'android'));
            $bearerToken = $request->bearerToken();

            // If Bearer token is provided, resolve user if necessary
            if ($bearerToken) {
                try {
                    $user = auth('sanctum')->user() ?? $request->user();
                } catch (Throwable) {
                    // Ignore auth resolution errors for version check
                }
            }

            $hasProductCol = Schema::hasColumn('app_versions', 'product');

            $androidQuery = AppVersion::query()->where('platform', 'android');
            $iosQuery = AppVersion::query()->where('platform', 'ios');

            if ($hasProductCol) {
                $androidVersion = (clone $androidQuery)->where('product', $product)->first()
                    ?? $androidQuery->first();
                $iosVersion = (clone $iosQuery)->where('product', $product)->first()
                    ?? $iosQuery->first();
            } else {
                $androidVersion = $androidQuery->first();
                $iosVersion = $iosQuery->first();
            }

            $version = match ($requestedPlatform) {
                'ios' => $iosVersion ?? $androidVersion,
                default => $androidVersion ?? $iosVersion,
            };

            $latestVersion = $version?->latest_version ?? (string) config('app_versions.latest', '1.8.0');
            $minVersion = $version?->min_version ?? (string) config('app_versions.min_required', '1.2.0');
            $updateType = $version?->update_type ?? (string) config('app_versions.update_type', 'optional');
            $isActive = $version !== null ? (bool) $version->is_active : (bool) config('app_versions.is_active', true);
            $releaseNotes = $version?->release_notes ?? "Enhanced security and real-time networking tools\nPerformance optimizations";

            $latestAndroid = $androidVersion?->latest_version ?? $latestVersion;
            $latestIos = $iosVersion?->latest_version ?? $latestVersion;

            return response()->json([
                'status' => true,
                'message' => 'Version check successful',
                'data' => [
                    'latest_version' => $latestVersion,
                    'min_version' => $minVersion,
                    'update_type' => $updateType,
                    'is_active' => (bool) $isActive,
                    'playstore_url' => $this->playStoreUrl(),
                    'appstore_url' => $this->appStoreUrl(),
                    'release_notes' => $releaseNotes,
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
        return (string) config('app_links.android.store_url', 'https://play.google.com/store/apps/details?id=com.peers.peersunity&pcampaignid=web_share');
    }

    private function appStoreUrl(): string
    {
        return (string) config('app_links.ios.store_url', 'https://apps.apple.com/in/app/peers-global-unity/id6739198477');
    }
}
