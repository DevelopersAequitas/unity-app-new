<?php

namespace App\Http\Controllers\Admin\IndustryDirector;

use App\Http\Controllers\Controller;
use App\Services\IndustryDirector\IndustryScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class IndustryDirectorDashboardController extends Controller
{
    public function __construct(private readonly IndustryScopeService $industryScope)
    {
    }

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        $industryId = (string) ($request->attributes->get('industry_id')
            ?: session('industry_director.industry_id')
            ?: $this->industryScope->assignedIndustryOrFail((string) $admin->id));

        $industryName = $this->industryScope->industryName($industryId) ?: 'Assigned Industry';
        $circleIds = $this->industryScope->industryCircleIds($industryId);
        $memberIds = $this->industryScope->industryMemberIds($industryId);

        $stats = [
            'total_members' => $memberIds->count(),
            'active_members' => $this->memberQuery($industryId)->where('users.membership_status', '!=', 'visitor')->count(),
            'new_registrations' => $this->memberQuery($industryId)->whereDate('users.created_at', '>=', now()->subDays(30)->toDateString())->count(),
            'total_activities' => $this->totalActivities($industryId),
            'total_posts' => $this->totalPosts($industryId),
            'pending_requests' => $this->pendingRequests($industryId),
            'total_circles' => count($circleIds),
            'total_coins_earned' => $this->totalCoinsEarned($industryId),
            'life_impact' => $this->lifeImpactTotal($industryId),
        ];

        $charts = [
            'membership_growth' => $this->monthlySeries('users', 'created_at', $industryId, 'member'),
            'activity_trend' => $this->activityTrend($industryId),
            'coins_trend' => $this->monthlySeries('coins_ledger', 'created_at', $industryId, 'coins'),
            'life_impact_trend' => $this->monthlySeries('life_impact_histories', 'created_at', $industryId, 'life_impact'),
        ];

        return view('admin.industry-director.dashboard', [
            'industryId' => $industryId,
            'industryName' => $industryName,
            'stats' => $stats,
            'charts' => $charts,
            'hasData' => $stats['total_members'] > 0 || $stats['total_circles'] > 0,
        ]);
    }

    private function memberQuery(string $industryId)
    {
        $query = DB::table('users');

        return $this->industryScope->applyMemberScope($query, $industryId);
    }

    private function totalActivities(string $industryId): int
    {
        $tables = [
            ['testimonials', 'from_user_id'],
            ['referrals', 'from_user_id'],
            ['business_deals', 'from_user_id'],
            ['p2p_meetings', 'initiator_user_id'],
            ['requirements', 'user_id'],
            ['visitor_registrations', 'user_id'],
            ['activities', 'user_id'],
        ];

        return collect($tables)->sum(function (array $table) use ($industryId): int {
            [$tableName, $userColumn] = $table;

            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $userColumn)) {
                return 0;
            }

            $query = DB::table($tableName);
            $this->industryScope->applyUserColumnScope($query, $userColumn, $industryId);

            return (int) $query->count();
        });
    }

    private function totalPosts(string $industryId): int
    {
        if (! Schema::hasTable('posts')) {
            return 0;
        }

        $query = DB::table('posts');
        $this->industryScope->applyPostScope($query, $industryId);

        if (Schema::hasColumn('posts', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        return (int) $query->count();
    }

    private function pendingRequests(string $industryId): int
    {
        $total = 0;

        if (Schema::hasTable('coin_claim_requests')) {
            $query = DB::table('coin_claim_requests')->where('status', 'pending');
            $this->industryScope->applyUserColumnScope($query, 'user_id', $industryId);
            $total += (int) $query->count();
        }

        if (Schema::hasTable('circle_join_requests')) {
            $query = DB::table('circle_join_requests')
                ->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee']);
            $this->industryScope->applyCircleColumnScope($query, 'circle_id', $industryId);
            $total += (int) $query->count();
        }

        if (Schema::hasTable('impacts')) {
            $query = DB::table('impacts')->where('status', 'pending');
            $this->industryScope->applyUserColumnScope($query, 'user_id', $industryId);
            $total += (int) $query->count();
        }

        if (Schema::hasTable('visitor_registrations') && Schema::hasColumn('visitor_registrations', 'status')) {
            $query = DB::table('visitor_registrations')->where('status', 'pending');
            $this->industryScope->applyUserColumnScope($query, 'user_id', $industryId);
            $total += (int) $query->count();
        }

        return $total;
    }

    private function totalCoinsEarned(string $industryId): int
    {
        if (! Schema::hasTable('coins_ledger')) {
            return 0;
        }

        $query = DB::table('coins_ledger')->where('amount', '>', 0);
        $this->industryScope->applyCoinsScope($query, $industryId);

        return (int) $query->sum('amount');
    }

    private function lifeImpactTotal(string $industryId): int
    {
        if (! Schema::hasTable('life_impact_histories')) {
            return 0;
        }

        $query = DB::table('life_impact_histories');
        $this->industryScope->applyLifeImpactScope($query, $industryId);

        if (Schema::hasColumn('life_impact_histories', 'counted_in_total')) {
            $query->where('counted_in_total', true);
        }

        return (int) $query->sum(DB::raw('COALESCE(impact_value, life_impacted, 0)'));
    }

    private function monthlySeries(string $table, string $dateColumn, string $industryId, string $type): array
    {
        $months = collect(range(5, 0))->mapWithKeys(function (int $monthsAgo): array {
            $month = CarbonImmutable::now()->subMonths($monthsAgo)->startOfMonth();

            return [$month->format('Y-m') => 0];
        });

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $dateColumn)) {
            return $this->formatSeries($months);
        }

        $query = DB::table($table)
            ->selectRaw("to_char({$dateColumn}, 'YYYY-MM') as month_key");

        if ($type === 'coins') {
            $query->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total');
            $this->industryScope->applyCoinsScope($query, $industryId);
        } elseif ($type === 'life_impact') {
            $query->selectRaw('SUM(COALESCE(impact_value, life_impacted, 0)) as total');
            $this->industryScope->applyLifeImpactScope($query, $industryId);
        } else {
            $query->selectRaw('COUNT(*) as total');
            $this->industryScope->applyMemberScope($query, $industryId);
        }

        $query->whereDate($dateColumn, '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->groupBy('month_key')
            ->orderBy('month_key');

        foreach ($query->get() as $row) {
            if ($months->has($row->month_key)) {
                $months[$row->month_key] = (int) $row->total;
            }
        }

        return $this->formatSeries($months);
    }

    private function activityTrend(string $industryId): array
    {
        $months = collect(range(5, 0))->mapWithKeys(function (int $monthsAgo): array {
            $month = CarbonImmutable::now()->subMonths($monthsAgo)->startOfMonth();

            return [$month->format('Y-m') => 0];
        });

        foreach ([['activities', 'user_id'], ['testimonials', 'from_user_id'], ['referrals', 'from_user_id'], ['business_deals', 'from_user_id'], ['requirements', 'user_id'], ['visitor_registrations', 'user_id']] as [$table, $userColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_at') || ! Schema::hasColumn($table, $userColumn)) {
                continue;
            }

            $query = DB::table($table)
                ->selectRaw("to_char(created_at, 'YYYY-MM') as month_key")
                ->selectRaw('COUNT(*) as total')
                ->whereDate('created_at', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
                ->groupBy('month_key')
                ->orderBy('month_key');

            $this->industryScope->applyUserColumnScope($query, $userColumn, $industryId);

            foreach ($query->get() as $row) {
                if ($months->has($row->month_key)) {
                    $months[$row->month_key] += (int) $row->total;
                }
            }
        }

        return $this->formatSeries($months);
    }

    private function formatSeries($months): array
    {
        return [
            'labels' => $months->keys()->map(fn (string $month) => CarbonImmutable::createFromFormat('Y-m', $month)->format('M Y'))->values()->all(),
            'values' => $months->values()->all(),
        ];
    }
}
