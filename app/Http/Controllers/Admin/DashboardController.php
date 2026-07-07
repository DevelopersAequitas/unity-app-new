<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use App\Services\Api\Ded\DashboardAggregationService;
use App\Services\Api\Ded\DistrictAnalyticsService;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        // ── Users stats: single aggregated query instead of 3 separate round-trips ──
        $userStats = cache()->remember('admin.dashboard.user_stats', 60, function () use ($today) {
        $userStats = cache()->remember('admin.dashboard.user_stats', 60, function () {
            if (! Schema::hasTable('users')) {
                return ['total' => 0, 'today' => 0, 'premium' => 0];
            }

            $hasCreatedAt      = Schema::hasColumn('users', 'created_at');
            $hasCreatedAt = Schema::hasColumn('users', 'created_at');
            $hasMembershipStatus = Schema::hasColumn('users', 'membership_status');

            $row = DB::table('users')
                ->selectRaw('COUNT(*) as total')
                ->when($hasCreatedAt, fn ($q) => $q->selectRaw("COUNT(*) FILTER (WHERE DATE(created_at) = ?) as today", [$this->todayDate()]))
                ->when($hasCreatedAt, fn ($q) => $q->selectRaw('COUNT(*) FILTER (WHERE DATE(created_at) = ?) as today', [$this->todayDate()]))
                ->when($hasMembershipStatus, fn ($q) => $q->selectRaw("COUNT(*) FILTER (WHERE membership_status = 'premium') as premium"))
                ->first();

            return [
                'total'   => (int) ($row->total ?? 0),
                'today'   => (int) ($row->today ?? 0),
                'total' => (int) ($row->total ?? 0),
                'today' => (int) ($row->today ?? 0),
                'premium' => (int) ($row->premium ?? 0),
            ];
        });

        // ── Circles stats: single aggregated query instead of 2 separate round-trips ──
        $circleStats = cache()->remember('admin.dashboard.circle_stats', 60, function () {
            if (! Schema::hasTable('circles')) {
                return ['active' => 0, 'pending' => 0];
            }

            if (! Schema::hasColumn('circles', 'status')) {
                return ['active' => DB::table('circles')->count(), 'pending' => 0];
            }

            $row = DB::table('circles')
                ->selectRaw("COUNT(*) FILTER (WHERE status = 'active') as active, COUNT(*) FILTER (WHERE status = 'pending') as pending")
                ->first();

            return [
                'active'  => (int) ($row->active ?? 0),
                'pending' => (int) ($row->pending ?? 0),
            ];
        });

        // ── Other stats: cached individually ──
        $activitiesToday = cache()->remember('admin.dashboard.activities_today', 60, function () use ($today) {
            return $this->hasTableColumn('activities', 'created_at')
                ? (int) DB::table('activities')->whereDate('created_at', $today)->count()
                : 0;
        });

        $supportRequests  = cache()->remember('admin.dashboard.support_requests', 120, fn () => $this->safeCountTable('support_requests'));
        $reportedPosts    = cache()->remember('admin.dashboard.reported_posts', 120, fn () => $this->safeReportedPostsCount());
        $coinsIssued      = cache()->remember('admin.dashboard.coins_issued', 120, fn () => $this->safeCountTable('coin_ledgers'));
        $walletCollections = cache()->remember('admin.dashboard.wallet_collections', 120, fn () => $this->safeCountTable('wallet_transactions'));

        $totalUsers      = $userStats['total'];
        $newSignups      = $userStats['today'];
        $premiumUpgrades = $userStats['premium'];
        $activeCircles   = $circleStats['active'];
        $pendingApprovals = $circleStats['pending'];

        $totalContactPosts  = 0;
        $todayContactPosts  = 0;
        $recentContactPosts = collect();

        $stats = [
            'newSignups'      => $newSignups,
            'premiumUpgrades' => $premiumUpgrades,
            'activeCircles'   => $activeCircles,
            'pendingApprovals' => $pendingApprovals,
            'coinsIssued'     => $coinsIssued,
            'walletCollections' => $walletCollections,
            'supportRequests' => $supportRequests,
            'activitiesToday' => $activitiesToday,
            'reportedPosts'   => $reportedPosts,
            // Legacy keys for existing blade usage
            'total_users'     => $totalUsers,
            'active_circles'  => $activeCircles,
            'pending_approvals' => $pendingApprovals,
            'new_signups'     => $newSignups,

            return [
                'active' => (int) ($row->active ?? 0),
                'pending' => (int) ($row->pending ?? 0),
            ];
        });

        // ── Other stats: cached individually ──
        $activitiesToday = cache()->remember('admin.dashboard.activities_today', 60, function () use ($today) {
            return $this->hasTableColumn('activities', 'created_at')
                ? (int) DB::table('activities')->whereDate('created_at', $today)->count()
                : 0;
        });

        $supportRequests = cache()->remember('admin.dashboard.support_requests', 120, fn () => $this->safeCountTable('support_requests'));
        $reportedPosts = cache()->remember('admin.dashboard.reported_posts', 120, fn () => $this->safeReportedPostsCount());
        $coinsIssued = cache()->remember('admin.dashboard.coins_issued', 120, fn () => $this->safeCountTable('coin_ledgers'));
        $walletCollections = cache()->remember('admin.dashboard.wallet_collections', 120, fn () => $this->safeCountTable('wallet_transactions'));

        $totalUsers = $userStats['total'];
        $newSignups = $userStats['today'];
        $premiumUpgrades = $userStats['premium'];
        $activeCircles = $circleStats['active'];
        $pendingApprovals = $circleStats['pending'];

        $totalContactPosts = 0;
        $todayContactPosts = 0;
        $recentContactPosts = collect();

        $stats = [
            'newSignups' => $newSignups,
            'premiumUpgrades' => $premiumUpgrades,
            'activeCircles' => $activeCircles,
            'pendingApprovals' => $pendingApprovals,
            'coinsIssued' => $coinsIssued,
            'walletCollections' => $walletCollections,
            'supportRequests' => $supportRequests,
            'activitiesToday' => $activitiesToday,
            'reportedPosts' => $reportedPosts,
            // Legacy keys for existing blade usage
            'total_users' => $totalUsers,
            'active_circles' => $activeCircles,
            'pending_approvals' => $pendingApprovals,
            'new_signups' => $newSignups,
        ];

        $pendingItems = [
            ['title' => 'Pending Activities Today', 'count' => $activitiesToday],
            ['title' => 'Circles Awaiting Review',  'count' => $pendingApprovals],
            ['title' => 'Reported Posts',           'count' => $reportedPosts],
            ['title' => 'Support Requests',         'count' => $supportRequests],
        ];

        $recentPeers = User::query()
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'stats'             => $stats,
            'pendingItems'      => $pendingItems,
            'totalContactPosts' => $totalContactPosts,
            'todayContactPosts' => $todayContactPosts,
            'recentContactPosts' => $recentContactPosts,
            'recentPeers'       => $recentPeers,
            'recentPeers' => $recentPeers,
        ]);
    }

    private function todayDate(): string
    {
        return now()->toDateString();
    }


    public function ded(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;

        if (! $districtName) {
            return view('admin.ded-dashboard', [
                'districtName' => null,
                'dashboardData' => [],
                'districtCircles' => collect(),
                'selectedCircleId' => '',
                'selectedCircle' => null,
            ]);
        }

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);

        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedCircleId = trim((string) $request->query('circle_id', ''));
        if ($selectedCircleId === 'all') {
            $selectedCircleId = '';
        }

        $selectedCircle = null;
        if ($selectedCircleId !== '') {
            $selectedCircle = $districtCircles->firstWhere('id', $selectedCircleId);
            abort_unless($selectedCircle !== null, 403);
        }

        $aggregation = app(DashboardAggregationService::class);
        $dashboardData = $aggregation->getDashboardData($admin, $selectedCircleId ?: null);

        return view('admin.ded-dashboard', [
            'districtName' => $districtName,
            'dashboardData' => $dashboardData,
            'districtCircles' => $districtCircles,
            'selectedCircleId' => $selectedCircleId,
            'selectedCircle' => $selectedCircle,
        ]);
    }

    public function dedLeadershipDetail(Request $request, string $role)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $roleTitles = [
            'industry_director' => 'Industry Directors',
            'founder' => 'Circle Founders',
            'director' => 'Circle Directors',
            'chair' => 'Chairs',
            'vice_chair' => 'Vice Chairs',
            'secretary' => 'Secretaries',
            'member' => 'Members',
        ];
        abort_unless(array_key_exists($role, $roleTitles), 404);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getLeadershipRoleDetails($admin, $role, $filters);

        return view('admin.ded-leadership-detail', [
            'role' => $role,
            'roleTitle' => $roleTitles[$role],
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedActiveMembersDetail(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getActiveMembersDetail($admin, $filters);

        return view('admin.ded-health-active-members', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedLeadershipSpotsDetail(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getLeadershipSpotsFilledDetail($admin, $filters);

        return view('admin.ded-health-leadership-spots', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedMembershipConversionDetail(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getMembershipConversionDetail($admin, $filters);

        return view('admin.ded-health-membership-conversion', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedReferralActivityDetail(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getReferralActivityDetail($admin, $filters);

        return view('admin.ded-health-referral-activity', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedIndustriesOverview(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $industries = DB::table('circle_categories')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'industry_id' => $request->query('industry_id'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getIndustriesOverview($admin, $filters);

        return view('admin.ded-industries-overview', [
            'records' => $data['records'],
            'summary' => $data['summary'],
            'districtCircles' => $districtCircles,
            'industries' => $industries,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    public function dedIndustryDetail(Request $request, string $id): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;
        abort_unless($districtName !== null, 403);

        $districtCirclesQuery = Circle::query();
        $this->applyDedCircleScope($districtCirclesQuery, $admin);
        $districtCircles = (clone $districtCirclesQuery)
            ->orderBy('name')
            ->get(['id', 'name']);

        $filters = [
            'circle_id' => $request->query('circle_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $analytics = app(DistrictAnalyticsService::class);
        $data = $analytics->getIndustryDetail($admin, $id, $filters);

        return view('admin.ded-industry-detail', [
            'industryId' => $id,
            'summary' => $data['summary'],
            'members' => $data['members'],
            'circles' => $data['circles'],
            'districtCircles' => $districtCircles,
            'filters' => $filters,
            'districtName' => $districtName,
        ]);
    }

    private function scopedTableCount($admin, string $table, string $userColumn, bool $hasIsDeleted = false, ?string $peerColumn = null, ?string $circleId = null): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
            return 0;
        }

        $query = DB::table("{$table} as activity");

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('activity.deleted_at');
        }

        if ($hasIsDeleted && Schema::hasColumn($table, 'is_deleted')) {
            $query->where('activity.is_deleted', false);
        }

        $qualifiedUserColumn = "activity.{$userColumn}";
        $qualifiedPeerColumn = ($peerColumn && Schema::hasColumn($table, $peerColumn)) ? "activity.{$peerColumn}" : null;

        AdminCircleScope::applyToActivityQuery(
            $query,
            $admin,
            $qualifiedUserColumn,
            $qualifiedPeerColumn
        );

        if ($circleId) {
            $this->applyCircleFilterToActivityQuery($query, $qualifiedUserColumn, $qualifiedPeerColumn, $circleId);
        }

        return (int) $query->count();
    }

    private function scopedCoinsEarned($admin, ?string $circleId = null): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'coins_balance')) {
            return 0;
        }

        $query = User::query();
        AdminCircleScope::applyToUsersQuery($query, $admin);

        if ($circleId) {
            $this->applyCircleFilterToUsersQuery($query, $circleId);
        }

        return (int) $query->sum('users.coins_balance');
    }

    private function scopedPendingRequestsCount($admin, ?string $circleId = null): int
    {
        if (! Schema::hasTable('circle_join_requests') || ! Schema::hasColumn('circle_join_requests', 'user_id')) {
            return 0;
        }

        $query = DB::table('circle_join_requests as activity');

        if (Schema::hasColumn('circle_join_requests', 'status')) {
            $query->whereIn('activity.status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee']);
        }

        AdminCircleScope::applyToActivityQuery($query, $admin, 'activity.user_id', null);

        if ($circleId) {
            if (Schema::hasColumn('circle_join_requests', 'circle_id')) {
                $query->where('activity.circle_id', $circleId);
            } else {
                $this->applyCircleFilterToActivityQuery($query, 'activity.user_id', null, $circleId);
            }
        }

        return (int) $query->count();
    }

    private function applyCircleFilterToUsersQuery($query, string $circleId): void
    {
        $query->whereExists(function ($subQuery) use ($circleId) {
            $subQuery->selectRaw(1)
                ->from('circle_members as dashboard_circle_members')
                ->whereColumn('dashboard_circle_members.user_id', 'users.id')
                ->where('dashboard_circle_members.status', 'approved')
                ->whereNull('dashboard_circle_members.deleted_at')
                ->where('dashboard_circle_members.circle_id', $circleId);
        });
    }

    private function applyCircleFilterToActivityQuery($query, string $userColumn, ?string $peerColumn, string $circleId): void
    {
        $query->where(function ($scopeQuery) use ($userColumn, $peerColumn, $circleId) {
            $this->whereActivityUserBelongsToCircle($scopeQuery, $userColumn, $circleId);

            if ($peerColumn) {
                $scopeQuery->orWhere(function ($peerScope) use ($peerColumn, $circleId) {
                    $this->whereActivityUserBelongsToCircle($peerScope, $peerColumn, $circleId);
                });
            }
        });
    }

    private function whereActivityUserBelongsToCircle($query, string $userColumn, string $circleId): void
    {
        $query->whereExists(function ($subQuery) use ($userColumn, $circleId) {
            $subQuery->selectRaw(1)
                ->from('circle_members as dashboard_activity_circle_members')
                ->whereColumn('dashboard_activity_circle_members.user_id', $userColumn)
                ->where('dashboard_activity_circle_members.status', 'approved')
                ->whereNull('dashboard_activity_circle_members.deleted_at')
                ->where('dashboard_activity_circle_members.circle_id', $circleId);
        });
    }

    private function applyDedCircleScope($query, $admin): void
    {
        AdminCircleScope::applyToCirclesQuery($query, $admin);
    }

    private function safeCountTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function safeReportedPostsCount(): int
    {
        if (Schema::hasTable('post_reports')) {
            return (int) DB::table('post_reports')->distinct()->count('post_id');
        }

        if (Schema::hasTable('reported_posts')) {
            return (int) DB::table('reported_posts')->count();
        }

        if ($this->hasTableColumn('posts', 'is_reported')) {
            return (int) DB::table('posts')->where('is_reported', true)->count();
        }

        if ($this->hasTableColumn('posts', 'reported_at')) {
            return (int) DB::table('posts')->whereNotNull('reported_at')->count();
        }

        return 0;
    }
}
