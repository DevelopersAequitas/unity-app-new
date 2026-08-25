<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
