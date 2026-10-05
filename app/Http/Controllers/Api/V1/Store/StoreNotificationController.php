<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreDeviceRegisterRequest;
use App\Services\Store\StoreNotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreNotificationController extends BaseApiController
{
    protected StoreNotificationService $notificationService;

    public function __construct(StoreNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $notifications = $this->notificationService->getUserNotifications($user, $perPage);

        return $this->success($notifications, 'Notifications retrieved');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $notification = $this->notificationService->getNotification($user, $id);

            return $this->success($notification, 'Notification details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $this->notificationService->markAsRead($user, $id);

            return $this->success(['read' => true], 'Notification marked as read');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function registerDevice(StoreDeviceRegisterRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->notificationService->registerDevice($user, $request->validated());

        return $this->success(['registered' => true], 'Device registered successfully', 201);
    }

    public function removeDevice(Request $request, string $deviceId): JsonResponse
    {
        $user = $request->user();
        $this->notificationService->removeDevice($user, $deviceId);

        return $this->success(['removed' => true], 'Device token removed');
    }
}
