<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\ActivityVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ActivityVideoApiController extends BaseApiController
{
    /**
     * Retrieve all active activity video configurations for Flutter/Mobile app.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('activity_videos')) {
            return $this->success([], 'Activity videos retrieved successfully');
        }

        $videos = ActivityVideo::query()
            ->active()
            ->whereNotNull('video_url')
            ->where('video_url', '!=', '')
            ->orderBy('activity_name')
            ->get();

        $data = $videos->map(function (ActivityVideo $video) {
            return [
                'activity_id'   => $video->activity_key,
                'activity_name' => $video->activity_name,
                'video_type'    => $video->video_type,
                'video_url'     => $video->video_url,
                'is_active'     => (bool) $video->is_active,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Activity videos retrieved successfully',
            'data'    => $data,
        ]);
    }
}
