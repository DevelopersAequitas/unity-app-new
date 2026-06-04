<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now();

        $totalUsers = $this->safeCountTable('users');
        $newSignups = ($this->hasTableColumn('users', 'created_at'))
            ? DB::table('users')->whereDate('created_at', $today->toDateString())->count()
            : 0;
        $premiumUpgrades = ($this->hasTableColumn('users', 'membership_status'))
            ? DB::table('users')->where('membership_status', 'premium')->count()
            : 0;

        $activeCircles = ($this->hasTableColumn('circles', 'status'))
            ? DB::table('circles')->where('status', 'active')->count()
            : $this->safeCountTable('circles');
        $pendingApprovals = ($this->hasTableColumn('circles', 'status'))
            ? DB::table('circles')->where('status', 'pending')->count()
            : 0;

        $activitiesToday = ($this->hasTableColumn('activities', 'created_at'))
            ? DB::table('activities')->whereDate('created_at', $today->toDateString())->count()
            : 0;

        $supportRequests = $this->safeCountTable('support_requests');
        $reportedPosts = $this->safeReportedPostsCount();

        $coinsIssued = $this->safeCountTable('coin_ledgers');
        $walletCollections = $this->safeCountTable('wallet_transactions');

        $stats = [
            'newSignups' => (int) $newSignups,
            'premiumUpgrades' => (int) $premiumUpgrades,
            'activeCircles' => (int) $activeCircles,
            'pendingApprovals' => (int) $pendingApprovals,
            'coinsIssued' => (int) $coinsIssued,
            'walletCollections' => (int) $walletCollections,
            'supportRequests' => (int) $supportRequests,
            'activitiesToday' => (int) $activitiesToday,
            'reportedPosts' => (int) $reportedPosts,
            // Legacy keys for existing blade usage
            'total_users' => (int) $totalUsers,
            'active_circles' => (int) $activeCircles,
            'pending_approvals' => (int) $pendingApprovals,
            'new_signups' => (int) $newSignups,
        ];

        $pendingItems = [
            ['title' => 'Pending Activities Today', 'count' => (int) $activitiesToday],
            ['title' => 'Circles Awaiting Review', 'count' => (int) $pendingApprovals],
            ['title' => 'Reported Posts', 'count' => (int) $reportedPosts],
            ['title' => 'Support Requests', 'count' => (int) $supportRequests],
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'pendingItems' => $pendingItems,
        ]);
    }


    public function ded(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless(AdminAccess::isDed($admin), 403);

        $district = AdminAccess::assignedDedDistrict($admin);
        $districtId = $district['id'] ?? null;
        $districtName = $district['name'] ?? null;

        $circleOptions = $district ? AdminCircleScope::circleOptions($admin) : collect();
        $selectedCircleId = $district ? trim((string) $request->query('circle_id', '')) : '';
        if ($selectedCircleId !== '' && ! $circleOptions->contains(fn ($circle) => (string) $circle->id === $selectedCircleId)) {
            abort(403);
        }
        $selectedCircleId = $selectedCircleId !== '' ? $selectedCircleId : null;

        $stats = [
            'peers' => $district ? $this->districtUsersQuery($admin, $selectedCircleId)->count() : 0,
            'circles' => $district ? $this->districtCirclesCount($admin, $selectedCircleId) : 0,
            'referrals' => $district ? $this->districtActivityCount('referrals', 'from_user_id', $admin, $selectedCircleId) : 0,
            'requirements' => $district ? $this->districtActivityCount('requirements', 'user_id', $admin, $selectedCircleId) : 0,
            'testimonials' => $district ? $this->districtActivityCount('testimonials', 'from_user_id', $admin, $selectedCircleId) : 0,
            'businessDeals' => $district ? $this->districtActivityCount('business_deals', 'from_user_id', $admin, $selectedCircleId) : 0,
            'p2pMeetings' => $district ? $this->districtActivityCount('p2p_meetings', 'initiator_user_id', $admin, $selectedCircleId) : 0,
            'coinsEarned' => $district ? $this->districtCoinsEarned($admin, $selectedCircleId) : 0,
            'pendingRequests' => $district ? $this->districtCircleJoinRequests($admin, $selectedCircleId) : 0,
        ];

        $peers = $district
            ? $this->districtUsersQuery($admin, $selectedCircleId)
                ->select(['users.id', 'users.display_name', 'users.first_name', 'users.last_name', 'users.email', 'users.company_name', 'users.city', 'users.city_id'])
                ->with('city:id,name,district')
                ->latest('users.created_at')
                ->limit(10)
                ->get()
            : collect();

        return view('admin.ded.dashboard', [
            'districtId' => $districtId,
            'districtName' => $districtName,
            'stats' => $stats,
            'peers' => $peers,
            'circleOptions' => $circleOptions,
            'selectedCircleId' => $selectedCircleId,
        ]);
    }

        $dedLocation = AdminAccess::assignedDedLocation($admin);
        $districtName = $dedLocation['district_name'] ?? null;

        if (! $districtName) {
            return view('admin.ded-dashboard', [
                'districtName' => null,
                'stats' => [],
                'pendingItems' => [],
                'recentPeers' => collect(),
            ]);
        }

        $districtPeersQuery = User::query();
        AdminCircleScope::applyToUsersQuery($districtPeersQuery, $admin);

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

            $this->applyCircleFilterToUsersQuery($districtPeersQuery, $selectedCircleId);
        }

        $stats = [
            'total_users' => (int) (clone $districtPeersQuery)->count(),
            'active_circles' => $selectedCircleId !== '' ? 1 : (int) $districtCircles->count(),
            'testimonials' => $this->scopedTableCount($admin, 'testimonials', 'from_user_id', true, null, $selectedCircleId ?: null),
            'requirements' => $this->scopedTableCount($admin, 'requirements', 'user_id', false, null, $selectedCircleId ?: null),
            'referrals' => $this->scopedTableCount($admin, 'referrals', 'from_user_id', true, null, $selectedCircleId ?: null),
            'business_deals' => $this->scopedTableCount($admin, 'business_deals', 'from_user_id', true, null, $selectedCircleId ?: null),
            'p2p_meetings' => $this->scopedTableCount($admin, 'p2p_meetings', 'initiator_user_id', true, null, $selectedCircleId ?: null),
            'coins_earned' => $this->scopedCoinsEarned($admin, $selectedCircleId ?: null),
            'pending_requests' => $this->scopedPendingRequestsCount($admin, $selectedCircleId ?: null),
        ];

        $pendingItems = [
            ['title' => 'Pending Requests', 'count' => $stats['pending_requests']],
            ['title' => 'District Referrals', 'count' => $stats['referrals']],
            ['title' => 'District Requirements', 'count' => $stats['requirements']],
            ['title' => 'District Testimonials', 'count' => $stats['testimonials']],
        ];

        $recentPeersQuery = (clone $districtPeersQuery)->with('city')->latest('created_at')->limit(8);

        return view('admin.ded-dashboard', [
            'districtName' => $districtName,
            'stats' => $stats,
            'pendingItems' => $pendingItems,
            'recentPeers' => $recentPeersQuery->get(),
            'districtCircles' => $districtCircles,
            'selectedCircleId' => $selectedCircleId,
            'selectedCircle' => $selectedCircle,
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

    private function districtUsersQuery($admin, ?string $circleId = null)
    {
        $query = User::query()->from('users');
        AdminCircleScope::applyToUsersQuery($query, $admin);
        $this->applyCircleMemberFilter($query, 'users.id', $circleId);

        return $query;
    }

    private function districtCirclesCount($admin, ?string $circleId = null): int
    {
        $query = Circle::query()->from('circles');
        AdminCircleScope::applyDedDistrictCircleScope($query, $admin);
        if ($circleId) {
            $query->where('circles.id', $circleId);
        }

        return (int) $query->count();
    }

    private function districtActivityCount(string $table, string $userColumn, $admin, ?string $circleId = null): int
    {
        if (! $this->hasTableColumn($table, $userColumn)) {
            return 0;
        }

        $query = DB::table($table)->whereNotNull($userColumn);
        AdminCircleScope::applyToActivityQuery($query, $admin, $table . '.' . $userColumn, null);
        $this->applyCircleMemberFilter($query, $table . '.' . $userColumn, $circleId);
        $this->applySoftDeleteFilters($query, $table);

        return (int) $query->count();
    }

    private function districtCoinsEarned($admin, ?string $circleId = null): int
    {
        $query = User::query()->from('users');
        AdminCircleScope::applyToUsersQuery($query, $admin);
        $this->applyCircleMemberFilter($query, 'users.id', $circleId);

        return (int) $query->sum(DB::raw('COALESCE(users.coins_balance, 0)'));
    }

    private function districtCircleJoinRequests($admin, ?string $circleId = null): int
    {
        if (! Schema::hasTable('circle_join_requests')) {
            return 0;
        }

        return (int) CircleJoinRequest::query()
            ->visibleToAdminUser($admin)
            ->when($circleId, fn ($query) => $query->where('circle_id', $circleId))
            ->whereIn('status', [
                CircleJoinRequest::STATUS_PENDING_CD_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_ID_APPROVAL,
                CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE,
            ])
            ->count();
    }

    private function applyCircleMemberFilter($query, string $userColumn, ?string $circleId): void
    {
        if (! $circleId) {
            return;
        }

        $query->whereExists(function ($subQuery) use ($userColumn, $circleId): void {
            $subQuery->selectRaw('1')
                ->from('circle_members as dashboard_circle_members')
                ->whereRaw('dashboard_circle_members.user_id::text = ' . $userColumn . '::text')
                ->where('dashboard_circle_members.circle_id', $circleId)
                ->where('dashboard_circle_members.status', 'approved')
                ->whereNull('dashboard_circle_members.deleted_at');
        });
    }

    private function applySoftDeleteFilters($query, string $table): void
    {
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull($table . '.deleted_at');
        }

        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where($table . '.is_deleted', false);
        }
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
