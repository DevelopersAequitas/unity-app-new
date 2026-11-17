<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreTutorialRequest;
use App\Models\Tutorial;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends BaseApiController
{
    /**
     * Display a listing of YouTube tutorial videos.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tutorials = Tutorial::orderBy('created_at', 'desc')->get();

            $formatted = $tutorials->map(fn (Tutorial $tutorial): array => [
                'id' => $tutorial->id,
                'video_id' => $tutorial->video_id,
                'youtube_url' => $tutorial->youtube_url,
                'created_at' => $tutorial->created_at->toISOString(),
            ]);

            return $this->success([
                'tutorials' => $formatted,
            ], 'Tutorials fetched successfully');
        } catch (Exception $e) {
            return $this->error('Failed to fetch tutorials: '.$e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created YouTube tutorial.
     */
    public function store(StoreTutorialRequest $request): JsonResponse
    {
        try {
            $tutorial = Tutorial::create($request->validated());

            return $this->success([
                'tutorial' => [
                    'id' => $tutorial->id,
                    'video_id' => $tutorial->video_id,
                    'youtube_url' => $tutorial->youtube_url,
                    'created_at' => $tutorial->created_at->toISOString(),
                ],
            ], 'Tutorial created successfully', 201);
        } catch (Exception $e) {
            return $this->error('Failed to create tutorial: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified tutorial.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tutorial = Tutorial::findOrFail($id);
            $tutorial->delete();

            return $this->success(null, 'Tutorial deleted successfully');
        } catch (Exception $e) {
            return $this->error('Failed to delete tutorial: '.$e->getMessage(), 500);
        }
    }
}
