<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
     * Display the user activity & security logs.
     * Default view: Grouped by User / Peer (click user to view all their activity).
     */
    public function index(Request $request): View|JsonResponse
    {
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $viewMode = (string) $request->query('view', 'users'); // default to 'users'

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'activity_type' => trim((string) $request->query('activity_type', 'all')),
            'module' => trim((string) $request->query('module', 'all')),
            'status' => trim((string) $request->query('status', 'all')),
            'platform' => trim((string) $request->query('platform', 'all')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'per_page' => $perPage,
            'view' => $viewMode,
        ];

        $tableMissing = ! Schema::hasTable('user_activity_logs');
        $stats = $this->userActivityLogService->getAdminStats();
        $options = $this->userActivityLogService->getFilterOptions();

        if ($viewMode === 'logs') {
            $logs = $this->userActivityLogService->getAdminLogs($filters, $perPage);
            $users = null;
        } else {
            $users = $this->userActivityLogService->getUsersWithActivity($filters, $perPage);
            $logs = null;
        }

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'viewMode' => $viewMode,
                'stats' => $stats,
                'options' => $options,
                'filters' => $filters,
                'tableMissing' => $tableMissing,
                'users' => $users ? [
                    'data' => $users->items(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ] : null,
                'logs' => $logs ? [
                    'data' => $logs->items(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ] : null,
            ]);
        }

        return view('admin.user-activity-logs.index', [
            'users' => $users,
            'logs' => $logs,
            'stats' => $stats,
            'options' => $options,
            'filters' => $filters,
            'viewMode' => $viewMode,
            'tableMissing' => $tableMissing,
        ]);
    }

    /**
     * Show all activities for a specific user.
     */
    public function userActivity(Request $request, string $userId): View|JsonResponse
    {
        if (! Schema::hasTable('user_activity_logs')) {
            if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table user_activity_logs does not exist yet.',
                ], 404);
            }
        }

        $user = User::withTrashed()->findOrFail($userId);
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

        $logs = $this->userActivityLogService->getLogsForUserWithFilters($userId, $filters, $perPage);
        $userStats = $this->userActivityLogService->getUserActivityStats($userId);
        $options = $this->userActivityLogService->getFilterOptions();

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->display_name ?: $user->first_name.' '.$user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'company' => $user->company_name,
                    'designation' => $user->designation ?? null,
                    'avatar_url' => $user->profile_photo_url,
                ],
                'stats' => $userStats,
                'logs' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ]);
        }

        return view('admin.user-activity-logs.user', [
            'user' => $user,
            'logs' => $logs,
            'userStats' => $userStats,
            'options' => $options,
            'filters' => $filters,
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

        if (request()->wantsJson() || request()->ajax() || request()->is('api/*')) {
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

    /**
     * Delete a single activity log item.
     */
    public function destroy(Request $request, string $id): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (! Schema::hasTable('user_activity_logs')) {
            if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table user_activity_logs does not exist.',
                ], 404);
            }
            return redirect()->back()->with('error', 'Table user_activity_logs does not exist.');
        }

        $log = UserActivityLog::find($id);
        if ($log) {
            $log->delete();
        }

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Activity log deleted successfully.',
                'deleted_id' => $id,
            ]);
        }

        return redirect()->back()->with('success', 'Activity log deleted successfully.');
    }

    /**
     * Delete all activity logs for a specific user.
     */
    public function destroyUserLogs(Request $request, string $userId): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (! Schema::hasTable('user_activity_logs')) {
            if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table user_activity_logs does not exist.',
                ], 404);
            }
            return redirect()->back()->with('error', 'Table user_activity_logs does not exist.');
        }

        $deletedCount = UserActivityLog::where('user_id', $userId)->delete();

        if ($request->wantsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} activity logs for this user.",
                'deleted_count' => $deletedCount,
                'user_id' => $userId,
            ]);
        }

        return redirect()->back()->with('success', "Successfully deleted {$deletedCount} activity logs for this user.");
    }
}
