<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogWebController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 20);
        $search = trim((string) $request->query('search', ''));
        $module = trim((string) $request->query('module', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = ActivityLog::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name',
                'actor:id,first_name,last_name,display_name,email',
            ])
            ->search($search)
            ->module($module)
            ->status($status)
            ->dateRange($dateFrom, $dateTo)
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($perPage)->withQueryString();

        // High-level statistics
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::where('created_at', '>=', Carbon::today()->startOfDay())->count();
        $failedLogs = ActivityLog::whereIn('status', ['failed', 'denied'])->count();
        $activeModulesCount = ActivityLog::distinct('module')->count('module');

        $availableModules = ActivityLog::distinct()->pluck('module')->filter()->values()->toArray();
        if (empty($availableModules)) {
            $availableModules = ['auth', 'networking', 'posts', 'circles', 'events', 'communications', 'coins', 'billing', 'admin'];
        }

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'todayLogs' => $todayLogs,
            'failedLogs' => $failedLogs,
            'activeModulesCount' => $activeModulesCount,
            'availableModules' => $availableModules,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->query('search', ''));
        $module = trim((string) $request->query('module', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = ActivityLog::query()
            ->with(['user', 'actor'])
            ->search($search)
            ->module($module)
            ->status($status)
            ->dateRange($dateFrom, $dateTo)
            ->orderBy('created_at', 'desc')
            ->limit(5000);

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-logs-'.Carbon::now()->format('Y-m-d-His').'.csv"',
        ];

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Timestamp', 'Module', 'Type', 'Status', 'Actor', 'Target User', 'Description', 'IP Address']);
            $query->chunk(250, function ($records) use ($handle): void {
                foreach ($records as $log) {
                    $actorName = $log->actor ? ($log->actor->display_name ?: $log->actor->first_name) : 'System';
                    $targetName = $log->user ? ($log->user->display_name ?: $log->user->first_name) : 'N/A';
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->module,
                        $log->activity_type,
                        $log->status,
                        $actorName,
                        $targetName,
                        $log->description,
                        $log->ip_address,
                    ]);
                }
            });
            fclose($handle);
        }, 200, $headers);
    }
}
