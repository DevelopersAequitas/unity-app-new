<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderCreateReferralRequest;
use App\Leader\Services\LeaderActivitiesService;
use App\Models\User;
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

    /**
     * Log a life impact activity.
     */
    public function storeImpact(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'impacted_peer_id' => 'nullable|string',
            'to_peer_id' => 'nullable|string',
            'action' => 'required|string|max:255',
            'story_to_share' => 'nullable|string',
            'story' => 'nullable|string',
            'life_impacted' => 'nullable|integer|min:1',
            'lives_impacted' => 'nullable|integer|min:1',
            'impact_date' => 'nullable|date',
            'additional_remarks' => 'nullable|string',
        ]);

        $data = $this->activitiesService->createImpact($user, $validated);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Life impact logged successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Log a 1-on-1 P2P meeting.
     */
    public function storeP2pMeeting(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'peer_id' => 'nullable|string',
            'to_peer_id' => 'nullable|string',
            'peer_user_id' => 'nullable|string',
            'meeting_date' => 'nullable|date',
            'meeting_place' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'notes' => 'nullable|string',
            'media' => 'nullable|array',
        ]);

        $targetPeer = $validated['peer_id'] ?? ($validated['to_peer_id'] ?? ($validated['peer_user_id'] ?? null));
        if (! $targetPeer) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The peer ID is required.',
            ], 422);
        }

        $data = $this->activitiesService->createP2pMeeting($user, $validated);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'P2P meeting logged successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Log a business deal closed between peers.
     */
    public function storeBusinessDeal(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'to_peer_id' => 'nullable|string',
            'peer_user_id' => 'nullable|string',
            'peer_id' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'deal_amount' => 'nullable|numeric|min:0',
            'business_type' => 'nullable|string|max:100',
            'comment' => 'nullable|string',
            'notes' => 'nullable|string',
            'deal_date' => 'nullable|date',
            'referral_id' => 'nullable|string',
        ]);

        $targetPeer = $validated['to_peer_id'] ?? ($validated['peer_user_id'] ?? ($validated['peer_id'] ?? null));
        if (! $targetPeer) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The target peer ID is required.',
            ], 422);
        }

        $amount = $validated['amount'] ?? ($validated['deal_amount'] ?? null);
        if ($amount === null) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The deal amount is required.',
            ], 422);
        }

        $data = $this->activitiesService->createBusinessDeal($user, $validated);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Business deal logged successfully.',
            'data' => $data,
        ], 201);
    }

    /**
     * Submit a testimonial for a peer.
     */
    public function storeTestimonial(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'to_peer_id' => 'nullable|string',
            'peer_user_id' => 'nullable|string',
            'peer_id' => 'nullable|string',
            'content' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'media' => 'nullable|array',
            'referral_id' => 'nullable|string',
        ]);

        $targetPeer = $validated['to_peer_id'] ?? ($validated['peer_user_id'] ?? ($validated['peer_id'] ?? null));
        if (! $targetPeer) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The target peer ID is required.',
            ], 422);
        }

        $data = $this->activitiesService->createTestimonial($user, $validated);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Testimonial submitted successfully.',
            'data' => $data,
        ], 201);
    }
}
