<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates activity metrics for a circle and its individual members.
 *
 * Since none of the activity tables (p2p_meetings, business_deals, referrals, etc.)
 * carry a circle_id, we resolve the set of approved member user_ids first and
 * then run a single aggregation pass per table.
 */
class CircleActivityMetricsService
{
    /**
     * Returns aggregated activity totals for the entire circle.
     *
     * @return array{
     *   p2p_meetings_count: int,
     *   business_deals_given: int,
     *   testimonials_given: int,
     *   collaborations_count: int,
     *   get_help_count: int,
     *   referrals_asks_count: int,
     *   referrals_given_count: int,
     *   badges_count: int,
     *   life_impacted_count: int,
     * }
     */
    public function forCircle(Circle $circle): array
    {
        $userIds = $this->resolveMemberUserIds($circle->id);

        if ($userIds->isEmpty()) {
            return $this->emptyMetrics();
        }

        return [
            'p2p_meetings_count' => $this->countP2pMeetings($userIds),
            'business_deals_given' => $this->countBusinessDeals($userIds),
            'testimonials_given' => $this->countTestimonials($userIds),
            'collaborations_count' => $this->countCollaborations($userIds),
            'get_help_count' => $this->countGetHelp($userIds),
            'referrals_asks_count' => $this->countReferralAsks($userIds),
            'referrals_given_count' => $this->countReferralsGiven($userIds),
            'badges_count' => $this->countBadges($userIds),
            'life_impacted_count' => $this->sumLifeImpacted($userIds),
        ];
    }

    /**
     * Returns per-member activity metrics keyed by user_id.
     *
     * @return array<string, array<string, int>>
     */
    public function perMember(Circle $circle): array
    {
        $userIds = $this->resolveMemberUserIds($circle->id);

        if ($userIds->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($userIds as $uid) {
            $result[(string) $uid] = $this->emptyMemberMetrics();
        }

        // P2P meetings (initiator or peer)
        if (Schema::hasTable('p2p_meetings')) {
            $rows = DB::table('p2p_meetings')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($userIds): void {
                    $q->whereIn('initiator_user_id', $userIds)
                        ->orWhereIn('peer_user_id', $userIds);
                })
                ->select(['initiator_user_id', 'peer_user_id'])
                ->get();

            foreach ($rows as $row) {
                if (isset($result[(string) $row->initiator_user_id])) {
                    $result[(string) $row->initiator_user_id]['p2p_meetings_count']++;
                }
                if (isset($result[(string) $row->peer_user_id])) {
                    $result[(string) $row->peer_user_id]['p2p_meetings_count']++;
                }
            }
        }

        // Business deals given (from_user_id)
        if (Schema::hasTable('business_deals')) {
            $rows = DB::table('business_deals')
                ->whereNull('deleted_at')
                ->whereIn('from_user_id', $userIds)
                ->select(DB::raw('from_user_id, COUNT(*) as cnt'))
                ->groupBy('from_user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->from_user_id])) {
                    $result[(string) $row->from_user_id]['business_deals_given'] = (int) $row->cnt;
                }
            }
        }

        // Testimonials given (from_user_id)
        if (Schema::hasTable('testimonials')) {
            $rows = DB::table('testimonials')
                ->whereNull('deleted_at')
                ->whereIn('from_user_id', $userIds)
                ->select(DB::raw('from_user_id, COUNT(*) as cnt'))
                ->groupBy('from_user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->from_user_id])) {
                    $result[(string) $row->from_user_id]['testimonials_given'] = (int) $row->cnt;
                }
            }
        }

        // Referrals given (from_user_id)
        if (Schema::hasTable('referrals')) {
            $rows = DB::table('referrals')
                ->whereNull('deleted_at')
                ->whereIn('from_user_id', $userIds)
                ->select(DB::raw('from_user_id, COUNT(*) as cnt'))
                ->groupBy('from_user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->from_user_id])) {
                    $result[(string) $row->from_user_id]['referrals_given'] = (int) $row->cnt;
                }
            }

            // Referral asks (to_user_id)
            $rows = DB::table('referrals')
                ->whereNull('deleted_at')
                ->whereIn('to_user_id', $userIds)
                ->select(DB::raw('to_user_id, COUNT(*) as cnt'))
                ->groupBy('to_user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->to_user_id])) {
                    $result[(string) $row->to_user_id]['referrals_asks'] = (int) $row->cnt;
                }
            }
        }

        // Collaborations (user_id)
        if (Schema::hasTable('collaboration_posts')) {
            $rows = DB::table('collaboration_posts')
                ->whereIn('user_id', $userIds)
                ->whereNotIn('status', ['deleted'])
                ->select(DB::raw('user_id, COUNT(*) as cnt'))
                ->groupBy('user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->user_id])) {
                    $result[(string) $row->user_id]['collaborations_count'] = (int) $row->cnt;
                }
            }
        }

        // Get Help / Requirements (user_id)
        if (Schema::hasTable('requirements')) {
            $rows = DB::table('requirements')
                ->whereNull('deleted_at')
                ->whereIn('user_id', $userIds)
                ->select(DB::raw('user_id, COUNT(*) as cnt'))
                ->groupBy('user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->user_id])) {
                    $result[(string) $row->user_id]['get_help_count'] = (int) $row->cnt;
                }
            }
        }

        // Badges (user_id, earned status)
        if (Schema::hasTable('user_milestone_badges')) {
            $rows = DB::table('user_milestone_badges')
                ->whereIn('user_id', $userIds)
                ->where('status', 'earned')
                ->select(DB::raw('user_id, COUNT(*) as cnt'))
                ->groupBy('user_id')
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->user_id])) {
                    $result[(string) $row->user_id]['badges_count'] = (int) $row->cnt;
                }
            }
        }

        // Life impacted (from users.life_impacted_count)
        if (Schema::hasTable('users')) {
            $rows = DB::table('users')
                ->whereIn('id', $userIds)
                ->select(['id', DB::raw('COALESCE(life_impacted_count, 0) as life_impacted_count')])
                ->get();
            foreach ($rows as $row) {
                if (isset($result[(string) $row->id])) {
                    $result[(string) $row->id]['life_impacted_count'] = (int) $row->life_impacted_count;
                }
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Circle-level aggregation helpers
    // -------------------------------------------------------------------------

    private function countP2pMeetings(Collection $userIds): int
    {
        if (! Schema::hasTable('p2p_meetings')) {
            return 0;
        }

        return (int) DB::table('p2p_meetings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userIds): void {
                $q->whereIn('initiator_user_id', $userIds)
                    ->orWhereIn('peer_user_id', $userIds);
            })
            ->count();
    }

    private function countBusinessDeals(Collection $userIds): int
    {
        if (! Schema::hasTable('business_deals')) {
            return 0;
        }

        return (int) DB::table('business_deals')
            ->whereNull('deleted_at')
            ->whereIn('from_user_id', $userIds)
            ->count();
    }

    private function countTestimonials(Collection $userIds): int
    {
        if (! Schema::hasTable('testimonials')) {
            return 0;
        }

        return (int) DB::table('testimonials')
            ->whereNull('deleted_at')
            ->whereIn('from_user_id', $userIds)
            ->count();
    }

    private function countCollaborations(Collection $userIds): int
    {
        if (! Schema::hasTable('collaboration_posts')) {
            return 0;
        }

        return (int) DB::table('collaboration_posts')
            ->whereIn('user_id', $userIds)
            ->whereNotIn('status', ['deleted'])
            ->count();
    }

    private function countGetHelp(Collection $userIds): int
    {
        if (! Schema::hasTable('requirements')) {
            return 0;
        }

        return (int) DB::table('requirements')
            ->whereNull('deleted_at')
            ->whereIn('user_id', $userIds)
            ->count();
    }

    private function countReferralAsks(Collection $userIds): int
    {
        if (! Schema::hasTable('referrals')) {
            return 0;
        }

        return (int) DB::table('referrals')
            ->whereNull('deleted_at')
            ->whereIn('to_user_id', $userIds)
            ->count();
    }

    private function countReferralsGiven(Collection $userIds): int
    {
        if (! Schema::hasTable('referrals')) {
            return 0;
        }

        return (int) DB::table('referrals')
            ->whereNull('deleted_at')
            ->whereIn('from_user_id', $userIds)
            ->count();
    }

    private function countBadges(Collection $userIds): int
    {
        if (! Schema::hasTable('user_milestone_badges')) {
            return 0;
        }

        return (int) DB::table('user_milestone_badges')
            ->whereIn('user_id', $userIds)
            ->where('status', 'earned')
            ->count();
    }

    private function sumLifeImpacted(Collection $userIds): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        return (int) DB::table('users')
            ->whereIn('id', $userIds)
            ->sum(DB::raw('COALESCE(life_impacted_count, 0)'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns approved, non-regional member user_ids for a circle.
     *
     * @return Collection<int, string>
     */
    private function resolveMemberUserIds(string $circleId): Collection
    {
        return CircleMember::query()
            ->where('circle_id', $circleId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereNotIn(DB::raw('LOWER(circle_members.role::text)'), CircleMember::REGIONAL_ROLES)
            ->whereHas('user', function ($q): void {
                $q->where(function ($sq): void {
                    $sq->whereNull('status')->orWhere('status', 'active');
                })->where('status', '!=', 'inactive')
                    ->whereNull('deleted_at');
            })
            ->pluck('user_id');
    }

    /** @return array<string, int> */
    private function emptyMetrics(): array
    {
        return [
            'p2p_meetings_count' => 0,
            'business_deals_given' => 0,
            'testimonials_given' => 0,
            'collaborations_count' => 0,
            'get_help_count' => 0,
            'referrals_asks_count' => 0,
            'referrals_given_count' => 0,
            'badges_count' => 0,
            'life_impacted_count' => 0,
        ];
    }

    /** @return array<string, int> */
    private function emptyMemberMetrics(): array
    {
        return [
            'p2p_meetings_count' => 0,
            'business_deals_given' => 0,
            'testimonials_given' => 0,
            'referrals_given' => 0,
            'referrals_asks' => 0,
            'collaborations_count' => 0,
            'get_help_count' => 0,
            'badges_count' => 0,
            'life_impacted_count' => 0,
        ];
    }
}
