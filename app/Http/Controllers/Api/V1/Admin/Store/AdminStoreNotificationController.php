<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\NotificationTemplate;
use App\Models\Store\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreNotificationController extends BaseApiController
{
    public function templates(): JsonResponse
    {
        if (class_exists(NotificationTemplate::class)) {
            $templates = NotificationTemplate::all();

            return $this->success($templates, 'Notification templates retrieved');
        }

        return $this->success([], 'No templates available');
    }

    public function showTemplate(string $id): JsonResponse
    {
        if (class_exists(NotificationTemplate::class)) {
            $template = NotificationTemplate::findOrFail($id);

            return $this->success($template, 'Template retrieved');
        }

        return $this->error('Template not found', 404);
    }

    public function logs(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $logs = NotificationLog::with(['event', 'user'])->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($logs, 'Notification logs retrieved');
    }

    public function resendLog(string $id): JsonResponse
    {
        $log = NotificationLog::findOrFail($id);
        $log->update(['status' => 'RETRYING', 'attempts' => $log->attempts + 1]);

        return $this->success($log, 'Notification queued for resending');
    }
}
