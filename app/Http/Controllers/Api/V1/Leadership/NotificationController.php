<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends LeadershipBaseController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * L1. View notification delivery history.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $logs = $this->notificationService->listLogs($request->all(), $perPage);

        return $this->paginate($logs, 'Notification logs fetched successfully.');
    }

    /**
     * L2. View notification details.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = $this->notificationService->getLogDetails($id);

            return $this->success($log, 'Notification details fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * L3. Retry failed notification.
     */
    public function retry(string $id): JsonResponse
    {
        try {
            $log = $this->notificationService->retryNotification($id);

            return $this->success($log, 'Notification retry queued successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
