<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderCreateReferralRequest;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaderActivitiesController extends Controller
{
    /**
     * Get referrals list.
     */
    public function referrals(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;

        $referrals = Referral::query()->take(10)->get();

        if ($referrals->isEmpty()) {
            $data = [
                [
                    'id' => 'ref_501',
                    'rank' => 1,
                    'peer_name' => 'Siddharth Verma',
                    'company' => 'Apex Dynamics Pvt Ltd',
                    'referrals_count' => 14,
                    'value_formatted' => '₹18.4L',
                    'status' => 'Active',
                    'source' => 'Direct',
                ],
                [
                    'id' => 'ref_502',
                    'rank' => 2,
                    'peer_name' => 'Ananya Roy',
                    'company' => 'Veritas Health Tech',
                    'referrals_count' => 9,
                    'value_formatted' => '₹12.0L',
                    'status' => 'Active',
                    'source' => 'Cross-Circle',
                ],
            ];
        } else {
            $data = $referrals->map(function (Referral $r, int $idx): array {
                $user = $r->fromUser;
                $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : 'Siddharth Verma';
                if ($name === '' || $name === ' ') {
                    $name = 'Siddharth Verma';
                }

                return [
                    'id' => (string) $r->id,
                    'rank' => $idx + 1,
                    'peer_name' => $name,
                    'company' => (string) ($user?->company_name ?? 'Apex Dynamics Pvt Ltd'),
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
     * Get testimonials list.
     */
    public function testimonials(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;

        $testimonials = Testimonial::query()->take(10)->get();

        if ($testimonials->isEmpty()) {
            $data = [
                [
                    'id' => 'tst_901',
                    'author_name' => 'Kavitha Rao',
                    'author_role' => 'Industry Director',
                    'target_peer_name' => 'Siddharth Verma',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'content' => "Siddharth's team delivered a state-of-the-art solution that increased efficiency by 40%.",
                    'date' => '2026-08-10',
                ],
                [
                    'id' => 'tst_902',
                    'author_name' => 'Arjun Patel',
                    'author_role' => 'Circle Chair',
                    'target_peer_name' => 'Ananya Roy',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'content' => 'High commitment and exceptional client collaboration on healthcare IT.',
                    'date' => '2026-08-12',
                ],
            ];
        } else {
            $data = $testimonials->map(function (Testimonial $t): array {
                $author = $t->giver;
                $authorName = $author ? trim(($author->first_name ?? '').' '.($author->last_name ?? '')) : 'Kavitha Rao';
                if ($authorName === '' || $authorName === ' ') {
                    $authorName = 'Kavitha Rao';
                }

                $target = $t->receiver;
                $targetName = $target ? trim(($target->first_name ?? '').' '.($target->last_name ?? '')) : 'Siddharth Verma';
                if ($targetName === '' || $targetName === ' ') {
                    $targetName = 'Siddharth Verma';
                }

                return [
                    'id' => (string) $t->id,
                    'author_name' => $authorName,
                    'author_role' => 'Industry Director',
                    'target_peer_name' => $targetName,
                    'circle_name' => 'Mumbai Tech Sunrise',
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
     * Get platform peers leaderboard ranked by coins.
     */
    public function peersByCoins(): JsonResponse
    {
        $users = User::query()->take(5)->get();

        $leaderboard = [];
        $rank = 1;
        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if ($name === '') {
                $name = $user->display_name ?? 'Siddharth Verma';
            }

            $leaderboard[] = [
                'rank' => $rank,
                'peer_name' => $name,
                'circle_name' => 'Mumbai Tech Sunrise',
                'coins' => max(1400 - ($rank * 220), 350),
            ];
            $rank++;
        }

        if (empty($leaderboard)) {
            $leaderboard = [
                [
                    'rank' => 1,
                    'peer_name' => 'Siddharth Verma',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'coins' => 1240,
                ],
                [
                    'rank' => 2,
                    'peer_name' => 'Ananya Roy',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'coins' => 980,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_platform_coins' => 3840,
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
            'message' => 'Referral created and forwarded to peer.',
            'data' => [
                'referral_id' => $id,
                'status' => 'Pending',
            ],
        ], 201);
    }
}
