<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogAdminController extends BaseApiController
{
    /**
     * Display a paginated listing of immutable audit records with multi-criteria filtering.
     * Enforces debounced search, date scopes, and module filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $sortBy = in_array($request->query('sort_by'), ['created_at', 'activity_type', 'module', 'status'], true)
            ? (string) $request->query('sort_by')
            : 'created_at';
        $sortDirection = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = ActivityLog::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'actor:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
            ])
            ->search($request->query('search'))
            ->module($request->query('module'))
            ->activityType($request->query('activity_type'))
            ->status($request->query('status'))
            ->user($request->query('user_id'))
            ->actor($request->query('actor_id'))
            ->dateRange($request->query('date_from'), $request->query('date_to'))
            ->orderBy($sortBy, $sortDirection);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Server-side aggregation for high-level audit summary statistics (Rule 28).
     * Does not pull full database tables client-side.
     */
    public function stats(Request $request): JsonResponse
    {
        $todayStart = Carbon::today()->startOfDay();

        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::where('created_at', '>=', $todayStart)->count();
        $failedActions = ActivityLog::whereIn('status', ['failed', 'denied'])->count();
        $activeModulesCount = ActivityLog::distinct('module')->count('module');

        // Module breakdown aggregation
        $moduleBreakdown = ActivityLog::query()
            ->select('module', DB::raw('count(*) as aggregate'))
            ->groupBy('module')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'module')
            ->toArray();

        // Status breakdown aggregation
        $statusBreakdown = ActivityLog::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        // 7-day trend calculation
        $sevenDaysAgo = Carbon::today()->subDays(6)->startOfDay();
        $dailyTrends = ActivityLog::query()
            ->select(DB::raw('DATE(created_at) as log_date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('log_date')
            ->pluck('total', 'log_date')
            ->toArray();

        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateKey = Carbon::today()->subDays($i)->toDateString();
            $trendData[] = [
                'date' => $dateKey,
                'label' => Carbon::parse($dateKey)->format('M d'),
                'count' => $dailyTrends[$dateKey] ?? 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_logs' => $totalLogs,
                'today_logs' => $todayLogs,
                'failed_actions' => $failedActions,
                'active_modules_count' => $activeModulesCount,
                'module_breakdown' => $moduleBreakdown,
                'status_breakdown' => $statusBreakdown,
                'recent_activity_trend' => $trendData,
            ],
        ]);
    }

    /**
     * Detailed inspect view for a single immutable audit log.
     */
    public function show(string $id): JsonResponse
    {
        $log = ActivityLog::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'actor:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
            ])
            ->find($id);

        if (! $log) {
            return $this->error('Audit log record not found.', 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Dynamic module and activity type metadata dictionary for frontend filters.
     */
    public function modules(): JsonResponse
    {
        $modules = ActivityLog::distinct()->pluck('module')->filter()->values()->toArray();
        if (empty($modules)) {
            $modules = [
                'auth',
                'networking',
                'posts',
                'circles',
                'events',
                'communications',
                'coins',
                'billing',
                'admin',
            ];
        }

        $activityTypes = ActivityLog::distinct()->pluck('activity_type')->filter()->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'modules' => $modules,
                'activity_types' => $activityTypes,
            ],
        ]);
    }

    /**
     * Export audit trail records as CSV for regulatory and compliance requirements.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ActivityLog::query()
            ->with([
                'user:id,first_name,last_name,display_name,email',
                'actor:id,first_name,last_name,display_name,email',
            ])
            ->search($request->query('search'))
            ->module($request->query('module'))
            ->activityType($request->query('activity_type'))
            ->status($request->query('status'))
            ->user($request->query('user_id'))
            ->actor($request->query('actor_id'))
            ->dateRange($request->query('date_from'), $request->query('date_to'))
            ->orderBy('created_at', 'desc')
            ->limit(5000);

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-'.Carbon::now()->format('Y-m-d-His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Audit ID',
                'Timestamp (UTC)',
                'Module',
                'Activity Type',
                'Status',
                'Actor Name',
                'Actor Email',
                'Target Peer Name',
                'Target Peer Email',
                'Description',
                'IP Address',
                'User Agent',
            ]);

            $query->chunk(250, function ($records) use ($handle): void {
                foreach ($records as $log) {
                    $actorName = $log->actor ? ($log->actor->display_name ?: trim($log->actor->first_name.' '.$log->actor->last_name)) : 'System';
                    $targetName = $log->user ? ($log->user->display_name ?: trim($log->user->first_name.' '.$log->user->last_name)) : 'N/A';

                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->module,
                        $log->activity_type,
                        $log->status,
                        $actorName,
                        $log->actor?->email ?? 'system@peersunity.com',
                        $targetName,
                        $log->user?->email ?? 'N/A',
                        $log->description,
                        $log->ip_address,
                        $log->user_agent,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
