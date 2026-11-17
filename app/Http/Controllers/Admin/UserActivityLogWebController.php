<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Services\UserActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserActivityLogWebController extends Controller
{
    public function __construct(
        private readonly UserActivityLogService $userActivityLogService,
    ) {}

    /**
     * Display the paginated user activity & security logs with stats and filters.
     */
    public function index(Request $request): View
    {
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'activity_type' => trim((string) $request->query('activity_type', 'all')),
            'module' => trim((string) $request->query('module', 'all')),
            'status' => trim((string) $request->query('status', 'all')),
            'platform' => trim((string) $request->query('platform', 'all')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'per_page' => $perPage,
        ];

        $tableMissing = ! Schema::hasTable('user_activity_logs');
        $logs = $this->userActivityLogService->getAdminLogs($filters, $perPage);
        $stats = $this->userActivityLogService->getAdminStats();
        $options = $this->userActivityLogService->getFilterOptions();

        return view('admin.user-activity-logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'options' => $options,
            'filters' => $filters,
            'tableMissing' => $tableMissing,
        ]);
    }

    /**
     * Show detail for a specific log item (as JSON modal payload or view).
     */
    public function show(string $id): JsonResponse|View
    {
        if (! Schema::hasTable('user_activity_logs')) {
            return response()->json([
                'success' => false,
                'message' => 'Table user_activity_logs does not exist yet.',
            ], 404);
        }

        $log = UserActivityLog::with('user')->findOrFail($id);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        }

        return view('admin.user-activity-logs.show', compact('log'));
    }

    /**
     * Export filtered logs as a downloadable CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'activity_type' => trim((string) $request->query('activity_type', 'all')),
            'module' => trim((string) $request->query('module', 'all')),
            'status' => trim((string) $request->query('status', 'all')),
            'platform' => trim((string) $request->query('platform', 'all')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        return $this->userActivityLogService->exportCsv($filters);
    }
}
