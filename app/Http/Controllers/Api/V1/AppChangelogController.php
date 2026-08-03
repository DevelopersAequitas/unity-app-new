<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreAppReleaseRequest;
use App\Models\AppChangelog;
use App\Services\AppReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppChangelogController extends BaseApiController
{
    /**
     * Retrieve the list of released app changelogs and new features.
     */
    public function index(Request $request): JsonResponse
    {
        $platform = $request->query('platform');

        $query = AppChangelog::query()
            ->where('is_released', true);

        if ($platform && in_array(strtolower($platform), ['android', 'ios'], true)) {
            $platLower = strtolower($platform);

            if (DB::connection()->getDriverName() === 'sqlite') {
                $query->where(function ($q) use ($platLower): void {
                    $q->whereJsonContains('platform', ucfirst($platLower))
                        ->orWhereJsonContains('platform', $platLower)
                        ->orWhere('platform', 'like', "%{$platLower}%");
                });
            } else {
                $query->whereRaw(
                    'EXISTS (
                        SELECT 1
                        FROM jsonb_array_elements_text(app_changelogs.platform) AS platform_value
                        WHERE LOWER(platform_value) = ?
                    )',
                    [$platLower]
                );
            }
        }

        $changelogs = $query
            ->orderByRaw(DB::connection()->getDriverName() === 'sqlite' ? 'released_at DESC' : 'released_at DESC NULLS LAST')
            ->orderByDesc('version')
            ->get();

        $data = $changelogs->map(fn (AppChangelog $log) => [
            'id' => $log->id,
            'version' => $log->version,
            'platform' => $log->platform,
            'title' => $log->title,
            'description' => $log->description,
            'features' => $log->features ?? [],
            'is_released' => (bool) $log->is_released,
            'released_at' => $log->released_at ? $log->released_at->toIso8601String() : null,
        ]);

        return $this->success($data, 'App changelogs fetched successfully.');
    }

    /**
     * Create a new App Release / Changelog.
     */
    public function store(StoreAppReleaseRequest $request, AppReleaseService $service): JsonResponse
    {
        $changelog = $service->createRelease($request->validated());

        $data = [
            'id' => $changelog->id,
            'version' => $changelog->version,
            'platform' => $changelog->platform,
            'title' => $changelog->title,
            'description' => $changelog->description,
            'features' => $changelog->features ?? [],
            'is_released' => (bool) $changelog->is_released,
            'released_at' => $changelog->released_at ? $changelog->released_at->toIso8601String() : null,
            'created_at' => $changelog->created_at ? $changelog->created_at->toIso8601String() : null,
            'updated_at' => $changelog->updated_at ? $changelog->updated_at->toIso8601String() : null,
        ];

        return $this->success($data, 'App release created successfully.');
    }
}
