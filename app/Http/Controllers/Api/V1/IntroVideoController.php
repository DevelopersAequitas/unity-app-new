<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

        // Award coins for first-time intro video upload (idempotent duplicate protection)
        $coinsService = app(\App\Services\Coins\CoinsService::class);
        $coinsLedger = $coinsService->rewardForIntroVideo($user);
        if ($coinsLedger) {
            $user->refresh();

            $impactPoints = (int) config('impact.activity_rewards.introduction_video', 1);
            if ($impactPoints > 0) {
                app(\App\Services\LifeImpact\LifeImpactService::class)->addLifeImpact(
                    (string) $user->id,
                    (string) $user->id,
                    'introduction_video',
                    (string) $user->profile_video_id,
                    $impactPoints,
                    'Uploaded an introduction video',
                    'Life impact awarded for uploading profile introduction video.'
                );
            }
        }

        $totalLifeImpact = app(\App\Services\LifeImpact\LifeImpactService::class)->getCurrentTotal((string) $user->id);
        $responseData = $this->formatResponse($user);

        $coinsEarned = $coinsLedger ? (int) $coinsLedger->amount : 0;
        $coinsBalance = (int) $user->coins_balance;
        $impactEarned = $coinsLedger ? (int) config('impact.activity_rewards.introduction_video', 1) : 0;

        $responseData['coins'] = [
            'earned' => $coinsEarned,
            'balance_after' => $coinsBalance,
        ];
        $responseData['impacts'] = [
            'earned' => $impactEarned,
            'total' => $totalLifeImpact,
            'balance_after' => $totalLifeImpact,
        ];
        $responseData['life_impact'] = [
            'earned' => $impactEarned,
            'total' => $totalLifeImpact,
            'balance_after' => $totalLifeImpact,
        ];
        $responseData['life_impacted_count'] = $totalLifeImpact;
        if ($coinsLedger) {
            $responseData['coins_earned'] = $coinsEarned;
            $responseData['coins_balance'] = $coinsBalance;
        }

        return response()->json([
            'success' => true,
            'message' => 'Intro video updated successfully',
            'data' => $responseData,
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
        $query = User::query()
            ->whereNotNull('profile_video_id')
            ->latest();

        if ($request->boolean('all', false) || $request->input('per_page') === 'all') {
            $users = $query->get();
            $data = $users->map(fn (User $user): array => $this->formatResponse($user))->values()->all();

            return response()->json([
                'success' => true,
                'message' => 'Intro videos fetched successfully',
                'data' => $data,
            ]);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $users = $query->paginate($perPage);
        $data = collect($users->items())->map(fn (User $user): array => $this->formatResponse($user))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Intro videos fetched successfully',
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
    private function formatResponse(User $user): array
    {
        $introVideoId = $user->profile_video_id;

        $displayName = $user->display_name;
        if (blank($displayName)) {
            $displayName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        }

        return [
            'user_id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $displayName,
            'email' => $user->email,
            'profile_photo_url' => $user->profile_photo_url,
            'intro_video_id' => $introVideoId,
            'intro_video_url' => $user->profile_video_url,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
