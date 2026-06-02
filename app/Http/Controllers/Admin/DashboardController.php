<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
