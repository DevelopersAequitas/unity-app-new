<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntroVideoController extends Controller
{
    /**
     * Store or update the authenticated user's intro video.
     *
     * Accepts an intro_video_id (UUID of an already-uploaded file)
     * and persists it as the user's profile_video_id.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'intro_video_id' => ['required', 'uuid', 'exists:files,id'],
        ]);

        $user = $request->user();
        $user->profile_video_id = $request->input('intro_video_id');
        $user->saveOrFail();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Intro video updated successfully',
            'data' => $this->formatResponse($user),
        ]);
    }

    /**
     * Get the authenticated user's current intro video.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Intro video fetched successfully',
            'data' => $this->formatResponse($user),
        ]);
    }

    /**
     * Remove the authenticated user's intro video.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->profile_video_id = null;
        $user->saveOrFail();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Intro video removed successfully',
            'data' => $this->formatResponse($user),
        ]);
    }

    /**
     * Get all users' intro videos.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $users = User::paginate($perPage);
        $data = collect($users->items())->map(fn ($user) => $this->formatResponse($user))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'All users fetched successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }


    /**
     * Format the response with the user's intro video details.
     */
    private function formatResponse($user): array
    {
        $profilePhotoId = $user->profile_photo_file_id ?? $user->profile_photo_id;
        $introVideoId = $user->profile_video_id;

        return [
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'profile_photo_url' => $profilePhotoId
                ? url('/api/v1/files/' . $profilePhotoId)
                : null,
            'intro_video_id' => $introVideoId,
            'intro_video_url' => $introVideoId
                ? url('/api/v1/files/' . $introVideoId)
                : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
