<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now();
        $admin = Auth::guard('admin')->user();
        $allowedCircleIds = AdminAccess::isIndustryScoped($admin) ? AdminAccess::allowedCircleIds($admin) : null;

        $totalUsers = $this->scopedUsersCount($allowedCircleIds);
        $newSignups = ($this->hasTableColumn('users', 'created_at'))
            ? $this->scopedUsersQuery($allowedCircleIds)->whereDate('users.created_at', $today->toDateString())->count()
            : 0;
        $premiumUpgrades = ($this->hasTableColumn('users', 'membership_status'))
            ? $this->scopedUsersQuery($allowedCircleIds)->where('users.membership_status', 'premium')->count()
            : 0;

        $activeCircles = ($this->hasTableColumn('circles', 'status'))
            ? $this->scopedCirclesQuery($allowedCircleIds)->where('status', 'active')->count()
            : $this->scopedCirclesCount($allowedCircleIds);
        $pendingApprovals = ($this->hasTableColumn('circles', 'status'))
            ? $this->scopedCirclesQuery($allowedCircleIds)->where('status', 'pending')->count()
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


    private function scopedUsersQuery(?array $allowedCircleIds)
    {
        $query = DB::table('users');

        if (is_array($allowedCircleIds)) {
            if ($allowedCircleIds === []) {
                return $query->whereRaw('1=0');
            }

            $query->whereExists(function ($subQuery) use ($allowedCircleIds): void {
                $subQuery->selectRaw(1)
                    ->from('circle_members as cm')
                    ->whereColumn('cm.user_id', 'users.id')
                    ->where('cm.status', config('circle.member_joined_status', 'approved'))
                    ->whereNull('cm.deleted_at')
                    ->whereIn('cm.circle_id', $allowedCircleIds);
            });
        }

        return $query;
    }

    private function scopedUsersCount(?array $allowedCircleIds): int
    {
        return Schema::hasTable('users') ? (int) $this->scopedUsersQuery($allowedCircleIds)->count() : 0;
    }

    private function scopedCirclesQuery(?array $allowedCircleIds)
    {
        $query = DB::table('circles');

        if (is_array($allowedCircleIds)) {
            if ($allowedCircleIds === []) {
                return $query->whereRaw('1=0');
            }

            $query->whereIn('id', $allowedCircleIds);
        }

        return $query;
    }

    private function scopedCirclesCount(?array $allowedCircleIds): int
    {
        return Schema::hasTable('circles') ? (int) $this->scopedCirclesQuery($allowedCircleIds)->count() : 0;
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
