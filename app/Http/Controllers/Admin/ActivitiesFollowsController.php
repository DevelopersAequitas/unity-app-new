<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\IndustryScopeService;
use App\Support\ActivityUserFilter;
use App\Support\AdminCircleScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivitiesFollowsController extends Controller
{
    // ──────────────────────────────────────────────
    // Main Index
    // ──────────────────────────────────────────────

    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);

        $summary = $this->buildSummary($filters);
        $trends = $this->buildTrends($filters);
        $baseQuery = $this->baseQuery($filters);
        $total = (clone $baseQuery)->count();

        $items = (clone $baseQuery)
            ->select([
                'uf.id',
                'uf.follower_id',
                'uf.following_id',
                'uf.status',
                'uf.created_at',
                'follower.display_name as follower_display_name',
                'follower.first_name as follower_first_name',
                'follower.last_name as follower_last_name',
                'follower.email as follower_email',
                'follower.city as follower_city',
                'follower.membership_status as follower_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', follower.first_name, follower.last_name)), ''), follower.display_name, '—') as follower_name"),
                'followed.display_name as followed_display_name',
                'followed.first_name as followed_first_name',
                'followed.last_name as followed_last_name',
                'followed.email as followed_email',
                'followed.city as followed_city',
                'followed.membership_status as followed_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', followed.first_name, followed.last_name)), ''), followed.display_name, '—') as followed_name"),
            ])
            ->orderBy($this->resolveSortColumn($filters['sort']), $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        $topFollowed = $this->topMostFollowed($filters);
        $topFollowing = $this->topMostFollowing($filters);

        return view('admin.activities.follows.index', [
            'items' => $items,
            'filters' => $filters,
            'summary' => $summary,
            'trends' => $trends,
            'topFollowed' => $topFollowed,
            'topFollowing' => $topFollowing,
            'total' => $total,
            'circles' => $this->circleOptions(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Export
    // ──────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->buildFilters($request);
        $filename = 'follow_analytics_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            try {
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'Follow ID',
                    'Follower Name',
                    'Follower Email',
                    'Follower City',
                    'Follower Membership Status',
                    'Followed Member Name',
                    'Followed Member Email',
                    'Followed Member City',
                    'Followed Member Membership Status',
                    'Status',
                    'Follow Date',
                    'Follow Time',
                ]);

                $this->baseQuery($filters)
                    ->select([
                        'uf.id',
                        'uf.status',
                        'uf.created_at',
                        'follower.first_name as f_first', 'follower.last_name as f_last',
                        'follower.display_name as f_display',
                        'follower.email as f_email',
                        'follower.city as f_city',
                        'follower.membership_status as f_membership',
                        'followed.first_name as d_first', 'followed.last_name as d_last',
                        'followed.display_name as d_display',
                        'followed.email as d_email',
                        'followed.city as d_city',
                        'followed.membership_status as d_membership',
                    ])
                    ->orderBy('uf.created_at')
                    ->chunk(500, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            $at = $row->created_at ? Carbon::parse($row->created_at) : null;
                            fputcsv($handle, [
                                $row->id,
                                $this->formatName($row->f_display, $row->f_first, $row->f_last),
                                $row->f_email ?? '',
                                $row->f_city ?? '',
                                $row->f_membership ?? '',
                                $this->formatName($row->d_display, $row->d_first, $row->d_last),
                                $row->d_email ?? '',
                                $row->d_city ?? '',
                                $row->d_membership ?? '',
                                $row->status ?? '',
                                $at ? $at->format('Y-m-d') : '',
                                $at ? $at->format('H:i:s') : '',
                            ]);
                        }
                    });
            } finally {
                fclose($handle);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // ──────────────────────────────────────────────
    // Filters
    // ──────────────────────────────────────────────

    private function buildFilters(Request $request): array
    {
        $preset = (string) $request->query('date_preset', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        [$fromDt, $toDt] = $this->resolveDateRange($preset, $from, $to);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'date_preset' => $preset,
            'from' => $from,
            'to' => $to,
            'from_dt' => $fromDt,
            'to_dt' => $toDt,
            'circle_id' => (string) $request->query('circle_id', ''),
            'status' => (string) $request->query('status', ''),
            'follower_name' => trim((string) $request->query('follower_name', '')),
            'follower_email' => trim((string) $request->query('follower_email', '')),
            'follower_city' => trim((string) $request->query('follower_city', '')),
            'follower_membership' => trim((string) $request->query('follower_membership', '')),
            'followed_name' => trim((string) $request->query('followed_name', '')),
            'followed_email' => trim((string) $request->query('followed_email', '')),
            'followed_city' => trim((string) $request->query('followed_city', '')),
            'followed_membership' => trim((string) $request->query('followed_membership', '')),
            'per_page' => $perPage,
            'sort' => (string) $request->query('sort', 'created_at'),
            'direction' => strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    // ──────────────────────────────────────────────
    // Base Query
    // ──────────────────────────────────────────────

    private function baseQuery(array $filters)
    {
        $query = DB::table('user_follows as uf')
            ->join('users as follower', 'follower.id', '=', 'uf.follower_id')
            ->join('users as followed', 'followed.id', '=', 'uf.following_id');

        if ($filters['q'] !== '') {
            $like = '%'.$this->escapeLike($filters['q']).'%';
            $query->where(function ($q) use ($like) {
                $q->where('follower.display_name', 'ILIKE', $like)
                    ->orWhere('follower.first_name', 'ILIKE', $like)
                    ->orWhere('follower.last_name', 'ILIKE', $like)
                    ->orWhere('follower.email', 'ILIKE', $like)
                    ->orWhere('followed.display_name', 'ILIKE', $like)
                    ->orWhere('followed.first_name', 'ILIKE', $like)
                    ->orWhere('followed.last_name', 'ILIKE', $like)
                    ->orWhere('followed.email', 'ILIKE', $like);
            });
        }

        if ($filters['from_dt']) {
            $query->where('uf.created_at', '>=', $filters['from_dt']);
        }
        if ($filters['to_dt']) {
            $query->where('uf.created_at', '<=', $filters['to_dt']);
        }

        if ($filters['status'] !== '') {
            $query->whereRaw('uf.status::text = ?', [$filters['status']]);
        }

        if ($filters['follower_name'] !== '') {
            $like = '%'.$this->escapeLike($filters['follower_name']).'%';
            $query->whereRaw("coalesce(nullif(trim(concat_ws(' ', follower.first_name, follower.last_name)), ''), follower.display_name, '') ILIKE ?", [$like]);
        }
        if ($filters['follower_email'] !== '') {
            $query->where('follower.email', 'ILIKE', '%'.$this->escapeLike($filters['follower_email']).'%');
        }
        if ($filters['follower_city'] !== '') {
            $query->where('follower.city', 'ILIKE', '%'.$this->escapeLike($filters['follower_city']).'%');
        }
        if ($filters['follower_membership'] !== '') {
            $query->where('follower.membership_status', $filters['follower_membership']);
        }
        if ($filters['followed_name'] !== '') {
            $like = '%'.$this->escapeLike($filters['followed_name']).'%';
            $query->whereRaw("coalesce(nullif(trim(concat_ws(' ', followed.first_name, followed.last_name)), ''), followed.display_name, '') ILIKE ?", [$like]);
        }
        if ($filters['followed_email'] !== '') {
            $query->where('followed.email', 'ILIKE', '%'.$this->escapeLike($filters['followed_email']).'%');
        }
        if ($filters['followed_city'] !== '') {
            $query->where('followed.city', 'ILIKE', '%'.$this->escapeLike($filters['followed_city']).'%');
        }
        if ($filters['followed_membership'] !== '') {
            $query->where('followed.membership_status', $filters['followed_membership']);
        }

        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'follower.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }

        $this->applyAdminScope($query, 'uf.follower_id', 'uf.following_id');

        return $query;
    }

    // ──────────────────────────────────────────────
    // Summary Stats
    // ──────────────────────────────────────────────

    private function buildSummary(array $filters): array
    {
        $stats = (clone $this->baseQuery($filters))->selectRaw("
            COUNT(*) as total_follows,
            COUNT(*) FILTER (WHERE uf.status::text = 'accepted') as accepted_follows,
            COUNT(*) FILTER (WHERE uf.status::text = 'pending') as pending_follows,
            COUNT(*) FILTER (WHERE uf.status::text = 'rejected') as rejected_follows,
            COUNT(*) FILTER (WHERE uf.status::text = 'blocked') as blocked_follows,
            COUNT(DISTINCT uf.follower_id) as unique_followers,
            COUNT(DISTINCT uf.following_id) as unique_followed
        ")->first();

        $total = (int) ($stats->total_follows ?? 0);
        $acceptedF = (int) ($stats->accepted_follows ?? 0);
        $pendingF = (int) ($stats->pending_follows ?? 0);
        $rejectedF = (int) ($stats->rejected_follows ?? 0);
        $blockedF = (int) ($stats->blocked_follows ?? 0);
        $uniqueF = (int) ($stats->unique_followers ?? 0);
        $uniqueD = (int) ($stats->unique_followed ?? 0);
        $uniqueAll = max(1, $uniqueF + $uniqueD);
        $avgPerMember = round($total / $uniqueAll, 2);

        // Avg per day
        $dayCount = 1;
        if ($filters['from_dt'] && $filters['to_dt']) {
            $dayCount = max(1, Carbon::parse($filters['from_dt'])->diffInDays(Carbon::parse($filters['to_dt'])) + 1);
        }
        $avgPerDay = $dayCount > 0 ? round($total / $dayCount, 2) : 0;

        return [
            'total_follows' => $total,
            'accepted_follows' => $acceptedF,
            'pending_follows' => $pendingF,
            'rejected_follows' => $rejectedF,
            'blocked_follows' => $blockedF,
            'unique_followers' => $uniqueF,
            'unique_followed' => $uniqueD,
            'avg_per_member' => $avgPerMember,
            'avg_per_day' => $avgPerDay,
        ];
    }

    // ──────────────────────────────────────────────
    // Trends
    // ──────────────────────────────────────────────

    private function buildTrends(array $filters): array
    {
        $trendFrom = $filters['from_dt'] ?? now()->subDays(29)->startOfDay();
        $trendTo = $filters['to_dt'] ?? now()->endOfDay();

        if (Carbon::parse($trendFrom)->diffInDays(Carbon::parse($trendTo)) > 90) {
            $trendFrom = Carbon::parse($trendTo)->subDays(89)->startOfDay();
        }

        $query = DB::table('user_follows as uf')
            ->join('users as follower', 'follower.id', '=', 'uf.follower_id')
            ->join('users as followed', 'followed.id', '=', 'uf.following_id');

        $this->applyAdminScope($query, 'uf.follower_id', 'uf.following_id');

        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('circle_members as cm_f')
                    ->whereColumn('cm_f.user_id', 'uf.follower_id')
                    ->where('cm_f.circle_id', $filters['circle_id']);
            });
        }

        $query->where('uf.created_at', '>=', $trendFrom)->where('uf.created_at', '<=', $trendTo);

        $rows = $query->selectRaw("DATE(uf.created_at AT TIME ZONE 'UTC') as day, COUNT(*) as total")
            ->groupByRaw("DATE(uf.created_at AT TIME ZONE 'UTC')")
            ->orderByRaw("DATE(uf.created_at AT TIME ZONE 'UTC')")
            ->get();

        $fromDate = Carbon::parse($trendFrom)->startOfDay();
        $toDate = Carbon::parse($trendTo)->endOfDay();

        if ($rows->isEmpty() && empty($filters['from_dt']) && empty($filters['to_dt'])) {
            $latestDate = DB::table('user_follows')->max('created_at');
            if ($latestDate) {
                $toDate = Carbon::parse($latestDate)->endOfDay();
                $fromDate = $toDate->copy()->subDays(29)->startOfDay();

                $reQuery = DB::table('user_follows as uf')
                    ->join('users as follower', 'follower.id', '=', 'uf.follower_id')
                    ->join('users as followed', 'followed.id', '=', 'uf.following_id');
                $this->applyAdminScope($reQuery, 'uf.follower_id', 'uf.following_id');
                if ($filters['circle_id'] !== '') {
                    $reQuery->whereExists(function ($sub) use ($filters) {
                        $sub->selectRaw('1')->from('circle_members as cm_f')
                            ->whereColumn('cm_f.user_id', 'uf.follower_id')
                            ->where('cm_f.circle_id', $filters['circle_id']);
                    });
                }
                $reQuery->where('uf.created_at', '>=', $fromDate)->where('uf.created_at', '<=', $toDate);
                $rows = $reQuery->selectRaw("DATE(uf.created_at AT TIME ZONE 'UTC') as day, COUNT(*) as total")
                    ->groupByRaw("DATE(uf.created_at AT TIME ZONE 'UTC')")
                    ->orderByRaw("DATE(uf.created_at AT TIME ZONE 'UTC')")
                    ->get();
            }
        }

        $dataByDay = [];
        foreach ($rows as $row) {
            $dataByDay[$row->day] = $row;
        }

        $labels = [];
        $totals = [];

        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $dayKey = $curr->format('Y-m-d');
            $labels[] = $curr->format('d M');
            $row = $dataByDay[$dayKey] ?? null;
            $totals[] = $row ? (int) $row->total : 0;
            $curr->addDay();
        }

        return compact('labels', 'totals');
    }

    // ──────────────────────────────────────────────
    // Top Reports
    // ──────────────────────────────────────────────

    private function topMostFollowed(array $filters)
    {
        return DB::table('user_follows as uf')
            ->join('users as followed', 'followed.id', '=', 'uf.following_id')
            ->when($filters['from_dt'], fn ($q) => $q->where('uf.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn ($q) => $q->where('uf.created_at', '<=', $filters['to_dt']))
            ->tap(fn ($q) => $this->applyAdminScope($q, 'uf.following_id', null))
            ->groupBy('uf.following_id', 'followed.display_name', 'followed.first_name', 'followed.last_name', 'followed.email', 'followed.city', 'followed.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'uf.following_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', followed.first_name, followed.last_name)), ''), followed.display_name, '—') as member_name"),
                'followed.email as member_email',
                'followed.city as member_city',
                'followed.membership_status',
                DB::raw('COUNT(*) as total_followers'),
            ])
            ->get();
    }

    private function topMostFollowing(array $filters)
    {
        return DB::table('user_follows as uf')
            ->join('users as follower', 'follower.id', '=', 'uf.follower_id')
            ->when($filters['from_dt'], fn ($q) => $q->where('uf.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn ($q) => $q->where('uf.created_at', '<=', $filters['to_dt']))
            ->tap(fn ($q) => $this->applyAdminScope($q, 'uf.follower_id', null))
            ->groupBy('uf.follower_id', 'follower.display_name', 'follower.first_name', 'follower.last_name', 'follower.email', 'follower.city', 'follower.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'uf.follower_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', follower.first_name, follower.last_name)), ''), follower.display_name, '—') as member_name"),
                'follower.email as member_email',
                'follower.city as member_city',
                'follower.membership_status',
                DB::raw('COUNT(*) as total_following'),
            ])
            ->get();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function resolveDateRange(string $preset, string $from, string $to): array
    {
        $tz = config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        return match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $from !== '' ? Carbon::parse($from, $tz)->startOfDay() : null,
                $to !== '' ? Carbon::parse($to, $tz)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    private function resolveSortColumn(string $sort): string
    {
        return match ($sort) {
            'follower_name' => 'follower.first_name',
            'followed_name' => 'followed.first_name',
            'follower_city' => 'follower.city',
            'followed_city' => 'followed.city',
            default => 'uf.created_at',
        };
    }

    private function applyAdminScope($query, string $primaryColumn, ?string $peerColumn): void
    {
        $admin = auth('admin')->user();
        AdminCircleScope::applyToActivityQuery($query, $admin, $primaryColumn, $peerColumn);
        app(IndustryScopeService::class)->applyToActivityQuery($query, $admin, array_filter([$primaryColumn, $peerColumn]));
        ActivityUserFilter::applyToActivityQuery($query, $primaryColumn, null);
    }

    private function circleOptions()
    {
        return DB::table('circles')->select(['id', 'name'])->orderBy('name')->get();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    private function formatName(?string $display, ?string $first, ?string $last): string
    {
        $full = trim(($first ?? '').' '.($last ?? ''));

        return $full !== '' ? $full : ($display ?? '—');
    }
}
