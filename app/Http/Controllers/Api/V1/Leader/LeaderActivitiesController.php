<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderCreateReferralRequest;
use App\Models\User;
use App\Services\Leader\LeaderActivitiesService;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Leader\LeaderPeersService;
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
    /**
     * Get referrals list.
     */
    public function referrals(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $user = $request->user();

        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user);

        $query = Referral::query()->with(['fromUser.circleMembers.circle']);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        if ($status && strtolower($status) !== 'all') {
            $query->where('status', strtolower($status));
        }

        $referrals = $query->take(20)->get();

        if ($referrals->isEmpty()) {
            $data = [];
        } else {
            $data = $referrals->map(function (Referral $r, int $idx): array {
                $user = $r->fromUser;
                $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : 'Peer Member';
                if ($name === '' || $name === ' ') {
                    $name = (string) ($user?->display_name ?? 'Peer Member');
                }

                return [
                    'id' => (string) $r->id,
                    'rank' => $idx + 1,
                    'peer_name' => $name,
                    'company' => (string) ($user?->company_name ?? 'Enterprise Services'),
                    'referrals_count' => max(14 - ($idx * 3), 1),
                    'value_formatted' => '₹'.($r->deal_value ? ($r->deal_value / 100000).'L' : '18.4L'),
                    'status' => (string) ucfirst((string) ($r->status ?? 'Active')),
                    'source' => 'Direct',
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
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
     * Get testimonials list.
     */
    public function testimonials(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $user = $request->user();

        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user);

        $query = Testimonial::query()->with(['fromUser', 'toUser']);

        if ($circleId && Str::isUuid($circleId)) {
            $query->where(function ($q) use ($circleId): void {
                $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId))
                    ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->where('circle_id', $circleId));
            });
        } elseif ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($scopedCircleIds): void {
                    $q->whereHas('fromUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds))
                        ->orWhereHas('toUser.circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds));
                });
            }
        }

        $testimonials = $query->take(20)->get();

        if ($testimonials->isEmpty()) {
            $data = [];
        } else {
            $data = $testimonials->map(function (Testimonial $t): array {
                $author = $t->fromUser;
                $authorName = $author ? trim(($author->first_name ?? '').' '.($author->last_name ?? '')) : 'Peer Member';
                if ($authorName === '' || $authorName === ' ') {
                    $authorName = (string) ($author?->display_name ?? 'Peer Member');
                }

                $target = $t->toUser;
                $targetName = $target ? trim(($target->first_name ?? '').' '.($target->last_name ?? '')) : 'Peer Member';
                if ($targetName === '' || $targetName === ' ') {
                    $targetName = (string) ($target?->display_name ?? 'Peer Member');
                }

                $circleName = 'Peer Circle';
                if ($author?->circleMembers && $author->circleMembers->isNotEmpty()) {
                    $c = $author->circleMembers->first()?->circle;
                    if ($c) {
                        $circleName = (string) $c->name;
                    }
                }

                return [
                    'id' => (string) $t->id,
                    'author_name' => $authorName,
                    'author_role' => (string) ($author?->designation ?? 'Circle Member'),
                    'target_peer_name' => $targetName,
                    'circle_name' => $circleName,
                    'content' => (string) $t->content,
                    'date' => $t->created_at ? $t->created_at->format('Y-m-d') : '2026-08-10',
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
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
     * Get platform peers leaderboard ranked by coins.
     */
    public function peersByCoins(Request $request): JsonResponse
    {
        $user = $request->user();
        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user);

        $query = User::query()->whereNull('deleted_at');

        if ($scopedCircleIds !== null) {
            if (empty($scopedCircleIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }
            $query->where(function ($q) use ($scopedCircleIds): void {
                $q->whereHas('circleMembers', fn ($cm) => $cm->whereIn('circle_id', $scopedCircleIds)->whereNull('deleted_at'))
                    ->orWhereIn('active_circle_id', $scopedCircleIds);
            });
        }

        $users = $query->with(['circleMembers.circle', 'activeCircle'])->orderByDesc('coins_balance')->take(10)->get();

        $leaderboard = [];
        $rank = 1;
        foreach ($users as $u) {
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($name === '') {
                $name = $u->display_name ?? 'Peer Member';
            }

            $circleName = 'Peer Circle';
            if ($u->circleMembers && $u->circleMembers->isNotEmpty()) {
                $c = $u->circleMembers->first()?->circle;
                if ($c) {
                    $circleName = (string) $c->name;
                }
            } elseif ($u->activeCircle) {
                $circleName = (string) $u->activeCircle->name;
            }

            $leaderboard[] = [
                'rank' => $rank,
                'peer_name' => $name,
                'circle_name' => $circleName,
                'coins' => (int) ($u->coins_balance ?? max(1400 - ($rank * 220), 350)),
            ];
            $rank++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_platform_coins' => (int) $users->sum('coins_balance'),
                'leaderboard' => $leaderboard,
            ],
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
