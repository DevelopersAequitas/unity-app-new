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

class ActivitiesConnectionsController extends Controller
{
    // ──────────────────────────────────────────────
    // Main Index
    // ──────────────────────────────────────────────

    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);

        // Summary stats
        $summary = $this->buildSummary($filters);

        // Trend data (last 30 days by default, or filtered range)
        $trends = $this->buildTrends($filters);

        // Paginated detail table
        $baseQuery = $this->baseQuery($filters);
        $total = (clone $baseQuery)->count();

        $items = (clone $baseQuery)
            ->select([
                'c.id',
                'c.requester_id',
                'c.addressee_id',
                'c.is_approved',
                'c.created_at',
                'c.approved_at',
                'actor.display_name as actor_display_name',
                'actor.first_name as actor_first_name',
                'actor.last_name as actor_last_name',
                'actor.email as actor_email',
                'actor.city as actor_city',
                'actor.membership_status as actor_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '—') as sender_name"),
                DB::raw("coalesce(actor.company_name, '') as sender_company"),
                'peer.display_name as peer_display_name',
                'peer.first_name as peer_first_name',
                'peer.last_name as peer_last_name',
                'peer.email as peer_email',
                'peer.city as peer_city',
                'peer.membership_status as peer_membership_status',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', peer.first_name, peer.last_name)), ''), peer.display_name, '—') as receiver_name"),
                DB::raw("coalesce(peer.company_name, '') as receiver_company"),
            ])
            ->orderBy($this->resolveSortColumn($filters['sort']), $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        // Top activity reports
        $topSenders    = $this->topMembersBySent($filters);
        $topReceivers  = $this->topMembersByReceived($filters);
        $topAccepted   = $this->topMembersByAccepted($filters);

        return view('admin.activities.connections.index', [
            'items'         => $items,
            'filters'       => $filters,
            'summary'       => $summary,
            'trends'        => $trends,
            'topSenders'    => $topSenders,
            'topReceivers'  => $topReceivers,
            'topAccepted'   => $topAccepted,
            'total'         => $total,
            'circles'       => $this->circleOptions(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Export
    // ──────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $filters  = $this->buildFilters($request);
        $filename = 'connections_analytics_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            try {
                fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
                fputcsv($handle, [
                    'Connection ID',
                    'Sender Name',
                    'Sender Email',
                    'Sender City',
                    'Sender Membership Status',
                    'Receiver Name',
                    'Receiver Email',
                    'Receiver City',
                    'Receiver Membership Status',
                    'Request Status',
                    'Request Date',
                    'Request Time',
                    'Response Date',
                    'Response Time',
                    'Response Duration (hours)',
                ]);

                $this->baseQuery($filters)
                    ->select([
                        'c.id',
                        'c.is_approved',
                        'c.created_at',
                        'c.approved_at',
                        'actor.first_name as actor_first_name',
                        'actor.last_name as actor_last_name',
                        'actor.display_name as actor_display_name',
                        'actor.email as actor_email',
                        'actor.city as actor_city',
                        'actor.membership_status as actor_membership_status',
                        'peer.first_name as peer_first_name',
                        'peer.last_name as peer_last_name',
                        'peer.display_name as peer_display_name',
                        'peer.email as peer_email',
                        'peer.city as peer_city',
                        'peer.membership_status as peer_membership_status',
                    ])
                    ->orderBy('c.created_at')
                    ->chunk(500, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            $senderName   = $this->formatName($row->actor_display_name, $row->actor_first_name, $row->actor_last_name);
                            $receiverName = $this->formatName($row->peer_display_name, $row->peer_first_name, $row->peer_last_name);

                            $requestedAt = $row->created_at ? Carbon::parse($row->created_at) : null;
                            $respondedAt = $row->approved_at ? Carbon::parse($row->approved_at) : null;

                            $durationHours = ($requestedAt && $respondedAt)
                                ? round($requestedAt->diffInMinutes($respondedAt) / 60, 2)
                                : '';

                            fputcsv($handle, [
                                $row->id,
                                $senderName,
                                $row->actor_email ?? '',
                                $row->actor_city ?? '',
                                $row->actor_membership_status ?? '',
                                $receiverName,
                                $row->peer_email ?? '',
                                $row->peer_city ?? '',
                                $row->peer_membership_status ?? '',
                                $row->is_approved ? 'Accepted' : 'Pending',
                                $requestedAt ? $requestedAt->format('Y-m-d') : '',
                                $requestedAt ? $requestedAt->format('H:i:s') : '',
                                $respondedAt ? $respondedAt->format('Y-m-d') : '',
                                $respondedAt ? $respondedAt->format('H:i:s') : '',
                                $durationHours,
                            ]);
                        }
                    });
            } finally {
                fclose($handle);
            }
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    // ──────────────────────────────────────────────
    // Filters Builder
    // ──────────────────────────────────────────────

    private function buildFilters(Request $request): array
    {
        $preset = (string) $request->query('date_preset', '');
        $from   = (string) $request->query('from', '');
        $to     = (string) $request->query('to', '');

        [$fromDt, $toDt] = $this->resolveDateRange($preset, $from, $to);

        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        $sort      = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [
            // Search
            'q'              => trim((string) $request->query('q', '')),
            // Date
            'date_preset'    => $preset,
            'from'           => $from,
            'to'             => $to,
            'from_dt'        => $fromDt,
            'to_dt'          => $toDt,
            // Circle
            'circle_id'      => (string) $request->query('circle_id', ''),
            // Advanced filters
            'sender_name'    => trim((string) $request->query('sender_name', '')),
            'sender_email'   => trim((string) $request->query('sender_email', '')),
            'sender_city'    => trim((string) $request->query('sender_city', '')),
            'sender_membership' => trim((string) $request->query('sender_membership', '')),
            'receiver_name'  => trim((string) $request->query('receiver_name', '')),
            'receiver_email' => trim((string) $request->query('receiver_email', '')),
            'receiver_city'  => trim((string) $request->query('receiver_city', '')),
            'receiver_membership' => trim((string) $request->query('receiver_membership', '')),
            'status'         => (string) $request->query('status', ''),
            // Pagination & sorting
            'per_page'       => $perPage,
            'sort'           => $sort,
            'direction'      => $direction,
        ];
    }

    // ──────────────────────────────────────────────
    // Base Query
    // ──────────────────────────────────────────────

    private function baseQuery(array $filters)
    {
        $query = DB::table('connections as c')
            ->join('users as actor', 'actor.id', '=', 'c.requester_id')
            ->join('users as peer', 'peer.id', '=', 'c.addressee_id');

        // Global search
        if ($filters['q'] !== '') {
            $like = '%' . $this->escapeLike($filters['q']) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('actor.display_name', 'ILIKE', $like)
                  ->orWhere('actor.first_name', 'ILIKE', $like)
                  ->orWhere('actor.last_name', 'ILIKE', $like)
                  ->orWhere('actor.email', 'ILIKE', $like)
                  ->orWhere('peer.display_name', 'ILIKE', $like)
                  ->orWhere('peer.first_name', 'ILIKE', $like)
                  ->orWhere('peer.last_name', 'ILIKE', $like)
                  ->orWhere('peer.email', 'ILIKE', $like);
            });
        }

        // Date range
        if ($filters['from_dt']) {
            $query->where('c.created_at', '>=', $filters['from_dt']);
        }
        if ($filters['to_dt']) {
            $query->where('c.created_at', '<=', $filters['to_dt']);
        }

        // Status filter
        if ($filters['status'] === 'accepted') {
            $query->where('c.is_approved', true);
        } elseif ($filters['status'] === 'pending') {
            $query->where('c.is_approved', false);
        }

        // Sender filters
        if ($filters['sender_name'] !== '') {
            $like = '%' . $this->escapeLike($filters['sender_name']) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '') ILIKE ?", [$like]);
            });
        }
        if ($filters['sender_email'] !== '') {
            $like = '%' . $this->escapeLike($filters['sender_email']) . '%';
            $query->where('actor.email', 'ILIKE', $like);
        }
        if ($filters['sender_city'] !== '') {
            $like = '%' . $this->escapeLike($filters['sender_city']) . '%';
            $query->where('actor.city', 'ILIKE', $like);
        }
        if ($filters['sender_membership'] !== '') {
            $query->where('actor.membership_status', $filters['sender_membership']);
        }

        // Receiver filters
        if ($filters['receiver_name'] !== '') {
            $like = '%' . $this->escapeLike($filters['receiver_name']) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("coalesce(nullif(trim(concat_ws(' ', peer.first_name, peer.last_name)), ''), peer.display_name, '') ILIKE ?", [$like]);
            });
        }
        if ($filters['receiver_email'] !== '') {
            $like = '%' . $this->escapeLike($filters['receiver_email']) . '%';
            $query->where('peer.email', 'ILIKE', $like);
        }
        if ($filters['receiver_city'] !== '') {
            $like = '%' . $this->escapeLike($filters['receiver_city']) . '%';
            $query->where('peer.city', 'ILIKE', $like);
        }
        if ($filters['receiver_membership'] !== '') {
            $query->where('peer.membership_status', $filters['receiver_membership']);
        }

        // Circle filter
        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'actor.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }

        $this->applyAdminScope($query, 'c.requester_id', 'c.addressee_id');

        return $query;
    }

    // ──────────────────────────────────────────────
    // Summary Stats
    // ──────────────────────────────────────────────

    private function buildSummary(array $filters): array
    {
        $q = $this->baseQuery($filters);

        $stats = (clone $q)->selectRaw("
            COUNT(*) as total_requests,
            COUNT(*) FILTER (WHERE c.is_approved = true) as total_accepted,
            COUNT(*) FILTER (WHERE c.is_approved = false) as total_pending,
            COUNT(DISTINCT c.requester_id) as unique_senders,
            COUNT(DISTINCT c.addressee_id) as unique_receivers
        ")->first();

        $totalRequests = (int) ($stats->total_requests ?? 0);
        $totalAccepted = (int) ($stats->total_accepted ?? 0);
        $totalPending  = (int) ($stats->total_pending ?? 0);
        $uniqueSenders = (int) ($stats->unique_senders ?? 0);
        $uniqueReceivers = (int) ($stats->unique_receivers ?? 0);

        // Average requests per member
        $uniqueMembers = max(1, $uniqueSenders + $uniqueReceivers);
        $avgPerMember  = $uniqueMembers > 0 ? round($totalRequests / $uniqueMembers, 2) : 0;

        // Average per day
        $dayCount = 1;
        if ($filters['from_dt'] && $filters['to_dt']) {
            $dayCount = max(1, Carbon::parse($filters['from_dt'])->diffInDays(Carbon::parse($filters['to_dt'])) + 1);
        } elseif ($filters['from_dt']) {
            $dayCount = max(1, Carbon::parse($filters['from_dt'])->diffInDays(now()) + 1);
        } else {
            // Use actual date range from data
            $dateRange = (clone $q)->selectRaw("MIN(c.created_at) as min_date, MAX(c.created_at) as max_date")->first();
            if ($dateRange && $dateRange->min_date && $dateRange->max_date) {
                $dayCount = max(1, Carbon::parse($dateRange->min_date)->diffInDays(Carbon::parse($dateRange->max_date)) + 1);
            }
        }
        $avgPerDay = $dayCount > 0 ? round($totalRequests / $dayCount, 2) : 0;

        $acceptanceRate = $totalRequests > 0 ? round(($totalAccepted / $totalRequests) * 100, 1) : 0;

        return [
            'total_requests'    => $totalRequests,
            'total_accepted'    => $totalAccepted,
            'total_pending'     => $totalPending,
            'unique_senders'    => $uniqueSenders,
            'unique_receivers'  => $uniqueReceivers,
            'avg_per_member'    => $avgPerMember,
            'avg_per_day'       => $avgPerDay,
            'acceptance_rate'   => $acceptanceRate,
        ];
    }

    // ──────────────────────────────────────────────
    // Trends (daily for charts)
    // ──────────────────────────────────────────────

    private function buildTrends(array $filters): array
    {
        $trendFrom = $filters['from_dt'] ?? now()->subDays(29)->startOfDay();
        $trendTo   = $filters['to_dt'] ?? now()->endOfDay();

        if (Carbon::parse($trendFrom)->diffInDays(Carbon::parse($trendTo)) > 90) {
            $trendFrom = Carbon::parse($trendTo)->subDays(89)->startOfDay();
        }

        $query = DB::table('connections as c')
            ->join('users as actor', 'actor.id', '=', 'c.requester_id')
            ->join('users as peer', 'peer.id', '=', 'c.addressee_id');

        $this->applyAdminScope($query, 'c.requester_id', 'c.addressee_id');

        if ($filters['circle_id'] !== '') {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'actor.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }

        $query->where('c.created_at', '>=', $trendFrom)
              ->where('c.created_at', '<=', $trendTo);

        $rows = $query->selectRaw("
            DATE(c.created_at AT TIME ZONE 'UTC') as day,
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE c.is_approved = true) as accepted,
            COUNT(*) FILTER (WHERE c.is_approved = false) as pending
        ")->groupByRaw("DATE(c.created_at AT TIME ZONE 'UTC')")
          ->orderByRaw("DATE(c.created_at AT TIME ZONE 'UTC')")
          ->get();

        $fromDate = Carbon::parse($trendFrom)->startOfDay();
        $toDate   = Carbon::parse($trendTo)->endOfDay();

        // If no records in the past 30 days and no explicit date filter given, find latest activity window
        if ($rows->isEmpty() && empty($filters['from_dt']) && empty($filters['to_dt'])) {
            $latestDate = DB::table('connections')->max('created_at');
            if ($latestDate) {
                $toDate   = Carbon::parse($latestDate)->endOfDay();
                $fromDate = $toDate->copy()->subDays(29)->startOfDay();

                $reQuery = DB::table('connections as c')
                    ->join('users as actor', 'actor.id', '=', 'c.requester_id')
                    ->join('users as peer', 'peer.id', '=', 'c.addressee_id');
                $this->applyAdminScope($reQuery, 'c.requester_id', 'c.addressee_id');
                if ($filters['circle_id'] !== '') {
                    $reQuery->whereExists(function ($sub) use ($filters) {
                        $sub->selectRaw('1')->from('circle_members as cm_filter')
                            ->whereColumn('cm_filter.user_id', 'actor.id')
                            ->where('cm_filter.circle_id', $filters['circle_id']);
                    });
                }
                $reQuery->where('c.created_at', '>=', $fromDate)->where('c.created_at', '<=', $toDate);
                $rows = $reQuery->selectRaw("
                    DATE(c.created_at AT TIME ZONE 'UTC') as day,
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE c.is_approved = true) as accepted,
                    COUNT(*) FILTER (WHERE c.is_approved = false) as pending
                ")->groupByRaw("DATE(c.created_at AT TIME ZONE 'UTC')")
                  ->orderByRaw("DATE(c.created_at AT TIME ZONE 'UTC')")
                  ->get();
            }
        }

        $dataByDay = [];
        foreach ($rows as $row) {
            $dataByDay[$row->day] = $row;
        }

        $labels   = [];
        $totals   = [];
        $accepted = [];
        $pending  = [];

        $curr = $fromDate->copy();
        while ($curr->lte($toDate)) {
            $dayKey     = $curr->format('Y-m-d');
            $labels[]   = $curr->format('d M');
            $row        = $dataByDay[$dayKey] ?? null;
            $totals[]   = $row ? (int) $row->total : 0;
            $accepted[] = $row ? (int) $row->accepted : 0;
            $pending[]  = $row ? (int) $row->pending : 0;
            $curr->addDay();
        }

        return compact('labels', 'totals', 'accepted', 'pending');
    }

    // ──────────────────────────────────────────────
    // Top Activity Reports
    // ──────────────────────────────────────────────

    private function topMembersBySent(array $filters)
    {
        return DB::table('connections as c')
            ->join('users as actor', 'actor.id', '=', 'c.requester_id')
            ->when($filters['from_dt'], fn($q) => $q->where('c.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn($q) => $q->where('c.created_at', '<=', $filters['to_dt']))
            ->tap(fn($q) => $this->applyAdminScope($q, 'c.requester_id', null))
            ->groupBy('c.requester_id', 'actor.display_name', 'actor.first_name', 'actor.last_name', 'actor.email', 'actor.city', 'actor.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'c.requester_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '—') as member_name"),
                'actor.email as member_email',
                'actor.city as member_city',
                'actor.membership_status',
                DB::raw('COUNT(*) as total_sent'),
                DB::raw("COUNT(*) FILTER (WHERE c.is_approved = true) as total_accepted"),
            ])
            ->get();
    }

    private function topMembersByReceived(array $filters)
    {
        return DB::table('connections as c')
            ->join('users as peer', 'peer.id', '=', 'c.addressee_id')
            ->when($filters['from_dt'], fn($q) => $q->where('c.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn($q) => $q->where('c.created_at', '<=', $filters['to_dt']))
            ->tap(fn($q) => $this->applyAdminScope($q, 'c.addressee_id', null))
            ->groupBy('c.addressee_id', 'peer.display_name', 'peer.first_name', 'peer.last_name', 'peer.email', 'peer.city', 'peer.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'c.addressee_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', peer.first_name, peer.last_name)), ''), peer.display_name, '—') as member_name"),
                'peer.email as member_email',
                'peer.city as member_city',
                'peer.membership_status',
                DB::raw('COUNT(*) as total_received'),
            ])
            ->get();
    }

    private function topMembersByAccepted(array $filters)
    {
        return DB::table('connections as c')
            ->join('users as actor', 'actor.id', '=', 'c.requester_id')
            ->where('c.is_approved', true)
            ->when($filters['from_dt'], fn($q) => $q->where('c.created_at', '>=', $filters['from_dt']))
            ->when($filters['to_dt'], fn($q) => $q->where('c.created_at', '<=', $filters['to_dt']))
            ->tap(fn($q) => $this->applyAdminScope($q, 'c.requester_id', null))
            ->groupBy('c.requester_id', 'actor.display_name', 'actor.first_name', 'actor.last_name', 'actor.email', 'actor.city', 'actor.membership_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->select([
                'c.requester_id as user_id',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '—') as member_name"),
                'actor.email as member_email',
                'actor.city as member_city',
                'actor.membership_status',
                DB::raw('COUNT(*) as total_accepted'),
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
            'today'          => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'      => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week'      => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'      => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month'     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month'     => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter'   => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter'   => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'this_year'      => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'      => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_7_days'    => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days'   => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days'   => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'custom'         => [
                $from !== '' ? Carbon::parse($from, $tz)->startOfDay() : null,
                $to !== '' ? Carbon::parse($to, $tz)->endOfDay() : null,
            ],
            'all_time'       => [null, null],
            default          => [null, null],
        };
    }

    private function resolveSortColumn(string $sort): string
    {
        return match ($sort) {
            'sender_name'    => 'actor.first_name',
            'receiver_name'  => 'peer.first_name',
            'sender_city'    => 'actor.city',
            'receiver_city'  => 'peer.city',
            'is_approved'    => 'c.is_approved',
            'approved_at'    => 'c.approved_at',
            default          => 'c.created_at',
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
        $full = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $full !== '' ? $full : ($display ?? '—');
    }
}
