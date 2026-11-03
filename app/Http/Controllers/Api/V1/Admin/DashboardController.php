<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\Event;
use App\Models\Impact;
use App\Models\JoinRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Admin\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminDashboardMetricsService $metricsService,
    ) {}

    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user?->id ?? 'global';
        $cacheKey = "admin.api.v1.dashboard.metrics.{$userId}";

        if ($request->boolean('refresh')) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $augmented = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($user) {
            $metrics = $this->metricsService->getMetrics($user);
            $todayStr = now()->toDateString();

            $userStats = DB::table('users')
                ->whereNull('gdpr_deleted_at')
                ->selectRaw("
                    COUNT(*) as total_users,
                    COUNT(*) FILTER (WHERE deleted_at IS NULL) as active_directory_users,
                    COUNT(*) FILTER (WHERE DATE(created_at) = ?) as new_signups,
                    COALESCE(SUM(coins_balance), 0) as total_coins_balance
                ", [$todayStr])
                ->first();

            $circleStats = DB::table('circles')
                ->selectRaw("
                    COUNT(*) as total_circles,
                    COUNT(*) FILTER (WHERE status = 'active') as active_circles,
                    COUNT(*) FILTER (WHERE status = 'pending') as pending_approvals
                ")
                ->first();

            $totalPeers = (int) ($userStats->total_users ?? 0);
            $activeDirectoryPeers = (int) ($userStats->active_directory_users ?? 0);
            $newSignupsToday = (int) ($userStats->new_signups ?? 0);
            $totalCoinsBalance = (int) ($userStats->total_coins_balance ?? 0);

            $charteredCircles = (int) ($circleStats->active_circles ?? 0);
            $awaitingReviewCircles = (int) ($circleStats->pending_approvals ?? 0);
            $totalCircles = (int) ($circleStats->total_circles ?? 0);

            $today = today();
            $todayEventsCount = 0;
            if (Schema::hasTable('events')) {
                $eventQuery = Event::query();
                $startCol = Schema::hasColumn('events', 'start_date') ? 'start_date' : (Schema::hasColumn('events', 'start_at') ? 'start_at' : null);
                $endCol = Schema::hasColumn('events', 'end_date') ? 'end_date' : (Schema::hasColumn('events', 'end_at') ? 'end_at' : null);

                if ($startCol && $endCol) {
                    $eventQuery->whereDate($startCol, '<=', $today)->whereDate($endCol, '>=', $today);
                } elseif ($startCol) {
                    $eventQuery->whereDate($startCol, $today);
                }
                $todayEventsCount = $eventQuery->count();
            }

            $pendingClearancesCount = 0;
            $pendingStatuses = ['pending', 'pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'];
            if (Schema::hasTable('circle_join_requests')) {
                $pendingClearancesCount += CircleJoinRequest::query()
                    ->whereIn(DB::raw('status::text'), $pendingStatuses)
                    ->count();
            }
            if (class_exists(JoinRequest::class) && Schema::hasTable('join_requests')) {
                $pendingClearancesCount += JoinRequest::query()
                    ->whereIn(DB::raw('status::text'), $pendingStatuses)
                    ->count();
            } elseif (Schema::hasTable('join_requests')) {
                $pendingClearancesCount += DB::table('join_requests')
                    ->whereIn(DB::raw('status::text'), $pendingStatuses)
                    ->count();
            }

            if ($totalCoinsBalance >= 1000000) {
                $coinsReserve = round($totalCoinsBalance / 1000000, 2) . 'M';
            } elseif ($totalCoinsBalance >= 1000) {
                $coinsReserve = round($totalCoinsBalance / 1000, 1) . 'K';
            } else {
                $coinsReserve = (string) $totalCoinsBalance;
            }

            if (Schema::hasTable('app_config_settings') && Schema::hasColumn('app_config_settings', 'key') && Schema::hasColumn('app_config_settings', 'value')) {
                $setting = DB::table('app_config_settings')->where('key', 'coins_reserve')->value('value');
                if ($setting !== null && $setting !== '') {
                    $coinsReserve = (string) $setting;
                }
            }

            $activitiesToday = Schema::hasTable('activities')
                ? (int) DB::table('activities')->whereDate('created_at', $todayStr)->count()
                : 0;
            $reportedPosts = Schema::hasTable('post_reports')
                ? (int) DB::table('post_reports')->distinct()->count('post_id')
                : 0;
            $supportRequests = Schema::hasTable('support_requests')
                ? (int) DB::table('support_requests')->count()
                : 0;
            $coinsLedgerCount = Schema::hasTable('coins_ledger')
                ? (int) DB::table('coins_ledger')->count()
                : 0;

            return array_merge($metrics, [
                'total_peers' => $totalPeers,
                'total_users' => $totalPeers,
                'totalPeers' => $totalPeers,
                'totalUsers' => $totalPeers,
                'totalActivePeers' => $totalPeers,
                'total_active_peers' => $totalPeers,
                'activeDirectoryPeers' => $activeDirectoryPeers,
                'active_directory_peers' => $activeDirectoryPeers,
                'newSignupsToday' => $newSignupsToday,
                'new_signups_today' => $newSignupsToday,
                'new_signups' => $newSignupsToday,
                'charteredCircles' => $charteredCircles,
                'chartered_circles' => $charteredCircles,
                'activeCirclesCount' => $charteredCircles,
                'active_circles_count' => $charteredCircles,
                'active_circles' => $charteredCircles,
                'awaitingReviewCircles' => $awaitingReviewCircles,
                'awaiting_review_circles' => $awaitingReviewCircles,
                'pending_approvals' => $awaitingReviewCircles,
                'totalCircles' => $totalCircles,
                'total_circles' => $totalCircles,
                'todayEventsCount' => $todayEventsCount,
                'today_events_count' => $todayEventsCount,
                'pendingClearancesCount' => $pendingClearancesCount,
                'pending_clearances_count' => $pendingClearancesCount,
                'coinsReserve' => $coinsReserve,
                'coins_reserve' => $coinsReserve,
                'totalCoinsBalance' => $totalCoinsBalance,
                'total_coins_balance' => $totalCoinsBalance,
                'coinsLedgerCount' => $coinsLedgerCount,
                'coins_ledger_count' => $coinsLedgerCount,
                'activitiesToday' => $activitiesToday,
                'activities_today' => $activitiesToday,
                'reportedPosts' => $reportedPosts,
                'reported_posts' => $reportedPosts,
                'supportRequests' => $supportRequests,
                'support_requests' => $supportRequests,
                'totalLivesImpacted' => $metrics['total_lives_impacted'] ?? 0,
                'totalCoinsIssued' => $totalCoinsBalance > 0 ? $totalCoinsBalance : ($metrics['total_coins_issued'] ?? 0),
                'totalRevenue' => $metrics['total_revenue'] ?? 0,
            ]);
        });

        return $this->success($augmented);
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->metrics($request);
    }

    public function getSummary(Request $request): JsonResponse
    {
        return $this->metrics($request);
    }

    public function revenue(Request $request): JsonResponse
    {
        $user = $request->user();
        $amountColumn = $this->resolvePaymentAmountColumn();
        $categoryColumn = $this->resolvePaymentCategoryColumn();
        $query = Payment::query()->whereIn('status', $this->resolvePaidStatuses());

        if (! $this->scope->isGlobal($user)) {
            $visibleCircleIds = $this->scope->visibleCircleIds($user);
            if (Schema::hasColumn('payments', 'circle_id')) {
                $query->whereIn('circle_id', $visibleCircleIds);
            } else {
                $query->whereIn('user_id', function ($q) use ($visibleCircleIds): void {
                    $q->select('user_id')->from('circle_members')
                        ->whereIn('circle_id', $visibleCircleIds)
                        ->where('status', 'approved')
                        ->whereNull('deleted_at');
                });
            }
        }

        if (! $categoryColumn) {
            $total = (float) $query->sum($amountColumn);

            return $this->success([
                'membership_revenue' => null,
                'circle_fee_revenue' => null,
                'event_revenue' => null,
                'sponsor_revenue' => null,
                'total_revenue' => $total,
            ]);
        }

        $rows = $query->selectRaw("COALESCE({$categoryColumn}, 'other') as source_key, SUM({$amountColumn}) as total")->groupBy($categoryColumn)->get();
        $mapped = $rows->pluck('total', 'source_key');

        return $this->success([
            'membership_revenue' => (float) ($mapped['membership'] ?? 0),
            'circle_fee_revenue' => (float) ($mapped['circle_fee'] ?? 0),
            'event_revenue' => (float) ($mapped['event'] ?? 0),
            'sponsor_revenue' => (float) ($mapped['sponsor'] ?? 0),
            'total_revenue' => (float) $rows->sum('total'),
        ]);
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

    private function resolvePaymentCategoryColumn(): ?string
    {
        foreach (['source', 'type', 'payment_type', 'category', 'transaction_type', 'purpose'] as $column) {
            if (Schema::hasColumn('payments', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function resolvePaidStatuses(): array
    {
        $distinct = DB::table('payments')->select('status')->whereNotNull('status')->distinct()->pluck('status')->map(fn ($s) => strtolower((string) $s))->all();
        $statuses = array_values(array_filter(['success', 'paid', 'completed'], fn ($candidate) => in_array($candidate, $distinct, true)));

        return $statuses !== [] ? $statuses : ['success'];
    }

    public function lifeImpact(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Impact::query()->where('status', 'approved');
        if (! $this->scope->isGlobal($user)) {
            $query->whereIn('user_id', User::query()->select('users.id')->tap(fn ($q) => $this->scope->applyUserScope($q, $user)));
        }

        $impactScoreCol = '1';
        if (Schema::hasTable('impacts')) {
            if (Schema::hasColumn('impacts', 'impact_score')) {
                $impactScoreCol = 'impact_score';
            } elseif (Schema::hasColumn('impacts', 'impact_value')) {
                $impactScoreCol = 'impact_value';
            }
        }

        $top = (clone $query)
            ->selectRaw("user_id, SUM(COALESCE({$impactScoreCol}, 1)) as total")
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('user:id,first_name,last_name,display_name')
            ->get();

        return $this->success([
            'total_lives_impacted' => (int) $query->sum(DB::raw("COALESCE({$impactScoreCol}, 1)")),
            'this_month_lives_impacted' => (int) (clone $query)->whereBetween('approved_at', [now()->startOfMonth(), now()->endOfMonth()])->sum(DB::raw("COALESCE({$impactScoreCol}, 1)")),
            'top_contributors' => $top,
        ]);
    }

    public function membersGrowth(Request $request): JsonResponse
    {
        $query = User::query()->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as total")->groupBy('month')->orderBy('month');
        $this->scope->applyUserScope($query, $request->user());

        return $this->success($query->get());
    }

    public function circlesOverview(Request $request): JsonResponse
    {
        $query = Circle::query()->withCount(['members' => fn ($q) => $q->where('circle_members.status', 'approved')->whereNull('circle_members.deleted_at')]);
        $this->scope->applyCircleScope($query, $request->user());

        return $this->success($query->latest('updated_at')->limit(20)->get());
    }

    public function pendingCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $circleIds = $this->scope->visibleCircleIds($user);

        return $this->success([
            'pending_impacts' => Impact::query()->where('status', 'pending')->count(),
            'pending_coin_claims' => CoinClaimRequest::query()->where('status', 'pending')->count(),
            'pending_circle_join_requests' => CircleJoinRequest::query()->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'])->when(! $this->scope->isGlobal($user), fn ($q) => $q->whereIn('circle_id', $circleIds))->count(),
            'pending_approvals_count' => Impact::query()->where('status', 'pending')->count() + CoinClaimRequest::query()->where('status', 'pending')->count(),
        ]);
    }
}
