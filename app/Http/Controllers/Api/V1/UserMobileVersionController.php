<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AppChangelog;
use App\Models\UserMobileVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserMobileVersionController extends BaseApiController
{
    /**
     * Store or update the authenticated user's mobile app version details
     * and include the latest released App Release / Changelog.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['required', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ]);

        $userId = $request->user()->id;
        $requestedPlatform = strtolower($validated['platform']);

        $userMobileVersion = UserMobileVersion::updateOrCreate(
            [
                'user_id' => $userId,
                'platform' => $requestedPlatform,
            ],
            [
                'app_version' => $validated['app_version'],
                'device_model' => $validated['device_model'] ?? null,
                'os_version' => $validated['os_version'] ?? null,
            ]
        );

        $latestReleaseQuery = AppChangelog::query()
            ->where('is_released', true);

        $latestReleaseQuery->where(function ($q) use ($requestedPlatform): void {
            $q->where('platform', 'like', "%{$requestedPlatform}%")
                ->orWhere('platform', 'like', '%all%');
        });

        $latestRelease = $latestReleaseQuery
            ->orderByRaw(DB::connection()->getDriverName() === 'sqlite' ? 'released_at DESC' : 'released_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->first();

        $latestReleaseData = null;
        if ($latestRelease) {
            $latestReleaseData = [
                'id' => $latestRelease->id,
                'version' => $latestRelease->version,
                'platform' => $latestRelease->platform,
                'title' => $latestRelease->title,
                'description' => $latestRelease->description,
                'features' => $latestRelease->features ?? [],
                'is_released' => (bool) $latestRelease->is_released,
                'released_at' => $latestRelease->released_at ? $latestRelease->released_at->toIso8601String() : null,
            ];
        }

        $responseData = array_merge($userMobileVersion->toArray(), [
            'latest_release' => $latestReleaseData,
        ]);

        return $this->success($responseData, 'User mobile version stored successfully.');
    }
}
