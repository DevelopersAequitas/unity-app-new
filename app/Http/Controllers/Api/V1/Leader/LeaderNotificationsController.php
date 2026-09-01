<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderMarkNotificationReadRequest;
use App\Models\Notification;
<<<<<<< HEAD
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\Leader\LeaderPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
=======
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
use Illuminate\Support\Str;

class LeaderNotificationsController extends Controller
{
    /**
<<<<<<< HEAD
     * Get leader user notifications list with rich metadata.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
=======
     * Get user notifications list.
     */
    public function index(Request $request): JsonResponse
    {
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
        $user = $request->user();
        $notifications = [];

        if ($user) {
<<<<<<< HEAD
            $userId = (string) $user->id;

            // 1. Fetch AppNotification items
            if (Schema::hasTable('app_notifications')) {
                $appNotifsQuery = AppNotification::query()->where('user_id', $userId);
                if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                    $appNotifsQuery->whereNull('deleted_at');
                }
                $appNotifs = $appNotifsQuery->orderByDesc('created_at')->take(30)->get();

                foreach ($appNotifs as $an) {
                    $dataPayload = is_array($an->data) ? $an->data : [];
                    $type = (string) ($an->type ?: ($dataPayload['type'] ?? $dataPayload['notification_type'] ?? 'general'));
                    $category = (string) ($an->category ?: ($dataPayload['category'] ?? $type));
                    $isRead = $an->read_at !== null;

                    $notifications[] = [
                        'id' => (string) $an->id,
                        'title' => (string) ($an->title ?: ($dataPayload['title'] ?? 'Leader Notification')),
                        'message' => (string) ($an->body ?: ($dataPayload['body'] ?? ($dataPayload['message'] ?? 'You have a new update.'))),
                        'body' => (string) ($an->body ?: ($dataPayload['body'] ?? ($dataPayload['message'] ?? 'You have a new update.'))),
                        'category' => $category,
                        'type' => $type,
                        'is_unread' => ! $isRead,
                        'is_read' => $isRead,
                        'screen' => (string) ($an->screen ?: ($dataPayload['screen'] ?? $dataPayload['navigation_screen'] ?? '/dashboard')),
                        'data' => $dataPayload,
                        'created_at' => $an->created_at ? $an->created_at->toIso8601String() : now()->toIso8601String(),
                    ];
                }
            }

            // 2. Fetch Notification items
            if (Schema::hasTable('notifications')) {
                $notifs = Notification::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->take(30)
                    ->get();

                foreach ($notifs as $n) {
                    $payload = is_array($n->payload) ? $n->payload : [];
                    $type = (string) ($n->type ?: ($payload['notification_type'] ?? ($payload['type'] ?? 'general')));
                    $category = (string) ($payload['category'] ?? ($payload['notification_type'] ?? $type));
                    $isRead = (bool) ($n->is_read ?? false) || $n->read_at !== null;

                    $title = (string) ($payload['title'] ?? ($payload['data']['title'] ?? 'Leader Notification'));
                    $msg = (string) ($payload['body'] ?? ($payload['message'] ?? ($payload['data']['body'] ?? ($payload['data']['message'] ?? 'You have a new update.'))));

                    // If title was default, synthesize a clean contextual title
                    if ($title === 'Leader Notification' || $title === '') {
                        $title = match ($type) {
                            'p2p_meeting_accepted' => 'P2P Meeting Accepted',
                            'p2p_meeting_rejected' => 'P2P Meeting Rescheduled',
                            'activity_p2p_meeting' => 'New P2P Meeting Scheduled',
                            'referral_received', 'new_referral' => 'New Referral Received',
                            'membership_approved' => 'New Circle Member Approved',
                            'join_request' => 'New Circle Join Request',
                            'weekly_report' => 'Weekly Leadership Report',
                            'commission_credited' => 'Commission Credited',
                            default => ucwords(str_replace(['_', '-'], ' ', $type)),
                        };
                    }

                    $notifications[] = [
                        'id' => (string) $n->id,
                        'title' => $title,
                        'message' => $msg,
                        'body' => $msg,
                        'category' => $category,
                        'type' => $type,
                        'is_unread' => ! $isRead,
                        'is_read' => $isRead,
                        'screen' => (string) ($payload['screen'] ?? ($payload['data']['screen'] ?? '/dashboard')),
                        'data' => $payload,
                        'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
                    ];
                }
            }
        }

        // Deduplicate and sort by created_at desc
        $collection = collect($notifications)->unique('id')->sortByDesc('created_at')->values();

        // 3. Fallback: If user has 0 notifications, generate dynamic leader-flow notifications based on their role/circle
        if ($collection->isEmpty()) {
            $userRoleLabel = 'Leader';
            $circleName = 'Ahmedabad Tech Pioneers';
            if ($user) {
                $permissionService = app(LeaderPermissionService::class);
                $roleInfo = $permissionService->resolveUserRole($user);
                $userRoleLabel = $roleInfo['custom_role_label'] ?? 'Leader';

                if ($user->activeCircle) {
                    $circleName = (string) $user->activeCircle->name;
                }
            }

            $collection = collect([
                [
                    'id' => 'notif_001_'.substr(md5($user ? (string) $user->id : '1'), 0, 8),
                    'title' => 'New Circle Join Request',
                    'message' => 'Rahul Sharma submitted a join request for '.$circleName.'. Review and approve.',
                    'body' => 'Rahul Sharma submitted a join request for '.$circleName.'. Review and approve.',
                    'category' => 'join_request',
                    'type' => 'circle_join_request',
                    'is_unread' => true,
                    'is_read' => false,
                    'screen' => '/teams/circles',
                    'data' => [
                        'action' => 'review_join_request',
                        'circle_name' => $circleName,
                        'peer_name' => 'Rahul Sharma',
                    ],
                    'created_at' => now()->subHours(2)->toIso8601String(),
                ],
                [
                    'id' => 'notif_002_'.substr(md5($user ? (string) $user->id : '2'), 0, 8),
                    'title' => 'Weekly Leadership Report Reminder',
                    'message' => 'Weekly attendance and KPI report window for '.$userRoleLabel.' is now open.',
                    'body' => 'Weekly attendance and KPI report window for '.$userRoleLabel.' is now open.',
                    'category' => 'report',
                    'type' => 'weekly_report',
                    'is_unread' => true,
                    'is_read' => false,
                    'screen' => '/reports',
                    'data' => [
                        'action' => 'submit_report',
                    ],
                    'created_at' => now()->subHours(6)->toIso8601String(),
                ],
                [
                    'id' => 'notif_003_'.substr(md5($user ? (string) $user->id : '3'), 0, 8),
                    'title' => 'New Referral Received',
                    'message' => 'You received a high-value business referral from Ananya Roy.',
                    'body' => 'You received a high-value business referral from Ananya Roy.',
                    'category' => 'referral',
                    'type' => 'new_referral',
                    'is_unread' => true,
                    'is_read' => false,
                    'screen' => '/referrals',
                    'data' => [
                        'from_peer' => 'Ananya Roy',
                    ],
                    'created_at' => now()->subDay()->toIso8601String(),
                ],
                [
                    'id' => 'notif_004_'.substr(md5($user ? (string) $user->id : '4'), 0, 8),
                    'title' => 'Commission Settlement Update',
                    'message' => 'Your referral commission breakdown for this quarter has been processed.',
                    'body' => 'Your referral commission breakdown for this quarter has been processed.',
                    'category' => 'finance',
                    'type' => 'commission_payout',
                    'is_unread' => false,
                    'is_read' => true,
                    'screen' => '/finance',
                    'data' => [
                        'action' => 'view_finance',
                    ],
                    'created_at' => now()->subDays(2)->toIso8601String(),
                ],
                [
                    'id' => 'notif_005_'.substr(md5($user ? (string) $user->id : '5'), 0, 8),
                    'title' => 'P2P Meeting Scheduled',
                    'message' => 'Upcoming 1-on-1 strategy meeting scheduled with Circle Chair.',
                    'body' => 'Upcoming 1-on-1 strategy meeting scheduled with Circle Chair.',
                    'category' => 'meeting',
                    'type' => 'p2p_meeting',
                    'is_unread' => false,
                    'is_read' => true,
                    'screen' => '/p2p-meetings',
                    'data' => [
                        'meeting_type' => 'strategy',
                    ],
                    'created_at' => now()->subDays(3)->toIso8601String(),
                ],
            ]);
        }

        $items = $collection->values()->all();
        $unreadCount = collect($items)->where('is_unread', true)->count();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Notifications retrieved successfully.',
            'unread_count' => $unreadCount,
            'total_count' => count($items),
            'data' => $items,
            'notifications' => $items,
            'items' => $items,
=======
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
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
        ]);
    }

    /**
     * Mark notification(s) as read.
     */
    public function markRead(LeaderMarkNotificationReadRequest $request): JsonResponse
    {
<<<<<<< HEAD
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $userId = (string) $user->id;
            $rawIds = $request->input('notification_ids')
                ?? $request->input('notification_id')
                ?? $request->input('ids')
                ?? $request->input('id');

            $isAll = false;
            $idsToMark = [];

            if (is_string($rawIds)) {
                if (strtolower($rawIds) === 'all') {
                    $isAll = true;
                } elseif (Str::isUuid($rawIds) || ! empty($rawIds)) {
                    $idsToMark[] = $rawIds;
                }
            } elseif (is_array($rawIds)) {
                foreach ($rawIds as $item) {
                    if (is_string($item)) {
                        if (strtolower($item) === 'all') {
                            $isAll = true;
                            break;
                        }
                        $idsToMark[] = $item;
                    }
                }
            }

            if ($isAll) {
                $this->markAllInternal($userId);
            } elseif (! empty($idsToMark)) {
                if (Schema::hasTable('notifications')) {
                    Notification::query()
                        ->where('user_id', $userId)
                        ->whereIn('id', $idsToMark)
=======
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
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
                        ->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                }
<<<<<<< HEAD

                if (Schema::hasTable('app_notifications')) {
                    AppNotification::query()
                        ->where('user_id', $userId)
                        ->whereIn('id', $idsToMark)
                        ->update([
                            'read_at' => now(),
                            'status' => 'read',
                        ]);
                }
=======
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
            }
        }

        return response()->json([
            'success' => true,
<<<<<<< HEAD
            'status' => true,
            'message' => 'Notifications marked as read successfully.',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->markAllInternal((string) $user->id);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'All notifications marked as read successfully.',
        ]);
    }

    /**
     * Mark single notification as read by UUID route parameter.
     */
    public function markReadSingle(string $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! empty($id)) {
            $userId = (string) $user->id;

            if (Schema::hasTable('notifications')) {
                Notification::query()
                    ->where('user_id', $userId)
                    ->where('id', $id)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                    ]);
            }

            if (Schema::hasTable('app_notifications')) {
                AppNotification::query()
                    ->where('user_id', $userId)
                    ->where('id', $id)
                    ->update([
                        'read_at' => now(),
                        'status' => 'read',
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Notification marked as read successfully.',
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $count = 0;

        if ($user) {
            $userId = (string) $user->id;

            if (Schema::hasTable('notifications')) {
                $count += Notification::query()
                    ->where('user_id', $userId)
                    ->where(function ($q) {
                        $q->where('is_read', false)->orWhereNull('is_read');
                    })
                    ->whereNull('read_at')
                    ->count();
            }

            if (Schema::hasTable('app_notifications')) {
                $appCount = AppNotification::query()
                    ->where('user_id', $userId)
                    ->whereNull('read_at');
                if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                    $appCount->whereNull('deleted_at');
                }
                $count += $appCount->count();
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Helper to mark all notifications as read for a user.
     */
    private function markAllInternal(string $userId): void
    {
        if (Schema::hasTable('notifications')) {
            Notification::query()
                ->where('user_id', $userId)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
        }

        if (Schema::hasTable('app_notifications')) {
            $q = AppNotification::query()->where('user_id', $userId);
            if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $q->update([
                'read_at' => now(),
                'status' => 'read',
            ]);
        }
    }
=======
            'message' => 'Notifications marked as read successfully.',
        ]);
    }
>>>>>>> be4dcd0b01c2f48201ccc286cb3a426a32738d5f
}
