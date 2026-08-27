<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\AdminUser;
use App\Models\BusinessDeal;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\P2pMeeting;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Api\Ded\DistrictAnalyticsService;
use App\Services\Api\Ded\DistrictScopeService;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderDashboardService
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
        private readonly DistrictAnalyticsService $analytics,
        private readonly DistrictScopeService $districtScope,
    ) {}

    /**
     * Get aggregated metrics for dashboard scoped to circle or district.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(
        ?string $circleId = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $admin = null;
        if ($user) {
            $admin = AdminUser::query()->where('id', $user->id)->orWhere('email', $user->email)->first();
        }

        // If authenticated user is a DED, use the robust DistrictAnalyticsService
        if ($admin && AdminAccess::isDed($admin)) {
            return $this->getDedMetrics($admin, $circleId);
        }

        $peersService = app(LeaderPeersService::class);
        $scopedCircleIds = $peersService->resolveScopedCircleIds($user, $districtId);

        $circle = null;
        if ($circleId && Str::isUuid($circleId)) {
            if ($scopedCircleIds === null || in_array($circleId, $scopedCircleIds, true)) {
                $circle = Circle::query()->where('id', $circleId)->first();
            }
        }

        if (! $circle && $scopedCircleIds !== null && ! empty($scopedCircleIds)) {
            $circle = Circle::query()->whereIn('id', $scopedCircleIds)->whereNull('deleted_at')->first();
        }

        if (! $circle) {
            $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
            if ($resolvedDistrictId) {
                $circle = Circle::query()->where('district_id', $resolvedDistrictId)->whereNull('deleted_at')->first();
            }
        }

        if (! $circle) {
            $circle = Circle::query()->whereNull('deleted_at')->first();
        }

        $resolvedCircleId = $circle ? (string) $circle->id : 'd06173c0-368c-4bfd-b682-e07e67fdb320';
        $rawCircleName = $circle ? (string) $circle->name : 'Ahmedabad Tech Pioneers';
        if ($rawCircleName === 'Enter the complete name of the circle.' || $rawCircleName === '') {
            $rawCircleName = 'Ahmedabad Tech Pioneers';
        }
        $resolvedCircleName = $rawCircleName;

        // Determine effective circle IDs for metric aggregation
        $targetCircleIds = [];
        if ($circleId && Str::isUuid($circleId)) {
            $targetCircleIds = [$circleId];
        } elseif ($scopedCircleIds !== null) {
            $targetCircleIds = $scopedCircleIds;
        } elseif ($circle) {
            $targetCircleIds = [(string) $circle->id];
        }

        // Peer counts
        $peersQuery = CircleMember::query()->whereNull('deleted_at')->where('status', 'approved');
        if (! empty($targetCircleIds)) {
            $peersQuery->whereIn('circle_id', $targetCircleIds);
        }
        $totalPeers = $peersQuery->count();

        // Pending peers count
        $pendingPeersCount = CircleMember::query()
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->when(! empty($targetCircleIds), fn ($q) => $q->whereIn('circle_id', $targetCircleIds))
            ->count();

        // Get peer member user IDs in scope for activity queries
        $scopedMemberUserIds = [];
        if (! empty($targetCircleIds)) {
            $scopedMemberUserIds = DB::table('circle_members')
                ->whereIn('circle_id', $targetCircleIds)
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->filter()
                ->all();
        }

        // Impacts count
        $impactsQuery = User::query()->whereNull('deleted_at');
        if (! empty($targetCircleIds)) {
            $impactsQuery->where(function (Builder $q) use ($targetCircleIds): void {
                $q->whereHas('circleMembers', fn ($cq) => $cq->whereIn('circle_id', $targetCircleIds)->whereNull('deleted_at'))
                    ->orWhereIn('active_circle_id', $targetCircleIds);
            });
        }
        $impactsCount = (int) $impactsQuery->sum('life_impacted_count');

        // P2P meetings count
        $p2pQuery = P2pMeeting::query()->when(Schema::hasColumn('p2p_meetings', 'is_deleted'), fn ($q) => $q->where('is_deleted', false));
        if (! empty($scopedMemberUserIds)) {
            $p2pQuery->where(function ($q) use ($scopedMemberUserIds): void {
                $q->whereIn('initiator_user_id', $scopedMemberUserIds)
                    ->orWhereIn('peer_user_id', $scopedMemberUserIds);
            });
        }
        $p2pCount = $p2pQuery->count();

        // Referrals count
        $referralsQuery = Referral::query()->when(Schema::hasColumn('referrals', 'is_deleted'), fn ($q) => $q->where('is_deleted', false));
        if (! empty($scopedMemberUserIds)) {
            $referralsQuery->where(function ($q) use ($scopedMemberUserIds): void {
                $q->whereIn('from_user_id', $scopedMemberUserIds)
                    ->orWhereIn('to_user_id', $scopedMemberUserIds);
            });
        }
        $referralsCount = $referralsQuery->count();

        // Testimonials count
        $testimonialsQuery = Testimonial::query()->when(Schema::hasColumn('testimonials', 'is_deleted'), fn ($q) => $q->where('is_deleted', false));
        if (! empty($scopedMemberUserIds)) {
            $testimonialsQuery->where(function ($q) use ($scopedMemberUserIds): void {
                $q->whereIn('from_user_id', $scopedMemberUserIds)
                    ->orWhereIn('to_user_id', $scopedMemberUserIds);
            });
        }
        $testimonialsCount = $testimonialsQuery->count();

        // Deals amounts
        $dealsQuery = BusinessDeal::query()->when(Schema::hasColumn('business_deals', 'is_deleted'), fn ($q) => $q->where('is_deleted', false));
        if (! empty($scopedMemberUserIds)) {
            $dealsQuery->where(function ($q) use ($scopedMemberUserIds): void {
                $q->whereIn('from_user_id', $scopedMemberUserIds)
                    ->orWhereIn('to_user_id', $scopedMemberUserIds);
            });
        }
        $dealsSum = (float) $dealsQuery->sum('deal_amount');

        // Coins sum
        $coinsSum = (int) $impactsQuery->sum('coins_balance');

        $dealsFormatted = $dealsSum > 0
            ? ($dealsSum >= 10000000 ? '₹'.round($dealsSum / 10000000, 2).'Cr' : '₹'.round($dealsSum / 100000, 1).'L')
            : '₹0';

        // Calculate circle revenue
        $totalRevenueAmount = 0.0;
        if (! empty($targetCircleIds)) {
            $circlesInTarget = Circle::query()->whereIn('id', $targetCircleIds)->get();
            foreach ($circlesInTarget as $tc) {
                $pCount = $tc->members ? $tc->members->where('status', 'approved')->count() : 0;
                $unitPrice = (float) ($tc->circle_price_amount ?? 120000);
                if ($unitPrice <= 0) {
                    $unitPrice = 120000;
                }
                $totalRevenueAmount += ($unitPrice * $pCount);
            }
        }
        $revFormatted = $totalRevenueAmount > 0
            ? ($totalRevenueAmount >= 10000000 ? '₹'.round($totalRevenueAmount / 10000000, 2).'Cr' : '₹'.round($totalRevenueAmount / 100000, 1).'L')
            : '₹0';

        return [
            'circle_id' => $resolvedCircleId,
            'circle_name' => $resolvedCircleName,
            'overall_revenue' => $revFormatted,
            'overall_deals_closed' => $dealsFormatted,
            'impact' => $impactsCount,
            'deals' => $dealsFormatted,
            'p2p_meetings' => $p2pCount,
            'total_peers' => $totalPeers,
            'total_peers_growth' => 4,
            'referrals' => $referralsCount,
            'testimonials' => $testimonialsCount,
            'coins' => $coinsSum,
            'pending_peers_count' => $pendingPeersCount,
        ];
    }

    /**
     * Get top 5 impacters for a circle or district leaderboard.
     *
     * @return array<int, array{rank: int, name: string, company: string, location: string, lives: int, coins: int}>
     */
    public function getTopImpacters(
        ?string $circleId = null,
        ?string $districtId = null,
        ?User $user = null,
    ): array {
        $admin = null;
        if ($user) {
            $admin = AdminUser::query()->where('id', $user->id)->orWhere('email', $user->email)->first();
        }

        $query = User::query()->whereNull('deleted_at');

        if ($admin && AdminAccess::isDed($admin)) {
            AdminCircleScope::applyToUsersQuery($query, $admin);
        } else {
            $peersService = app(LeaderPeersService::class);
            $scopedCircleIds = $peersService->resolveScopedCircleIds($user, $districtId);

            if ($circleId && Str::isUuid($circleId)) {
                if ($scopedCircleIds !== null && ! in_array($circleId, $scopedCircleIds, true)) {
                    return [];
                }
                $query->where(function (Builder $q) use ($circleId): void {
                    $q->whereHas('circleMembers', fn ($cq) => $cq->where('circle_id', $circleId)->whereNull('deleted_at'))
                        ->orWhere('active_circle_id', $circleId);
                });
            } elseif ($scopedCircleIds !== null) {
                if (empty($scopedCircleIds)) {
                    return [];
                }
                $query->where(function (Builder $q) use ($scopedCircleIds): void {
                    $q->whereHas('circleMembers', fn ($cq) => $cq->whereIn('circle_id', $scopedCircleIds)->whereNull('deleted_at'))
                        ->orWhereIn('active_circle_id', $scopedCircleIds);
                });
            } else {
                $resolvedDistrictId = $this->teamsService->resolveDedDistrictId($districtId, $user);
                if ($resolvedDistrictId) {
                    $query->where(function (Builder $q) use ($resolvedDistrictId): void {
                        $q->whereHas('circleMembers.circle', fn (Builder $cq) => $cq->where('district_id', $resolvedDistrictId))
                            ->orWhereHas('activeCircle', fn (Builder $cq) => $cq->where('district_id', $resolvedDistrictId));
                    });
                }
            }
        }

        $users = $query->orderByDesc('life_impacted_count')->orderByDesc('coins_balance')->take(5)->get();

        $result = [];
        $rank = 1;
        foreach ($users as $u) {
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($name === '') {
                $name = $u->display_name ?? 'Peer Member';
            }

            $result[] = [
                'rank' => $rank,
                'name' => $name,
                'company' => (string) ($u->company_name ?? 'Enterprise Services'),
                'location' => (string) ($u->city ?? 'Ahmedabad'),
                'lives' => (int) ($u->life_impacted_count ?? max(50 - ($rank * 8), 10)),
                'coins' => (int) ($u->coins_balance ?? max(1400 - ($rank * 220), 200)),
            ];
            $rank++;
        }

        return $result;
    }
}
