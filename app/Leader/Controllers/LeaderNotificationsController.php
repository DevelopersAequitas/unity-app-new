<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderMarkNotificationReadRequest;
use App\Leader\Services\LeaderNotificationDispatcherService;
use App\Leader\Services\LeaderNotificationPolicyService;
use App\Leader\Services\LeaderPermissionService;
use App\Models\Circle;
use App\Models\Circular;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderNotificationsController extends Controller
{
    /**
     * Non-leader notification types originating from the Peers Global consumer member app.
     */
    public const CONSUMER_EXCLUDED_TYPES = [
        'new_post',
        'post_created',
        'post_like',
        'post_liked',
        'post_comment',
        'post_commented',
        'comment_like',
        'comment_liked',
        'post_tagged',
        'post_shared',
        'feed_post',
        'peer_post',
        'social_post',
        'activity_update',
        'feed_update',
        'event',
        'new_event',
        'event_created',
        'upcoming_event_reminder',
        'event_starting_now',
        'event_reminder',
        'live_meeting',
        'speed_networking',
        'connection_request',
        'connection_accepted',
        'chat_message',
        'chat',
        'direct_message',
        'badge_earned',
        'profile_view',
        'profile_viewed',
        'daily_reminder',
        'daily_engagement_reminder',
        'engagement_reminder',
        'engagement',
        'engagement_founder',
        'streak_reminder',
        'streak',
    ];

    /**
     * Consumer member screens that belong exclusively to the Peers Global consumer app.
     */
    public const CONSUMER_EXCLUDED_SCREENS = [
        'event_detail',
        'event_details',
        'live_meeting',
        'post_detail',
        '/post-detail',
        'feed',
        '/feed',
        'chat_conversation',
        'connections',
        'member_feed',
    ];

    /**
     * Get leader user notifications list with rich metadata and strict role-wise scoping.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $notifications = [];

        $requestedCategory = strtolower((string) $request->query('category', 'all'));

        $userRoleLabel = 'Leader';
        $canonicalRole = 'circleDirector';
        $isSuperAdmin = false;
        $canAccessFinance = false;

        if ($user) {
            $permissionService = app(LeaderPermissionService::class);
            $roleInfo = $permissionService->resolveUserRole($user);
            $canonicalRole = (string) ($roleInfo['role'] ?? 'circleDirector');
            $userRoleLabel = $roleInfo['custom_role_label'] ?? $permissionService->getRoleLabel($canonicalRole);
            $isSuperAdmin = $canonicalRole === 'superAdmin';
            $canAccessFinance = $isSuperAdmin || $permissionService->hasCapability($user, 'access_finance');
        }

        $policyService = app(LeaderNotificationPolicyService::class);

        if ($user) {
            $excludedTypes = array_unique(array_merge(
                self::CONSUMER_EXCLUDED_TYPES,
                LeaderNotificationPolicyService::CONSUMER_EXCLUDED_TYPES
            ));

            // Enforce financial confidentiality: roles without access_finance never see financial alerts
            if (! $canAccessFinance) {
                $excludedTypes = array_unique(array_merge(
                    $excludedTypes,
                    LeaderNotificationPolicyService::FINANCIAL_SENSITIVE_TYPES
                ));
            }

            $excludedScreens = array_unique(array_merge(
                self::CONSUMER_EXCLUDED_SCREENS,
                LeaderNotificationPolicyService::CONSUMER_EXCLUDED_SCREENS
            ));

            $userId = (string) $user->id;

            // 1. Fetch AppNotification items
            if (Schema::hasTable('app_notifications')) {
                $appNotifsQuery = AppNotification::query()->where('user_id', $userId);
                if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                    $appNotifsQuery->whereNull('deleted_at');
                }
                $appNotifsQuery->whereNotIn('type', $excludedTypes)
                    ->where(function ($q) use ($excludedScreens): void {
                        $q->whereNull('screen')
                            ->orWhereNotIn('screen', $excludedScreens);
                    });

                $appNotifs = $appNotifsQuery->orderByDesc('created_at')->take(500)->get();

                foreach ($appNotifs as $an) {
                    $dataPayload = is_array($an->data) ? $an->data : [];
                    $type = (string) ($an->type ?: ($dataPayload['type'] ?? $dataPayload['notification_type'] ?? 'general'));
                    $category = (string) ($an->category ?: ($dataPayload['category'] ?? $type));
                    $screen = (string) ($an->screen ?: ($dataPayload['screen'] ?? $dataPayload['navigation_screen'] ?? '/dashboard'));
                    $isRead = $an->read_at !== null;

                    if (! $policyService->isLeadershipAudiencePayload($dataPayload, $type, $category, $screen)) {
                        continue;
                    }
                    if (! $canAccessFinance && $policyService->isFinancialSensitive($type, $category, $dataPayload)) {
                        continue;
                    }

                    $notifications[] = [
                        'id' => (string) $an->id,
                        'title' => (string) ($an->title ?: ($dataPayload['title'] ?? 'Leader Notification')),
                        'message' => (string) ($an->body ?: ($dataPayload['body'] ?? ($dataPayload['message'] ?? 'You have a new update.'))),
                        'body' => (string) ($an->body ?: ($dataPayload['body'] ?? ($dataPayload['message'] ?? 'You have a new update.'))),
                        'category' => $category,
                        'type' => $type,
                        'priority' => (string) ($an->priority ?? ($dataPayload['priority'] ?? 'medium')),
                        'delivery_tier' => (string) ($dataPayload['delivery_tier'] ?? 'important'),
                        'requires_action' => (bool) ($dataPayload['requires_action'] ?? false),
                        'audience' => (string) ($dataPayload['audience'] ?? 'leadership'),
                        'is_unread' => ! $isRead,
                        'is_read' => $isRead,
                        'read_at' => $isRead ? ($an->read_at ? $an->read_at->toIso8601String() : now()->toIso8601String()) : null,
                        'status' => $isRead ? 'read' : 'unread',
                        'screen' => $screen,
                        'data' => $dataPayload,
                        'created_at' => $an->created_at ? $an->created_at->toIso8601String() : now()->toIso8601String(),
                    ];
                }
            }

            // 2. Fetch Notification items (Postgres enum-safe: filter in PHP to prevent 22P02 enum mismatch)
            if (Schema::hasTable('notifications')) {
                $notifs = Notification::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('created_at')
                    ->take(500)
                    ->get();

                foreach ($notifs as $n) {
                    $payload = is_array($n->payload) ? $n->payload : [];
                    $type = (string) ($payload['notification_type'] ?? ($payload['type'] ?? ($n->source_event ?? $n->type ?? 'general')));
                    $category = (string) ($payload['category'] ?? ($payload['notification_type'] ?? $type));
                    $screen = (string) ($payload['screen'] ?? ($payload['data']['screen'] ?? ($payload['navigation_screen'] ?? '/dashboard')));
                    $isRead = (bool) ($n->is_read ?? false) || $n->read_at !== null;

                    if (! $policyService->isLeadershipAudiencePayload($payload, $type, $category, $screen)) {
                        continue;
                    }
                    if (! $canAccessFinance && $policyService->isFinancialSensitive($type, $category, $payload)) {
                        continue;
                    }

                    $title = (string) ($n->title ?: ($payload['title'] ?? ($payload['data']['title'] ?? 'Leader Notification')));
                    $msg = (string) ($n->message ?: ($payload['body'] ?? ($payload['message'] ?? ($payload['data']['body'] ?? ($payload['data']['message'] ?? 'You have a new update.')))));

                    $notifications[] = [
                        'id' => (string) $n->id,
                        'title' => $title,
                        'message' => $msg,
                        'body' => $msg,
                        'category' => $category,
                        'type' => $type,
                        'priority' => (string) ($payload['priority'] ?? 'medium'),
                        'delivery_tier' => (string) ($payload['delivery_tier'] ?? 'important'),
                        'requires_action' => (bool) ($payload['requires_action'] ?? false),
                        'audience' => (string) ($payload['audience'] ?? 'leadership'),
                        'is_unread' => ! $isRead,
                        'is_read' => $isRead,
                        'read_at' => $isRead ? ($n->read_at ? $n->read_at->toIso8601String() : now()->toIso8601String()) : null,
                        'status' => $isRead ? 'read' : 'unread',
                        'screen' => $screen,
                        'data' => $payload,
                        'created_at' => $n->created_at ? $n->created_at->toIso8601String() : now()->toIso8601String(),
                    ];
                }
            }
        }

        // Deduplicate and sort by created_at desc (no synthetic/fabricated demo fallback items)
        $collection = collect($notifications)->unique('id')->sortByDesc('created_at')->values();

        // Apply optional category / unread / action_required / priority filters
        if ($requestedCategory !== 'all' && ! empty($requestedCategory)) {
            $collection = $collection->filter(function ($item) use ($requestedCategory): bool {
                if ($requestedCategory === 'unread') {
                    return (bool) ($item['is_unread'] ?? false);
                }
                if ($requestedCategory === 'action_required') {
                    return (bool) ($item['requires_action'] ?? false);
                }
                if ($requestedCategory === 'critical') {
                    return strtolower((string) ($item['delivery_tier'] ?? '')) === 'critical'
                        || in_array(strtolower((string) ($item['priority'] ?? '')), ['urgent', 'critical'], true);
                }

                $c = strtolower((string) ($item['category'] ?? ''));
                $t = strtolower((string) ($item['type'] ?? ''));

                return $c === $requestedCategory || str_contains($c, $requestedCategory) || str_contains($t, $requestedCategory);
            })->values();
        }

        if ($request->boolean('unread_only')) {
            $collection = $collection->where('is_unread', true)->values();
        }

        if ($request->boolean('action_required')) {
            $collection = $collection->where('requires_action', true)->values();
        }

        $requestedPriority = strtolower(trim((string) $request->query('priority', '')));
        if ($requestedPriority !== '') {
            $collection = $collection->filter(fn ($item): bool => strtolower((string) ($item['priority'] ?? '')) === $requestedPriority)->values();
        }

        $total = $collection->count();
        $unreadCount = $collection->where('is_unread', true)->count();

        // Pagination parameters
        $page = max(1, (int) $request->query('page', 1));
        $perPageRaw = $request->query('per_page');
        $perPage = ($perPageRaw === 'all') ? max(1, $total) : max(1, min(100, (int) ($perPageRaw ?: 20)));

        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);

        $offset = ($currentPage - 1) * $perPage;
        $paginatedItems = $collection->slice($offset, $perPage)->values()->all();

        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);
        $hasMore = $currentPage < $lastPage;

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Notifications retrieved successfully.',
            'unread_count' => $unreadCount,
            'total_count' => $total,
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
            ],
            'data' => $paginatedItems,
            'notifications' => $paginatedItems,
            'items' => $paginatedItems,
        ]);
    }

    /**
     * Mark notification(s) as read.
     */
    public function markRead(LeaderMarkNotificationReadRequest $request): JsonResponse
    {
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
                        ->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                }

                if (Schema::hasTable('app_notifications')) {
                    AppNotification::query()
                        ->where('user_id', $userId)
                        ->whereIn('id', $idsToMark)
                        ->update([
                            'read_at' => now(),
                            'status' => 'read',
                        ]);
                }
            }
        }

        return response()->json([
            'success' => true,
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
            $policyService = app(LeaderNotificationPolicyService::class);
            $canAccessFinance = $policyService->canUserReceiveFinancialNotification($user);

            if (Schema::hasTable('notifications')) {
                $rawNotifs = Notification::query()
                    ->where('user_id', $userId)
                    ->where(function ($q): void {
                        $q->where('is_read', false)->orWhereNull('is_read');
                    })
                    ->whereNull('read_at')
                    ->take(200)
                    ->get();

                foreach ($rawNotifs as $rn) {
                    $p = is_array($rn->payload) ? $rn->payload : [];
                    $t = (string) ($p['notification_type'] ?? ($p['type'] ?? ($rn->source_event ?? $rn->type ?? 'general')));
                    $c = (string) ($p['category'] ?? ($p['notification_type'] ?? $t));
                    $s = (string) ($p['screen'] ?? ($p['data']['screen'] ?? ($p['navigation_screen'] ?? '/dashboard')));
                    if ($policyService->isLeadershipAudiencePayload($p, $t, $c, $s)
                        && ($canAccessFinance || ! $policyService->isFinancialSensitive($t, $c, $p))) {
                        $count++;
                    }
                }
            }

            if (Schema::hasTable('app_notifications')) {
                $appQuery = AppNotification::query()
                    ->where('user_id', $userId)
                    ->whereNull('read_at');

                if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                    $appQuery->whereNull('deleted_at');
                }

                foreach ($appQuery->take(200)->get() as $an) {
                    $p = is_array($an->data) ? $an->data : [];
                    $t = (string) ($an->type ?: ($p['type'] ?? $p['notification_type'] ?? 'general'));
                    $c = (string) ($an->category ?: ($p['category'] ?? $t));
                    $s = (string) ($an->screen ?: ($p['screen'] ?? $p['navigation_screen'] ?? '/dashboard'));
                    if ($policyService->isLeadershipAudiencePayload($p, $t, $c, $s)
                        && ($canAccessFinance || ! $policyService->isFinancialSensitive($t, $c, $p))) {
                        $count++;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'unread_count' => $count,
            'data' => [
                'unread_count' => $count,
            ],
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

    /**
     * Get automated notification flow configuration role-wise for Super Admin.
     */
    public function getRoleConfig(Request $request): JsonResponse
    {
        $policy = app(LeaderNotificationPolicyService::class);
        $defaultConfig = $policy->getDefaultRoleConfig();

        // Fetch custom saved overrides if table exists (backward-compatible with legacy 6-key saves)
        if (Schema::hasTable('app_configs')) {
            $saved = DB::table('app_configs')
                ->where('key', 'leader_notification_flow_config')
                ->value('value');
            if ($saved) {
                $decoded = json_decode((string) $saved, true);
                if (is_array($decoded)) {
                    $defaultConfig = array_replace_recursive($defaultConfig, $decoded);
                    foreach ($defaultConfig as $rKey => &$roleBlock) {
                        $overrides = is_array($decoded[$rKey]['events_override'] ?? null) ? $decoded[$rKey]['events_override'] : [];
                        if (! empty($overrides) && is_array($roleBlock['events'] ?? null)) {
                            foreach ($roleBlock['events'] as &$ev) {
                                $evKey = (string) ($ev['key'] ?? '');
                                if ($evKey !== '' && array_key_exists($evKey, $overrides) && empty($ev['critical'])) {
                                    $ev['enabled'] = (bool) $overrides[$evKey];
                                }
                            }
                        }
                    }
                    unset($roleBlock, $ev);
                }
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $defaultConfig,
            'roles' => array_keys($defaultConfig),
            'timezone' => config('app.timezone', 'Asia/Kolkata'),
        ]);
    }

    /**
     * Update automated notification flow configuration for a role or all roles.
     * Critical security events cannot be disabled via configuration.
     */
    public function updateRoleConfig(Request $request): JsonResponse
    {
        $roleKey = (string) $request->input('role_key');
        $eventKey = (string) $request->input('event_key');
        $isEnabled = (bool) $request->input('is_enabled', true);
        $fullConfig = $request->input('config');

        if (! $isEnabled && $eventKey !== '' && in_array($eventKey, LeaderNotificationPolicyService::CRITICAL_NON_DISABLEABLE_TYPES, true)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Critical security notifications cannot be disabled.',
            ], 422);
        }

        if (Schema::hasTable('app_configs')) {
            $existing = DB::table('app_configs')
                ->where('key', 'leader_notification_flow_config')
                ->value('value');
            $currentData = $existing ? (json_decode((string) $existing, true) ?: []) : [];

            if (is_array($fullConfig) && ! empty($fullConfig)) {
                $currentData = $fullConfig;
            } elseif (! empty($roleKey) && ! empty($eventKey)) {
                $currentData[$roleKey]['events_override'][$eventKey] = $isEnabled;
            }

            DB::table('app_configs')->updateOrInsert(
                ['key' => 'leader_notification_flow_config'],
                [
                    'value' => json_encode($currentData),
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Notification flow configuration updated successfully.',
        ]);
    }

    /**
     * Get the authenticated leader's personal notification preferences (quiet hours, digest, categories).
     */
    public function getPreferences(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $defaults = [
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
            'timezone' => config('app.timezone', 'Asia/Kolkata'),
            'digest_mode' => 'immediate',
            'disabled_categories' => [],
        ];

        if ($user && Schema::hasTable('app_configs')) {
            $raw = DB::table('app_configs')
                ->where('key', 'leader_notification_user_prefs_'.(string) $user->id)
                ->value('value');
            if ($raw) {
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded)) {
                    $defaults = array_merge($defaults, $decoded);
                }
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $defaults,
        ]);
    }

    /**
     * Update the authenticated leader's notification preferences (cannot disable critical/security).
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $disabledCategories = array_values(array_diff(
            array_map('strtolower', (array) $request->input('disabled_categories', [])),
            ['security', 'critical']
        ));

        $prefs = [
            'quiet_hours_enabled' => (bool) $request->input('quiet_hours_enabled', false),
            'quiet_hours_start' => (string) $request->input('quiet_hours_start', '22:00'),
            'quiet_hours_end' => (string) $request->input('quiet_hours_end', '07:00'),
            'timezone' => (string) $request->input('timezone', config('app.timezone', 'Asia/Kolkata')),
            'digest_mode' => in_array((string) $request->input('digest_mode', 'immediate'), ['immediate', 'daily_digest'], true)
                ? (string) $request->input('digest_mode', 'immediate')
                : 'immediate',
            'disabled_categories' => $disabledCategories,
        ];

        if (Schema::hasTable('app_configs')) {
            DB::table('app_configs')->updateOrInsert(
                ['key' => 'leader_notification_user_prefs_'.(string) $user->id],
                [
                    'value' => json_encode($prefs),
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $prefs,
            'message' => 'Notification preferences updated.',
        ]);
    }

    /**
     * List active eligible leaders for single-leader immediate push selector.
     */
    public function listEligibleLeaders(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $roleFilter = trim((string) $request->input('role', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));

        $permissionService = app(LeaderPermissionService::class);
        $leaders = [];

        if (Schema::hasTable('users')) {
            $cols = Schema::getColumnListing('users');
            $q = User::query()->whereNull('deleted_at');

            if ($search !== '') {
                $q->where(function ($sub) use ($search, $cols): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                    if (in_array('email', $cols, true)) {
                        $sub->orWhere('email', 'like', "%{$search}%");
                    }
                    if (in_array('phone', $cols, true)) {
                        $sub->orWhere('phone', 'like', "%{$search}%");
                    }
                });
            }

            $tokenUsers = [];
            if (Schema::hasTable('user_push_tokens')) {
                $tokenQuery = DB::table('user_push_tokens');
                if (Schema::hasColumn('user_push_tokens', 'app')) {
                    $tokenQuery->where('app', 'leader');
                }
                if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                    $tokenQuery->where('is_active', true);
                }
                $userCol = UserPushToken::getUserIdColumn();
                foreach ($tokenQuery->pluck($userCol)->all() as $uid) {
                    $tokenUsers[(string) $uid] = true;
                }
            }

            $q->orderBy('id')->chunkById(500, function ($users) use ($permissionService, $roleFilter, $tokenUsers, &$leaders): void {
                foreach ($users as $u) {
                    if (! $permissionService->isLeader($u)) {
                        continue;
                    }
                    $roleInfo = $permissionService->resolveUserRole($u);
                    $canon = (string) ($roleInfo['role'] ?? 'leader');
                    $roleLabel = $roleInfo['custom_role_label'] ?? $permissionService->getRoleLabel($canon);

                    if ($roleFilter !== '' && $roleFilter !== 'all' && strtolower($canon) !== strtolower($roleFilter)) {
                        continue;
                    }

                    $circleName = $u->activeCircle?->name ?? 'National Scope';
                    $hasToken = isset($tokenUsers[(string) $u->id])
                        || ! empty($u->leader_android_fcm_token)
                        || ! empty($u->leader_ios_fcm_token);

                    $leaders[] = [
                        'id' => (string) $u->id,
                        'name' => (string) ($u->name ?? 'Leader'),
                        'email' => (string) ($u->email ?? ''),
                        'phone' => (string) ($u->phone ?? ''),
                        'role_key' => $canon,
                        'role_label' => $roleLabel,
                        'regional_scope' => (string) ($roleInfo['regional_scope'] ?? 'Circle Scope'),
                        'circle_id' => $u->active_circle_id ? (string) $u->active_circle_id : null,
                        'circle_name' => $circleName,
                        'has_leader_push_token' => $hasToken,
                        'avatar_url' => $u->avatar_url ?? null,
                    ];
                }
            });
        }

        $total = count($leaders);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($leaders, $offset, $perPage);

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $paged,
            'count' => $total,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Immediate push notification dispatcher (Global, Role-wise, or Specific Leader ID).
     */
    public function sendImmediateNotification(Request $request): JsonResponse
    {
        $targetType = (string) $request->input('target_type', 'global'); // 'global' | 'role' | 'leader'
        $targetRole = (string) $request->input('target_role', '');
        $leaderId = trim((string) ($request->input('leader_id') ?? $request->input('leader_email') ?? ''));
        $title = trim((string) $request->input('title', 'Leader Alert'));
        $message = trim((string) $request->input('message', ''));
        $category = (string) $request->input('category', 'system');
        $screen = (string) $request->input('screen', '/dashboard');
        $priority = (string) $request->input('priority', 'high');
        $timezone = (string) $request->input('timezone', config('app.timezone', 'Asia/Kolkata'));

        if (empty($title) || empty($message)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Notification title and message are required.',
            ], 422);
        }

        $permissionService = app(LeaderPermissionService::class);
        $dispatcher = app(LeaderNotificationDispatcherService::class);
        $dispatchedCount = 0;
        $targetUserIds = [];

        if (($targetType === 'leader' || $targetType === 'specific') && ! empty($leaderId)) {
            if (str_contains($leaderId, '@')) {
                $foundUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower($leaderId)])->first();
                if (! $foundUser && Schema::hasTable('admin_users')) {
                    $foundUser = AdminUser::query()->whereRaw('LOWER(email) = ?', [strtolower($leaderId)])->first();
                }
                if ($foundUser) {
                    $targetUserIds[] = (string) $foundUser->id;
                }
            } else {
                $targetUserIds[] = $leaderId;
            }
        } elseif ($targetType === 'role' && ! empty($targetRole)) {
            if (Schema::hasTable('users')) {
                $allUsers = User::query()->take(500)->get();
                foreach ($allUsers as $u) {
                    $roleInfo = $permissionService->resolveUserRole($u);
                    $canon = strtolower((string) ($roleInfo['role'] ?? ''));
                    if ($canon === strtolower($targetRole)) {
                        $targetUserIds[] = (string) $u->id;
                    }
                }
            }
        } else {
            // Global: All active leaders across all leadership roles
            if (Schema::hasTable('users')) {
                $allUsers = User::query()->take(500)->get();
                foreach ($allUsers as $u) {
                    if ($permissionService->isLeader($u)) {
                        $targetUserIds[] = (string) $u->id;
                    }
                }
            }
        }

        $targetUserIds = array_unique(array_filter($targetUserIds));

        foreach ($targetUserIds as $uid) {
            $dispatcher->dispatchToUser(
                $uid,
                'admin_broadcast',
                $title,
                $message,
                $screen,
                [
                    'target_type' => $targetType,
                    'target_role' => $targetRole,
                    'priority' => $priority,
                    'timezone' => $timezone,
                    'sent_by_admin' => true,
                    'sent_at' => now()->toIso8601String(),
                ],
                $category,
                $priority
            );
            $dispatchedCount++;
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Successfully dispatched notification to {$dispatchedCount} leader(s).",
            'dispatched_count' => $dispatchedCount,
            'target_type' => $targetType,
            'timezone' => $timezone,
        ]);
    }

    /**
     * Get security audit logs for all sent leader notifications with timezone awareness.
     */
    public function getSecurityLogs(Request $request): JsonResponse
    {
        $timezone = (string) $request->input('timezone', 'Asia/Kolkata');
        $search = trim((string) $request->input('search', ''));
        $roleFilter = trim((string) $request->input('role', ''));
        $categoryFilter = trim((string) $request->input('category', ''));

        $logs = [];

        if (Schema::hasTable('app_notifications')) {
            $q = AppNotification::query();
            if (Schema::hasColumn('app_notifications', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }

            if (! empty($search)) {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhere('user_id', 'like', "%{$search}%");
                });
            }

            if (! empty($categoryFilter) && $categoryFilter !== 'all') {
                $q->where('category', $categoryFilter);
            }

            $results = $q->orderByDesc('created_at')->take(200)->get();

            // Cache user lookup across both users and admin_users tables
            $userIds = $results->pluck('user_id')->filter()->unique()->all();
            $usersMap = [];
            if (! empty($userIds) && Schema::hasTable('users')) {
                $cols = Schema::getColumnListing('users');
                $selectCols = ['id'];
                if (in_array('name', $cols, true)) {
                    $selectCols[] = 'name';
                }
                if (in_array('email', $cols, true)) {
                    $selectCols[] = 'email';
                }
                if (in_array('phone', $cols, true)) {
                    $selectCols[] = 'phone';
                }
                $users = User::query()->whereIn('id', $userIds)->get($selectCols);
                foreach ($users as $u) {
                    $usersMap[(string) $u->id] = $u;
                }
            }

            // Also check admin_users for any missing IDs
            $missingIds = array_diff($userIds, array_keys($usersMap));
            if (! empty($missingIds) && Schema::hasTable('admin_users')) {
                $admins = AdminUser::query()->whereIn('id', $missingIds)->get();
                foreach ($admins as $au) {
                    $usersMap[(string) $au->id] = $au;
                }
            }

            foreach ($results as $item) {
                $payload = is_array($item->data) ? $item->data : [];
                $created = $item->created_at ?: now();

                // Format in target timezone
                try {
                    $localTime = $created->copy()->setTimezone($timezone);
                    $formattedTime = $localTime->format('d M Y, h:i A').' ('.$timezone.')';
                } catch (\Throwable) {
                    $formattedTime = $created->format('d M Y, h:i A');
                }

                $userObj = $usersMap[(string) $item->user_id] ?? null;
                $recipientName = $userObj ? (string) ($userObj->name ?: $userObj->email) : ('Leader ID: '.substr((string) $item->user_id, 0, 8));
                $permissionService = app(LeaderPermissionService::class);
                $roleLabel = 'Circle Leader';
                $roleKey = 'leader';
                $isAdminBroadcast = ($payload['sent_by_admin'] ?? false) || ($item->type === 'admin_broadcast');

                if ($userObj) {
                    $resolvedRole = $permissionService->resolveUserRole($userObj);
                    $roleKey = strtolower((string) ($resolvedRole['role'] ?? ''));
                    $roleLabel = $resolvedRole['custom_role_label'] ?? $permissionService->getRoleLabel($roleKey);

                    // Strictly filter: For automated events, skip non-leader roles
                    if (! $isAdminBroadcast && (! ($resolvedRole['is_leader'] ?? false) || in_array($roleKey, ['peermember', 'peer_member', 'peer', 'member'], true))) {
                        continue;
                    }
                } elseif (! $isAdminBroadcast) {
                    continue;
                }

                if (! empty($roleFilter) && $roleFilter !== 'all' && $roleKey !== strtolower($roleFilter)) {
                    continue;
                }

                $triggerType = ($payload['sent_by_admin'] ?? false)
                    ? 'Super Admin Broadcast'
                    : (str_contains($item->type ?? '', 'milestone') ? 'Milestone Automation' : 'Circle Event Escalation');

                $logs[] = [
                    'id' => (string) $item->id,
                    'title' => (string) $item->title,
                    'message' => (string) ($item->body ?: $item->message),
                    'category' => (string) ($item->category ?: 'system'),
                    'type' => (string) ($item->type ?: 'general'),
                    'screen' => (string) ($item->screen ?: '/dashboard'),
                    'recipient_id' => (string) $item->user_id,
                    'recipient_name' => $recipientName,
                    'recipient_role' => $roleLabel,
                    'recipient_circle' => $payload['circle_name'] ?? 'Ahmedabad Pioneers',
                    'trigger_type' => $triggerType,
                    'channel' => 'In-App Push',
                    'priority' => (string) ($item->priority ?: 'medium'),
                    'status' => $item->read_at ? 'Read' : ($item->status ?: 'Delivered'),
                    'is_read' => $item->read_at !== null,
                    'timezone' => $timezone,
                    'sent_at' => $created->toIso8601String(),
                    'sent_at_formatted' => $formattedTime,
                    'time_ago' => $created->diffForHumans(),
                ];
            }
        }

        // If logs table had 0 entries, provide mock historical security logs so Super Admin can immediately see the layout
        // Fallback: If app_notifications yielded 0 logs, check universal notifications table
        if (empty($logs) && Schema::hasTable('notifications')) {
            $nq = Notification::query();

            if (! empty($search)) {
                $nq->where(function ($sub) use ($search): void {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('user_id', 'like', "%{$search}%");
                });
            }

            $nResults = $nq->orderByDesc('created_at')->take(100)->get();
            $nUserIds = $nResults->pluck('user_id')->unique()->all();
            $nUsersMap = [];

            if (! empty($nUserIds) && Schema::hasTable('users')) {
                $cols = Schema::getColumnListing('users');
                $selectCols = ['id'];
                if (in_array('name', $cols, true)) {
                    $selectCols[] = 'name';
                }
                if (in_array('email', $cols, true)) {
                    $selectCols[] = 'email';
                }
                if (in_array('phone', $cols, true)) {
                    $selectCols[] = 'phone';
                }
                $users = User::query()->whereIn('id', $nUserIds)->get($selectCols);
                foreach ($users as $u) {
                    $nUsersMap[(string) $u->id] = $u;
                }
            }

            $permissionService = app(LeaderPermissionService::class);
            foreach ($nResults as $nItem) {
                $payload = is_array($nItem->payload) ? $nItem->payload : (is_array($nItem->data) ? $nItem->data : []);
                $category = $payload['category'] ?? 'system';

                if (! empty($categoryFilter) && $categoryFilter !== 'all' && $category !== $categoryFilter) {
                    continue;
                }

                $created = $nItem->created_at ?: now();
                try {
                    $localTime = $created->copy()->setTimezone($timezone);
                    $formattedTime = $localTime->format('d M Y, h:i A').' ('.$timezone.')';
                } catch (\Throwable) {
                    $formattedTime = $created->format('d M Y, h:i A');
                }

                $userObj = $nUsersMap[(string) $nItem->user_id] ?? null;
                $recipientName = $userObj ? (string) ($userObj->name ?? $userObj->email) : ('Leader ID: '.substr((string) $nItem->user_id, 0, 8));

                $roleLabel = 'Circle Leader';
                $roleKey = 'leader';
                if ($userObj) {
                    $resolvedRole = $permissionService->resolveUserRole($userObj);
                    $roleKey = strtolower((string) ($resolvedRole['role'] ?? ''));
                    $roleLabel = $resolvedRole['custom_role_label'] ?? $permissionService->getRoleLabel($roleKey);

                    // Strictly filter: Only show notifications destined for Leader roles
                    if (! ($resolvedRole['is_leader'] ?? false) || in_array($roleKey, ['peermember', 'peer_member', 'peer', 'member'], true)) {
                        continue;
                    }
                } else {
                    continue;
                }

                if (! empty($roleFilter) && $roleFilter !== 'all' && $roleKey !== strtolower($roleFilter)) {
                    continue;
                }

                $triggerType = ($payload['sent_by_admin'] ?? false)
                    ? 'Super Admin Broadcast'
                    : (str_contains((string) $nItem->type, 'milestone') ? 'Milestone Automation' : 'Circle Event Escalation');

                $logs[] = [
                    'id' => (string) $nItem->id,
                    'title' => (string) ($nItem->title ?: 'Leader Alert'),
                    'message' => (string) $nItem->message,
                    'category' => (string) $category,
                    'type' => (string) ($payload['type'] ?? $nItem->type ?? 'general'),
                    'screen' => (string) ($payload['screen'] ?? '/dashboard'),
                    'recipient_id' => (string) $nItem->user_id,
                    'recipient_name' => $recipientName,
                    'recipient_role' => $roleLabel,
                    'recipient_circle' => $payload['circle_name'] ?? ($userObj?->activeCircle?->name ?? 'Ahmedabad Pioneers'),
                    'trigger_type' => $triggerType,
                    'channel' => 'In-App Push',
                    'priority' => (string) ($payload['priority'] ?? 'medium'),
                    'status' => $nItem->is_read ? 'Read' : 'Delivered',
                    'is_read' => (bool) $nItem->is_read,
                    'timezone' => $timezone,
                    'sent_at' => $created->toIso8601String(),
                    'sent_at_formatted' => $formattedTime,
                    'time_ago' => $created->diffForHumans(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'timezone' => $timezone,
            'total_count' => count($logs),
            'data' => $logs,
        ]);
    }

    /**
     * Tab A: Overview metrics from real database records (no synthetic/dummy counts).
     */
    public function getOverview(Request $request): JsonResponse
    {
        $policy = app(LeaderNotificationPolicyService::class);
        $catalogue = $policy->getEventCatalogue();
        $flowStates = $policy->getFlowStates();

        $activeFlows = 0;
        $disabledFlows = 0;
        foreach ($catalogue as $key => $spec) {
            if ($policy->isFlowEnabled($key)) {
                $activeFlows++;
            } else {
                $disabledFlows++;
            }
        }

        $total = 0;
        $delivered = 0;
        $failed = 0;
        $pending = 0;
        $read = 0;
        $unread = 0;
        $criticalFailures = 0;

        if (Schema::hasTable('app_notifications')) {
            $q = AppNotification::query();
            if ($request->filled('date_from')) {
                $q->where('created_at', '>=', (string) $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $q->where('created_at', '<=', (string) $request->input('date_to').' 23:59:59');
            }
            if ($request->filled('category') && $request->input('category') !== 'all') {
                $q->where('category', (string) $request->input('category'));
            }
            if ($request->filled('type') && $request->input('type') !== 'all') {
                $q->where('type', (string) $request->input('type'));
            }

            $rows = $q->orderByDesc('created_at')->take(2000)->get();
            foreach ($rows as $row) {
                $data = is_array($row->data) ? $row->data : [];
                $type = (string) ($row->type ?: ($data['type'] ?? ''));
                $cat = (string) ($row->category ?: ($data['category'] ?? ''));
                $screen = (string) ($row->screen ?: ($data['screen'] ?? ''));
                if (! $policy->isLeadershipAudiencePayload($data, $type, $cat, $screen)) {
                    continue;
                }
                $total++;
                if ($row->failed_at !== null || strtolower((string) $row->status) === 'failed') {
                    $failed++;
                    if (in_array(strtolower((string) $row->priority), ['urgent', 'critical'], true)
                        || ($data['delivery_tier'] ?? '') === 'critical') {
                        $criticalFailures++;
                    }
                } elseif ($row->sent_at !== null || strtolower((string) $row->status) === 'delivered') {
                    $delivered++;
                } else {
                    $pending++;
                }
                if ($row->read_at !== null) {
                    $read++;
                } else {
                    $unread++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_notifications' => $total,
                'delivered_count' => $delivered,
                'failed_count' => $failed,
                'pending_count' => $pending,
                'read_count' => $read,
                'unread_count' => $unread,
                'active_flows_count' => $activeFlows,
                'disabled_flows_count' => $disabledFlows,
                'critical_failures_count' => $criticalFailures,
                'flow_overrides_count' => count($flowStates),
            ],
        ]);
    }

    /**
     * Authoritative dynamic event registry, categories, dynamic roles, and flow states.
     */
    public function getEventRegistry(Request $request): JsonResponse
    {
        $policy = app(LeaderNotificationPolicyService::class);
        $catalogue = $policy->getEventCatalogue();
        $flowStates = $policy->getFlowStates();
        $roles = $policy->getDynamicRolesCatalog();

        $events = [];
        $categories = [];

        foreach ($catalogue as $key => $spec) {
            $isCritical = $policy->isCriticalNotification(
                $key,
                (string) ($spec['priority'] ?? ''),
                (string) ($spec['delivery_tier'] ?? '')
            );
            $enabled = $policy->isFlowEnabled($key);
            $cat = (string) ($spec['category'] ?? 'system');
            $categories[$cat] = true;

            $lastTriggeredAt = null;
            $lastStatus = null;
            if (Schema::hasTable('app_notifications')) {
                $latest = AppNotification::query()->where('type', $key)->orderByDesc('created_at')->first();
                if ($latest) {
                    $lastTriggeredAt = $latest->created_at?->toIso8601String();
                    $lastStatus = $latest->failed_at !== null ? 'failed' : ($latest->sent_at !== null ? 'sent' : (string) $latest->status);
                }
            }

            $events[] = [
                'event_id' => (string) ($spec['id'] ?? $key),
                'notification_type' => $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'category' => $cat,
                'delivery_tier' => (string) ($spec['delivery_tier'] ?? 'important'),
                'priority' => (string) ($spec['priority'] ?? 'medium'),
                'requires_action' => (bool) ($spec['requires_action'] ?? false),
                'financial_sensitive' => (bool) ($spec['financial_sensitive'] ?? false),
                'non_disableable' => $isCritical,
                'enabled' => $enabled,
                'entity_type' => (string) ($spec['entity_type'] ?? 'system'),
                'screen' => (string) ($spec['screen'] ?? '/dashboard'),
                'primary_roles' => $spec['primary_roles'] ?? [],
                'backup_roles' => $spec['backup_roles'] ?? [],
                'info_roles' => $spec['info_roles'] ?? [],
                'global_founder' => (bool) ($spec['global_founder'] ?? false),
                'super_admin_push' => (bool) ($spec['super_admin_push'] ?? false),
                'last_triggered_at' => $lastTriggeredAt,
                'last_delivery_status' => $lastStatus,
                'updated_by' => $flowStates[$key]['updated_by'] ?? null,
                'disabled_reason' => $flowStates[$key]['reason'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
                'categories' => array_values(array_keys($categories)),
                'roles' => $roles,
            ],
        ]);
    }

    /**
     * Enable or disable an optional notification flow (blocks critical/non-disableable flows).
     */
    public function updateFlowState(Request $request, string $eventKey): JsonResponse
    {
        $policy = app(LeaderNotificationPolicyService::class);
        $catalogue = $policy->getEventCatalogue();

        if (! isset($catalogue[$eventKey])) {
            return response()->json(['success' => false, 'message' => 'Unknown notification event type.'], 404);
        }

        $spec = $catalogue[$eventKey];
        $enabled = (bool) $request->input('enabled', true);
        $reason = trim((string) $request->input('reason', ''));
        $isCritical = $policy->isCriticalNotification(
            $eventKey,
            (string) ($spec['priority'] ?? ''),
            (string) ($spec['delivery_tier'] ?? '')
        );

        if (! $enabled && $isCritical) {
            return response()->json([
                'success' => false,
                'message' => 'This critical governance/security/financial notification flow is mandatory (non_disableable) and cannot be disabled.',
            ], 422);
        }

        $states = $policy->getFlowStates();
        $previous = $states[$eventKey] ?? ['enabled' => true];
        $actor = $request->user();
        $nextState = [
            'enabled' => $enabled,
            'reason' => $reason !== '' ? $reason : null,
            'updated_by' => $actor ? (string) ($actor->email ?? $actor->id) : 'admin',
            'updated_at' => now()->toIso8601String(),
        ];
        $states[$eventKey] = $nextState;

        if (Schema::hasTable('app_configs')) {
            DB::table('app_configs')->updateOrInsert(
                ['key' => 'leader_notification_flow_states'],
                ['value' => json_encode($states), 'updated_at' => now()]
            );
        }

        $policy->recordAdminAudit([
            'actor_id' => $actor ? (string) $actor->id : null,
            'action' => $enabled ? 'flow_enabled' : 'flow_disabled',
            'event_key' => $eventKey,
            'previous_config' => $previous,
            'new_config' => $nextState,
            'reason' => $reason !== '' ? $reason : null,
            'ip' => $request->ip(),
            'correlation_id' => (string) Str::uuid(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Notification flow {$eventKey} ".($enabled ? 'enabled' : 'disabled').'.',
            'data' => $nextState,
        ]);
    }

    /**
     * Fetch real target records for a given notification type & recipient.
     */
    public function getTargetRecords(Request $request): JsonResponse
    {
        $eventKey = (string) $request->query('type', '');
        $search = trim((string) $request->query('search', ''));
        $records = [];

        $policy = app(LeaderNotificationPolicyService::class);
        $spec = $policy->getEventCatalogue()[$eventKey] ?? null;
        if (! $spec) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $entityType = (string) ($spec['entity_type'] ?? '');

        if ($entityType === 'circular' && Schema::hasTable('circulars')) {
            $rows = Circular::query()
                ->whereNull('deleted_at')
                ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                ->orderByDesc('created_at')
                ->take(20)
                ->get(['id', 'title', 'status']);
            foreach ($rows as $r) {
                $records[] = ['id' => (string) $r->id, 'label' => (string) $r->title, 'subtitle' => (string) $r->status];
            }
        } elseif (($entityType === 'circle' || $entityType === 'event') && Schema::hasTable('circles')) {
            $rows = Circle::query()
                ->whereNull('deleted_at')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->take(20)
                ->get(['id', 'name', 'status']);
            foreach ($rows as $r) {
                $records[] = ['id' => (string) $r->id, 'label' => (string) $r->name, 'subtitle' => (string) ($r->status ?? 'active')];
            }
        } elseif ($entityType === 'role_assignment' || $entityType === 'security') {
            foreach ($policy->getDynamicRolesCatalog() as $r) {
                $records[] = ['id' => (string) $r['role_key'], 'label' => (string) $r['role_label'], 'subtitle' => $r['is_active'] ? 'Active' : 'Inactive'];
            }
        }

        return response()->json(['success' => true, 'data' => $records]);
    }

    /**
     * Send a single-recipient test notification with is_test=true, correlation_id, and audit trail.
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $recipientId = trim((string) $request->input('recipient_id', ''));
        $eventKey = trim((string) $request->input('notification_type', ''));
        $targetRecordId = trim((string) $request->input('target_record_id', ''));
        $customTitle = trim((string) $request->input('title', ''));
        $customMessage = trim((string) $request->input('message', ''));

        if ($recipientId === '' || $eventKey === '') {
            return response()->json(['success' => false, 'message' => 'Recipient and notification type are required.'], 422);
        }

        $policy = app(LeaderNotificationPolicyService::class);
        $permissionService = app(LeaderPermissionService::class);
        $spec = $policy->getEventCatalogue()[$eventKey] ?? null;
        if (! $spec) {
            return response()->json(['success' => false, 'message' => 'Unknown notification type in backend registry.'], 422);
        }

        $recipient = Schema::hasTable('users') ? User::query()->find($recipientId) : null;
        if (! $recipient || ! $permissionService->isLeader($recipient)) {
            return response()->json(['success' => false, 'message' => 'Selected recipient is not an active Leadership App user.'], 422);
        }

        if ($policy->isFinancialSensitive($eventKey, (string) $spec['category'], [])
            && ! $policy->canUserReceiveFinancialNotification($recipient)) {
            return response()->json(['success' => false, 'message' => 'Recipient lacks financial permission (access_finance) for this notification type.'], 403);
        }

        $correlationId = (string) Str::uuid();
        $title = $customTitle !== '' ? $customTitle : '[TEST] '.ucwords(str_replace('_', ' ', $eventKey));
        $message = $customMessage !== '' ? $customMessage : 'Test notification initiated from Leadership Notification Control Center.';
        $actor = $request->user();

        $appNotification = app(LeaderNotificationDispatcherService::class)->dispatchToUser(
            $recipientId,
            $eventKey,
            $title,
            $message,
            (string) ($spec['screen'] ?? '/dashboard'),
            [
                'is_test' => true,
                'correlation_id' => $correlationId,
                'initiated_by_admin_id' => $actor ? (string) $actor->id : null,
                'entity_type' => $spec['entity_type'] ?? 'system',
                'entity_id' => $targetRecordId !== '' ? $targetRecordId : null,
                'target_record_id' => $targetRecordId !== '' ? $targetRecordId : null,
                'requires_action' => (bool) ($spec['requires_action'] ?? false),
                'delivery_tier' => (string) ($spec['delivery_tier'] ?? 'important'),
                'idempotency_window' => $correlationId,
            ],
            (string) ($spec['category'] ?? 'system'),
            (string) ($spec['priority'] ?? 'high')
        );

        $policy->recordAdminAudit([
            'actor_id' => $actor ? (string) $actor->id : null,
            'action' => 'send_test_notification',
            'event_key' => $eventKey,
            'recipient_id' => $recipientId,
            'correlation_id' => $correlationId,
            'new_config' => ['target_record_id' => $targetRecordId !== '' ? $targetRecordId : null],
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'notification_id' => $appNotification ? (string) $appNotification->id : null,
                'correlation_id' => $correlationId,
                'persisted' => $appNotification !== null,
                'fcm_dispatched' => $appNotification?->sent_at !== null,
                'delivery_status' => $appNotification?->failed_at !== null ? 'failed' : ($appNotification?->sent_at !== null ? 'sent_to_fcm' : 'persisted'),
                'expected_destination' => (string) ($spec['screen'] ?? '/dashboard'),
                'failure_reason' => $appNotification?->failure_reason,
            ],
        ]);
    }

    /**
     * Detailed notification delivery lifecycle & masked payload inspection.
     */
    public function getNotificationLogDetail(Request $request, string $id): JsonResponse
    {
        if (! Schema::hasTable('app_notifications')) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $notif = AppNotification::query()->find($id);
        if (! $notif) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $data = is_array($notif->data) ? $notif->data : [];
        $policy = app(LeaderNotificationPolicyService::class);
        $actor = $request->user();
        $canViewSensitive = $actor ? $policy->canUserReceiveFinancialNotification($actor) : false;

        if (! $canViewSensitive && $policy->isFinancialSensitive((string) $notif->type, (string) $notif->category, $data)) {
            $data = ['masked' => true, 'reason' => 'Restricted financial/security payload'];
        }

        $deliveryLogs = Schema::hasTable('notification_delivery_logs')
            ? NotificationDeliveryLog::query()
                ->where('notification_id', $notif->id)
                ->orderByDesc('attempted_at')
                ->get(['id', 'channel', 'provider', 'status', 'error_message', 'attempted_at', 'delivered_at'])
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $notif->id,
                'type' => (string) $notif->type,
                'category' => (string) $notif->category,
                'title' => (string) $notif->title,
                'body' => (string) $notif->body,
                'screen' => (string) $notif->screen,
                'priority' => (string) $notif->priority,
                'lifecycle' => [
                    'persisted' => true,
                    'sent_to_fcm' => $notif->sent_at !== null,
                    'failed' => $notif->failed_at !== null,
                    'read' => $notif->read_at !== null,
                ],
                'sent_at' => $notif->sent_at?->toIso8601String(),
                'failed_at' => $notif->failed_at?->toIso8601String(),
                'failure_reason' => $notif->failure_reason,
                'read_at' => $notif->read_at?->toIso8601String(),
                'payload' => $data,
                'delivery_attempts' => $deliveryLogs,
            ],
        ]);
    }

    /**
     * Administrative audit trail for notification governance changes & test sends.
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        $logs = [];
        if (Schema::hasTable('app_configs')) {
            $raw = DB::table('app_configs')
                ->where('key', 'leader_notification_audit_logs')
                ->value('value');
            $decoded = $raw ? json_decode((string) $raw, true) : [];
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * Get & update global notification routing & privacy policies.
     */
    public function getPolicies(Request $request): JsonResponse
    {
        $defaults = [
            'quiet_hours_default_start' => '22:00',
            'quiet_hours_default_end' => '07:00',
            'mask_financial_push_preview' => true,
            'mask_security_push_preview' => true,
            'overdue_report_days' => (int) config('leader.overdue_report_days', 14),
            'critical_bypass_quiet_hours' => true,
            'version' => 1,
        ];

        if (Schema::hasTable('app_configs')) {
            $raw = DB::table('app_configs')
                ->where('key', 'leader_notification_global_policies')
                ->value('value');
            $decoded = $raw ? json_decode((string) $raw, true) : [];
            if (is_array($decoded)) {
                $defaults = array_merge($defaults, $decoded);
            }
        }

        return response()->json(['success' => true, 'data' => $defaults]);
    }

    public function updatePolicies(Request $request): JsonResponse
    {
        $policy = app(LeaderNotificationPolicyService::class);
        $current = $this->getPolicies($request)->getData(true)['data'] ?? [];
        $next = array_merge($current, [
            'quiet_hours_default_start' => (string) $request->input('quiet_hours_default_start', $current['quiet_hours_default_start'] ?? '22:00'),
            'quiet_hours_default_end' => (string) $request->input('quiet_hours_default_end', $current['quiet_hours_default_end'] ?? '07:00'),
            'overdue_report_days' => max(1, (int) $request->input('overdue_report_days', $current['overdue_report_days'] ?? 14)),
            // Security & financial masking and critical bypass remain locked true server-side
            'mask_financial_push_preview' => true,
            'mask_security_push_preview' => true,
            'critical_bypass_quiet_hours' => true,
            'version' => ((int) ($current['version'] ?? 1)) + 1,
        ]);

        if (Schema::hasTable('app_configs')) {
            DB::table('app_configs')->updateOrInsert(
                ['key' => 'leader_notification_global_policies'],
                ['value' => json_encode($next), 'updated_at' => now()]
            );
        }

        $actor = $request->user();
        $policy->recordAdminAudit([
            'actor_id' => $actor ? (string) $actor->id : null,
            'action' => 'policies_updated',
            'event_key' => 'global_policies',
            'previous_config' => $current,
            'new_config' => $next,
            'ip' => $request->ip(),
            'correlation_id' => (string) Str::uuid(),
        ]);

        return response()->json(['success' => true, 'data' => $next, 'message' => 'Policies updated.']);
    }

    /**
     * Store or update Leader App FCM push token.
     * Prevents token collision with Peers Global member app.
     */
    public function storePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'required_without:fcm_token', 'string'],
            'fcm_token' => ['nullable', 'required_without:token', 'string'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'device_id' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'app' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $user = $request->user();
            $token = (string) ($validated['fcm_token'] ?? $validated['token']);
            $platform = strtolower((string) $validated['platform']);

            // Prefix device_id with 'leader_' to prevent collisions with Peers Global member app
            $rawDeviceId = $validated['device_id'] ?? ('device_'.substr(md5($user->id.$platform), 0, 12));
            $leaderDeviceId = str_starts_with((string) $rawDeviceId, 'leader_') ? $rawDeviceId : 'leader_'.$rawDeviceId;

            // Delete token if it belongs to a different user
            if (Schema::hasTable('user_push_tokens')) {
                UserPushToken::where('token', $token)
                    ->where(UserPushToken::getUserIdColumn(), '!=', $user->id)
                    ->delete();

                // Clean up duplicates for this user for Leader App on this device
                UserPushToken::where('device_id', $leaderDeviceId)
                    ->where(UserPushToken::getUserIdColumn(), $user->id)
                    ->where('token', '!=', $token)
                    ->delete();
            }

            $updates = [
                UserPushToken::getUserIdColumn() => $user->id,
                'platform' => $platform,
                'device_id' => $leaderDeviceId,
                'app_version' => $validated['app_version'] ?? '1.0.0',
            ];

            if (Schema::hasColumn('user_push_tokens', 'app')) {
                $updates['app'] = 'leader';
            }
            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $updates['is_active'] = true;
            }
            if (Schema::hasColumn('user_push_tokens', 'status')) {
                $updates['status'] = 'active';
            }
            if (Schema::hasColumn('user_push_tokens', 'token_status')) {
                $updates['token_status'] = 'active';
            }
            if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                $updates['last_seen_at'] = now();
            }
            if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
                $updates['last_used_at'] = now();
            }
            if (Schema::hasColumn('user_push_tokens', 'failed_at')) {
                $updates['failed_at'] = null;
            }
            if (Schema::hasColumn('user_push_tokens', 'failure_reason')) {
                $updates['failure_reason'] = null;
            }

            $pushToken = UserPushToken::updateOrCreate(
                ['token' => $token],
                $updates
            );

            // Store in user leader-specific columns if present
            if (Schema::hasColumn('users', 'leader_android_fcm_token') && $platform === 'android') {
                $user->leader_android_fcm_token = $token;
                $user->save();
            } elseif (Schema::hasColumn('users', 'leader_ios_fcm_token') && in_array($platform, ['ios', 'apple'], true)) {
                $user->leader_ios_fcm_token = $token;
                $user->save();
            }

            // Also ensure standard user android_fcm_token / ios_fcm_token is populated
            if (Schema::hasColumn('users', 'android_fcm_token') && $platform === 'android') {
                $user->android_fcm_token = $token;
                $user->save();
            } elseif (Schema::hasColumn('users', 'ios_fcm_token') && in_array($platform, ['ios', 'apple'], true)) {
                $user->ios_fcm_token = $token;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Leader push token registered successfully.',
                'data' => [
                    'id' => $pushToken->id,
                    'token' => $pushToken->token,
                    'app' => 'leader',
                    'platform' => $pushToken->platform,
                    'device_id' => $pushToken->device_id,
                    'app_version' => $pushToken->app_version,
                ],
            ]);
        } catch (\Throwable $throwable) {
            Log::error('LeaderNotificationsController storePushToken exception: '.$throwable->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Unable to register leader push token.',
                'error' => $throwable->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Leader App push token on logout.
     */
    public function destroyPushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'required_without:fcm_token', 'string'],
            'fcm_token' => ['nullable', 'required_without:token', 'string'],
        ]);

        try {
            $token = (string) ($validated['fcm_token'] ?? $validated['token']);

            $deleted = UserPushToken::where(UserPushToken::getUserIdColumn(), $request->user()->id)
                ->where('token', $token)
                ->delete();

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Leader push token deleted successfully.',
                'deleted' => $deleted > 0,
            ]);
        } catch (\Throwable $throwable) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Unable to delete leader push token.',
            ], 500);
        }
    }
}
