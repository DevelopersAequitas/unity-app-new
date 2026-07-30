<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AppChangelog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            $query->whereIn('platform', [strtolower($platform), 'all']);
        }

        $changelogs = $query->orderBy('released_at', 'desc')
            ->orderBy('version', 'desc')
            ->get();

        $data = $changelogs->map(fn (AppChangelog $log) => [
            'id' => $log->id,
            'version' => $log->version,
            'platform' => $log->platform,
            'title' => $log->title,
            'description' => $log->description,
            'features' => $log->features ?? [],
            'released_at' => $log->released_at ? $log->released_at->toIso8601String() : null,
        ]);

        return $this->success($data, 'App changelogs fetched successfully.');
    }
}
