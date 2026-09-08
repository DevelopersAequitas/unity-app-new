<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderCreateReferralRequest;
use App\Models\User;
use App\Services\Leader\LeaderActivitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaderActivitiesController extends Controller
{
    public function __construct(
        private readonly LeaderActivitiesService $activitiesService,
    ) {}

    /**
     * Get impacts list with full peer details.
     */
    public function impacts(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getImpacts($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Impacts retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get P2P meetings list with full peer details.
     */
    public function p2pMeetings(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getP2pMeetings($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'P2P meetings retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get business deals list with full peer details.
     */
    public function businessDeals(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getBusinessDeals($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Business deals retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get referrals list with full peer details.
     */
    public function referrals(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getReferrals($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Referrals retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get testimonials list with full peer details.
     */
    public function testimonials(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getTestimonials($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Testimonials retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get platform peers leaderboard ranked by coins with full peer details.
     */
    public function peersByCoins(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getPeersByCoins($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Peers by coins retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get requirements list with full peer details.
     */
    public function requirements(Request $request): JsonResponse
    {
        $data = $this->activitiesService->getRequirements($request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Requirements retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Submit a new business referral on behalf of a peer.
     */
    public function storeReferral(LeaderCreateReferralRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $targetPeerId = trim((string) $validated['to_peer_id']);
        $targetUser = User::query()
            ->where('id', $targetPeerId)
            ->orWhere('email', $targetPeerId)
            ->orWhere('phone', $targetPeerId)
            ->first();

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => "The selected peer ID '{$targetPeerId}' does not exist. Please provide a valid peer UUID from your users table.",
            ], 422);
        }

        $targetUserId = (string) $targetUser->id;
        $id = (string) Str::uuid();

        $remarks = trim((string) ($validated['notes'] ?? ''));
        if (! empty($validated['estimated_deal_value'])) {
            $dealStr = 'Estimated Value: '.$validated['estimated_deal_value'];
            $remarks = $remarks !== '' ? $remarks.' ('.$dealStr.')' : $dealStr;
        }

        DB::table('referrals')->insert([
            'id' => $id,
            'from_user_id' => $user->id,
            'to_user_id' => $targetUserId,
            'referral_type' => 'b2b_referral',
            'referral_date' => now()->toDateString(),
            'referral_of' => $validated['prospect_name'],
            'phone' => $validated['prospect_phone'] ?? null,
            'email' => $validated['prospect_email'] ?? null,
            'address' => $validated['prospect_company'] ?? null,
            'hot_value' => 3,
            'remarks' => $remarks !== '' ? $remarks : null,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Referral created and forwarded to peer.',
            'data' => [
                'referral_id' => $id,
                'status' => 'Pending',
            ],
        ], 201);
    }
}
