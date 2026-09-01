<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\IndustryScopeService;
use App\Support\AdminCircleScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivitiesConnectionsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $tableFilters = $this->tableFilters($request);

        $baseQuery = $this->baseQuery($request, $filters, $tableFilters);
        $total = (clone $baseQuery)->count();

        $items = $baseQuery
            ->select([
                'activity.requester_id',
                'activity.addressee_id',
                'activity.is_approved',
                'activity.created_at',
                'activity.approved_at',
                'actor.display_name as actor_display_name',
                'actor.first_name as actor_first_name',
                'actor.last_name as actor_last_name',
                'actor.email as actor_email',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '—') as from_user_name"),
                DB::raw("coalesce(actor.company_name, '') as from_company"),
                DB::raw("coalesce(actor.city, '') as from_city"),
                'peer.display_name as peer_display_name',
                'peer.first_name as peer_first_name',
                'peer.last_name as peer_last_name',
                'peer.email as peer_email',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', peer.first_name, peer.last_name)), ''), peer.display_name, '—') as to_user_name"),
                DB::raw("coalesce(peer.company_name, '') as to_company"),
                DB::raw("coalesce(peer.city, '') as to_city"),
            ])
            ->orderByDesc('activity.created_at')
            ->paginate(20)
            ->withQueryString();

        $topMembers = $this->topMembers($request);

        return view('admin.activities.connections.index', [
            'items' => $items,
            'filters' => $filters,
            'tableFilters' => $tableFilters,
            'topMembers' => $topMembers,
            'total' => $total,
            'circles' => $this->circleOptions(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $tableFilters = $this->tableFilters($request);
        $filename = 'connections_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($request, $filters, $tableFilters) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');

            try {
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'Requester Name',
                    'Requester Email',
                    'Requester Company',
                    'Addressee Name',
                    'Addressee Email',
                    'Addressee Company',
                    'Status',
                    'Requested At',
                    'Approved At',
                ]);

                $this->baseQuery($request, $filters, $tableFilters)
                    ->select([
                        'activity.is_approved',
                        'activity.created_at',
                        'activity.approved_at',
                        'actor.display_name as actor_display_name',
                        'actor.first_name as actor_first_name',
                        'actor.last_name as actor_last_name',
                        'actor.email as actor_email',
                        'actor.company_name as actor_company',
                        'peer.display_name as peer_display_name',
                        'peer.first_name as peer_first_name',
                        'peer.last_name as peer_last_name',
                        'peer.email as peer_email',
                        'peer.company_name as peer_company',
                    ])
                    ->orderBy('activity.created_at')
                    ->chunk(500, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            $actorName = $this->formatUserName(
                                $row->actor_display_name,
                                $row->actor_first_name,
                                $row->actor_last_name
                            );
                            $peerName = $this->formatUserName(
                                $row->peer_display_name,
                                $row->peer_first_name,
                                $row->peer_last_name
                            );

                            fputcsv($handle, [
                                $actorName,
                                $row->actor_email ?? '',
                                $row->actor_company ?? '',
                                $peerName,
                                $row->peer_email ?? '',
                                $row->peer_company ?? '',
                                $row->is_approved ? 'Approved' : 'Pending',
                                $row->created_at ?? '',
                                $row->approved_at ?? '',
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

    private function filters(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        return [
            'q' => trim((string) $request->query('q', $request->query('search', ''))),
            'from' => $from,
            'to' => $to,
            'from_at' => $this->parseDayBoundary($from, false),
            'to_at' => $this->parseDayBoundary($to, true),
            'circle_id' => $request->query('circle_id'),
        ];
    }

    private function tableFilters(Request $request): array
    {
        return [
            'from_peer' => trim((string) $request->query('from_peer', '')),
            'to_peer' => trim((string) $request->query('to_peer', '')),
            'status' => (string) $request->query('status', ''),
        ];
    }

    private function baseQuery(Request $request, array $filters, array $tableFilters)
    {
        $query = DB::table('connections as activity')
            ->join('users as actor', 'actor.id', '=', 'activity.requester_id')
            ->join('users as peer', 'peer.id', '=', 'activity.addressee_id');

        if ($filters['q'] !== '') {
            $query->leftJoin('cities as actor_city', 'actor_city.id', '=', 'actor.city_id');
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $query->where(function ($q) use ($like) {
                $q->where('actor.display_name', 'ILIKE', $like)
                    ->orWhere('actor.first_name', 'ILIKE', $like)
                    ->orWhere('actor.last_name', 'ILIKE', $like)
                    ->orWhere('actor.company_name', 'ILIKE', $like)
                    ->orWhere('actor.city', 'ILIKE', $like)
                    ->orWhere('actor_city.name', 'ILIKE', $like);
            });
        }

        if ($filters['from_at']) {
            $query->where('activity.created_at', '>=', $filters['from_at']);
        }

        if ($filters['to_at']) {
            $query->where('activity.created_at', '<=', $filters['to_at']);
        }

        if (! empty($filters['circle_id'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'actor.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }

        if ($tableFilters['from_peer'] !== '') {
            $like = '%'.$this->escapeLike($tableFilters['from_peer']).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '') ILIKE ?", [$like])
                    ->orWhere('actor.company_name', 'ILIKE', $like)
                    ->orWhere('actor.city', 'ILIKE', $like);
            });
        }

        if ($tableFilters['to_peer'] !== '') {
            $like = '%'.$this->escapeLike($tableFilters['to_peer']).'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("coalesce(nullif(trim(concat_ws(' ', peer.first_name, peer.last_name)), ''), peer.display_name, '') ILIKE ?", [$like])
                    ->orWhere('peer.company_name', 'ILIKE', $like)
                    ->orWhere('peer.city', 'ILIKE', $like);
            });
        }

        if ($tableFilters['status'] === 'approved') {
            $query->where('activity.is_approved', true);
        } elseif ($tableFilters['status'] === 'pending') {
            $query->where('activity.is_approved', false);
        }

        $this->applyScopeToActivityQuery($query, 'activity.requester_id', 'activity.addressee_id');

        return $query;
    }

    private function topMembers(Request $request)
    {
        $filters = $this->filters($request);

        $query = DB::table('connections as activity')
            ->join('users as actor', 'actor.id', '=', 'activity.requester_id');

        if (! empty($filters['circle_id'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('circle_members as cm_filter')
                    ->whereColumn('cm_filter.user_id', 'actor.id')
                    ->where('cm_filter.circle_id', $filters['circle_id']);
            });
        }
        $this->applyScopeToActivityQuery($query, 'activity.requester_id', 'activity.addressee_id');

        return $query
            ->groupBy(
                'activity.requester_id',
                'actor.display_name',
                'actor.first_name',
                'actor.last_name',
                'actor.email',
                'actor.company_name',
                'actor.city'
            )
            ->orderByDesc(DB::raw('count(*)'))
            ->limit(5)
            ->select([
                'activity.requester_id as actor_id',
                'actor.display_name',
                'actor.first_name',
                'actor.last_name',
                'actor.email',
                'actor.company_name',
                'actor.city',
                DB::raw("coalesce(nullif(trim(concat_ws(' ', actor.first_name, actor.last_name)), ''), actor.display_name, '—') as peer_name"),
                DB::raw("coalesce(actor.company_name, '') as peer_company"),
                DB::raw("coalesce(actor.city, '') as peer_city"),
                DB::raw('count(*) as total_count'),
            ])
            ->get();
    }

    private function circleOptions()
    {
        return DB::table('circles')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    private function parseDayBoundary($value, bool $endOfDay): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);

            return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    private function applyScopeToActivityQuery($query, string $primaryColumn, ?string $peerColumn): void
    {
        $admin = auth('admin')->user();

        AdminCircleScope::applyToActivityQuery($query, $admin, $primaryColumn, $peerColumn);
        app(IndustryScopeService::class)->applyToActivityQuery($query, $admin, array_filter([$primaryColumn, $peerColumn]));
    }

    private function formatUserName(?string $displayName, ?string $firstName, ?string $lastName): string
    {
        if ($displayName) {
            return $displayName;
        }

        $name = trim(($firstName ?? '').' '.($lastName ?? ''));

        return $name !== '' ? $name : '—';
    }
}
