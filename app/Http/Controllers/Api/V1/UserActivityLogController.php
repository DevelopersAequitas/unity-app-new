<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StoreUserActivityLogRequest;
use App\Models\User;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityLogController extends BaseApiController
{
    public function __construct(
        protected UserActivityLogService $userActivityLogService
    ) {}

    /**
     * Store a new user activity log.
     */
    public function store(StoreUserActivityLogRequest $request): JsonResponse
    {
        $user = $request->user() ?: $request->user('sanctum') ?: auth('sanctum')->user();
        $validated = $request->validated();

        if (! $user && ! empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);
        }

        $log = $this->userActivityLogService->log($validated, $user, $request);

        return $this->success(
            $log ? [
                'id' => (string) $log->id,
                'user_id' => $log->user_id,
                'activity_type' => $log->activity_type,
                'action' => $log->action,
                'description' => $log->description,
                'module' => $log->module,
                'status' => $log->status,
                'ip_address' => $log->ip_address,
                'platform' => $log->platform,
                'app_version' => $log->app_version,
                'device_info' => $log->device_info,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toIso8601String(),
            ] : [
                'activity_type' => $validated['activity_type'] ?? 'general',
                'status' => $validated['status'] ?? 'success',
                'note' => 'Log recorded in system audit.',
            ],
            'User activity log recorded successfully.',
            201
        );
    }

    /**
     * Get paginated activity logs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?: $request->user('sanctum') ?: auth('sanctum')->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $filters = [
            'activity_type' => $request->query('activity_type'),
            'module' => $request->query('module'),
            'status' => $request->query('status'),
        ];

        $logs = $this->userActivityLogService->getLogsForUser((string) $user->id, $perPage, $filters);

        return $this->success($logs, 'User activity logs retrieved successfully.');
    }
}
