<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CertificationSubmission;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\Event;
use App\Models\Impact;
use App\Models\Industry;
use App\Models\Payment;
use App\Models\PostReport;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardMetricsService
{
    public function __construct(
        private readonly AdminScopeService $scope,
    ) {}

    /**
     * Compute authoritative dashboard metrics from PostgreSQL.
     */
    public function getMetrics(?User $actor = null): array
    {
        $now = now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $endOfThisMonth = $now->copy()->endOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // 1. Peer / User Aggregations
        $usersQuery = User::query();
        if ($actor !== null) {
            $this->scope->applyUserScope($usersQuery, $actor);
        }

        $totalUsers = (clone $usersQuery)->count();
        $hasIsActive = Schema::hasColumn('users', 'is_active');
        $totalActiveMembers = (clone $usersQuery)
            ->where(function ($q) use ($hasIsActive): void {
                $q->where('status', 'active');
                if ($hasIsActive) {
                    $q->orWhere('is_active', true);
                }
            })
            ->whereNotIn(DB::raw('LOWER(COALESCE(membership_status, \'\'))'), ['visitor', ''])
            ->count();

        $totalProPeers = (clone $usersQuery)
            ->whereNotIn(DB::raw('LOWER(COALESCE(membership_status, \'\'))'), [
                'visitor', 'free_peer', 'free', 'free_trial_peer', 'free_trial', '',
            ])
            ->count();

        $totalFreePeers = (clone $usersQuery)
            ->whereIn(DB::raw('LOWER(COALESCE(membership_status, \'\'))'), [
                'visitor', 'free_peer', 'free', '',
            ])
            ->count();

        $newMembersThisMonth = (clone $usersQuery)
            ->whereBetween('created_at', [$startOfThisMonth, $endOfThisMonth])
            ->count();

        $newMembersLastMonth = (clone $usersQuery)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $memberGrowthMonthlyPercent = $newMembersLastMonth > 0
            ? (float) round((($newMembersThisMonth - $newMembersLastMonth) / $newMembersLastMonth) * 100, 2)
            : ($newMembersThisMonth > 0 ? 100.0 : 0.0);

        // 2. Circle Aggregations
        $circlesQuery = Circle::query();
        if ($actor !== null) {
            $this->scope->applyCircleScope($circlesQuery, $actor);
        }

        $totalCircles = (clone $circlesQuery)->count();
        $activeCirclesCount = (clone $circlesQuery)->where('status', 'active')->count();

        // 3. Industry & Regional Aggregations
        $totalIndustries = Industry::query()
            ->when($actor !== null && ! $this->scope->isGlobal($actor), fn ($q) => $q->whereIn('id', $this->scope->visibleIndustryIds($actor)))
            ->count();

        $totalDistricts = $actor !== null ? count($this->scope->visibleDistrictIds($actor)) : 0;

        $totalLeaders = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('key', [
                'ded', 'industry_director', 'circle_leader', 'founder', 'director',
                'chair', 'vice_chair', 'secretary', 'circle_founder', 'circle_director',
            ]))
            ->count();

        // 4. Life Impact Aggregations
        $impactsQuery = Impact::query()->where('status', 'approved');
        if ($actor !== null && ! $this->scope->isGlobal($actor)) {
            $visibleCircleIds = $this->scope->visibleCircleIds($actor);
            $impactsQuery->whereIn('circle_id', $visibleCircleIds);
        }

        $impactScoreCol = '1';
        if (Schema::hasTable('impacts')) {
            if (Schema::hasColumn('impacts', 'impact_score')) {
                $impactScoreCol = 'impact_score';
            } elseif (Schema::hasColumn('impacts', 'impact_value')) {
                $impactScoreCol = 'impact_value';
            }
        }

        $totalLivesImpacted = (int) (clone $impactsQuery)->sum(DB::raw("COALESCE({$impactScoreCol}, 1)"));
        $thisMonthLivesImpacted = (int) (clone $impactsQuery)
            ->whereBetween('approved_at', [$startOfThisMonth, $endOfThisMonth])
            ->sum(DB::raw("COALESCE({$impactScoreCol}, 1)"));

        // 5. Coins Aggregations
        $totalCoinsIssued = 0;
        if (Schema::hasTable('coin_ledger')) {
            $totalCoinsIssued = (int) DB::table('coin_ledger')->where('type', 'credit')->sum('amount');
        }
        if ($totalCoinsIssued === 0) {
            $totalCoinsIssued = (int) User::query()->sum('coins_balance');
        }

        // 6. Revenue Aggregations
        $amountColumn = $this->resolvePaymentAmountColumn();
        $paidStatuses = $this->resolvePaidStatuses();

        $paymentsQuery = Payment::query()->whereIn('status', $paidStatuses);
        if ($actor !== null && ! $this->scope->isGlobal($actor)) {
            $paymentsQuery->whereIn('circle_id', $this->scope->visibleCircleIds($actor));
        }

        $totalRevenue = (float) (clone $paymentsQuery)->sum($amountColumn);
        $thisMonthRevenue = (float) (clone $paymentsQuery)
            ->whereBetween('created_at', [$startOfThisMonth, $endOfThisMonth])
            ->sum($amountColumn);
        $lastMonthRevenue = (float) (clone $paymentsQuery)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum($amountColumn);

        $monthlyRevenueGrowthPercent = $lastMonthRevenue > 0
            ? (float) round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
            : ($thisMonthRevenue > 0 ? 100.0 : 0.0);

        // 7. Pending Counts & Badges
        $circleIds = $actor !== null ? $this->scope->visibleCircleIds($actor) : [];

        $pendingJoinRequests = Schema::hasTable('circle_join_requests')
            ? CircleJoinRequest::query()
                ->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee', 'pending'])
                ->when($actor !== null && ! $this->scope->isGlobal($actor), fn ($q) => $q->whereIn('circle_id', $circleIds))
                ->count()
            : 0;

        $pendingImpacts = Schema::hasTable('impacts')
            ? Impact::query()->where('status', 'pending')->count()
            : 0;

        $pendingCoinClaims = Schema::hasTable('coin_claim_requests')
            ? CoinClaimRequest::query()->where('status', 'pending')->count()
            : 0;

        $pendingCertifications = Schema::hasTable('certification_submissions')
            ? CertificationSubmission::query()->where('status', 'pending')->count()
            : 0;

        $openTickets = Schema::hasTable('support_tickets')
            ? SupportTicket::query()->whereIn('status', ['open', 'pending'])->count()
            : 0;

        $pendingPostReports = Schema::hasTable('post_reports')
            ? PostReport::query()->where('status', 'open')->count()
            : 0;

        $totalPendingActions = $pendingJoinRequests + $pendingImpacts + $pendingCoinClaims
            + $pendingCertifications + $openTickets + $pendingPostReports;

        $upcomingEventsCount = Schema::hasTable('events')
            ? Event::query()
                ->whereDate('start_at', '>=', $now->toDateString())
                ->when($circleIds !== [], fn ($q) => $q->whereIn('circle_id', $circleIds))
                ->count()
            : 0;

        return [
            'total_peers' => $totalUsers,
            'total_users' => $totalUsers,
            'total_active_members' => $totalActiveMembers,
            'total_pro_peers' => $totalProPeers,
            'total_free_peers' => $totalFreePeers,
            'total_circles' => $totalCircles,
            'active_circles_count' => $activeCirclesCount,
            'total_industries' => $totalIndustries,
            'total_districts' => $totalDistricts,
            'total_leaders' => $totalLeaders,
            'total_lives_impacted' => $totalLivesImpacted,
            'this_month_lives_impacted' => $thisMonthLivesImpacted,
            'total_coins_issued' => $totalCoinsIssued,
            'total_revenue' => $totalRevenue,
            'this_month_revenue' => $thisMonthRevenue,
            'monthly_revenue_growth_percent' => $monthlyRevenueGrowthPercent,
            'member_growth_monthly_percent' => $memberGrowthMonthlyPercent,
            'upcoming_events_count' => $upcomingEventsCount,
            'pending_badges' => [
                'pending_join_requests' => $pendingJoinRequests,
                'pending_circle_join_requests' => $pendingJoinRequests,
                'pending_impacts' => $pendingImpacts,
                'pending_coin_claims' => $pendingCoinClaims,
                'pending_certifications' => $pendingCertifications,
                'open_tickets' => $openTickets,
                'pending_post_reports' => $pendingPostReports,
                'total_pending_actions' => $totalPendingActions,
            ],
            'pending_counts' => [
                'pending_join_requests' => $pendingJoinRequests,
                'pending_circle_join_requests' => $pendingJoinRequests,
                'pending_impacts' => $pendingImpacts,
                'pending_coin_claims' => $pendingCoinClaims,
                'pending_certifications' => $pendingCertifications,
                'open_tickets' => $openTickets,
                'pending_post_reports' => $pendingPostReports,
                'total_pending_actions' => $totalPendingActions,
            ],
        ];
    }

    private function resolvePaymentAmountColumn(): string
    {
        foreach (['total_amount', 'amount', 'base_amount'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return 'total_amount';
    }

    private function resolvePaidStatuses(): array
    {
        $distinct = DB::table('payments')
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->map(fn ($s) => strtolower((string) $s))
            ->all();

        $statuses = array_values(array_filter(['success', 'paid', 'completed'], fn ($candidate) => in_array($candidate, $distinct, true)));

        return $statuses !== [] ? $statuses : ['success', 'paid'];
    }
}
