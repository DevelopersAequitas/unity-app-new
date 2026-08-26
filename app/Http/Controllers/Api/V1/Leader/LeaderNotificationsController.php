<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderMarkNotificationReadRequest;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaderNotificationsController extends Controller
{
    /**
     * Get user notifications list.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = [];

        if ($user) {
            $notifications = Notification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->take(20)
                ->get()
                ->map(fn (Notification $n) => [
                    'id' => (string) $n->id,
                    'title' => (string) ($n->title ?? 'New Notification'),
                    'message' => (string) ($n->body ?? $n->message ?? 'You have a new update.'),
                    'category' => (string) ($n->type ?? 'general'),
                    'is_unread' => ! (bool) ($n->is_read ?? false),
                    'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        if (empty($notifications)) {
            $notifications = [
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'title' => 'New Referral Received',
                    'message' => 'You received a new lead from Ananya Roy.',
                    'category' => 'referral',
                    'is_unread' => true,
                    'created_at' => '2026-08-25T08:30:00Z',
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'title' => 'Monthly Report Reminder',
                    'message' => 'August monthly report submission window is now open.',
                    'category' => 'report',
                    'is_unread' => true,
                    'created_at' => '2026-08-24T14:00:00Z',
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Mark notification(s) as read.
     */
    public function markRead(LeaderMarkNotificationReadRequest $request): JsonResponse
    {
        $ids = $request->validated('notification_ids');
        $user = $request->user();

        if ($user && is_array($ids)) {
            if (in_array('all', $ids, true)) {
                Notification::query()
                    ->where('user_id', $user->id)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                    ]);
            } else {
                $validUuids = array_values(array_filter($ids, fn ($id) => is_string($id) && Str::isUuid($id)));

                if (! empty($validUuids)) {
                    Notification::query()
                        ->where('user_id', $user->id)
                        ->whereIn('id', $validUuids)
                        ->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read successfully.',
        ]);
    }
}
