<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request)
    {
        $authUser = $request->user();

        $query = Notification::where('user_id', $authUser->id);

        if (! is_null($request->input('is_read'))) {
            $isRead = filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (! is_null($isRead)) {
                $query->where('is_read', $isRead);
            }
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = [
            'items' => NotificationResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function markAllRead(Request $request)
    {
        $authUser = $request->user();
        $now = now();

        $updatedCount = Notification::where('user_id', $authUser->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
            ]);

        return $this->success([
            'updated_count' => $updatedCount,
        ], 'All notifications marked as read');
    }

    public function markRead(Request $request, string $id)
    {
        $authUser = $request->user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $authUser->id)
            ->first();

        if (! $notification) {
            return $this->error('Notification not found', 404);
        }

        if (! $notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return $this->success(new NotificationResource($notification), 'Notification marked as read');
    }
}
