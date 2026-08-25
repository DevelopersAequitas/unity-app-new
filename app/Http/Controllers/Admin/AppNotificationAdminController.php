<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Notifications\AppNotificationCatalogService;
use App\Services\Notifications\FcmService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AppNotificationAdminController extends Controller
{
    public function __construct(
        private readonly AppNotificationCatalogService $catalogService,
        private readonly FcmService $fcmService
    ) {}

    /**
     * Dynamically create a new App Notification type and save it to the catalog.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'navigation_screen' => ['required', 'string', 'max:255'],
            'title_template' => ['required', 'string', 'max:255'],
            'body_template' => ['required', 'string'],
            'default_payload' => ['nullable', 'string'],
            'dynamic_params' => ['nullable', 'string'],
        ]);

        $key = ! empty($validated['template_key'])
            ? Str::slug($validated['template_key'], '_')
            : Str::slug($validated['name'], '_');

        $payload = [];
        if (! empty($validated['default_payload'])) {
            $decoded = json_decode((string) $validated['default_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $payload['navigation_screen'] = $validated['navigation_screen'];
        $payload['screen'] = ltrim($validated['navigation_screen'], '/');
        $payload['type'] = $key;
        if (! empty($validated['category'])) {
            $payload['category'] = $validated['category'];
        }

        $dynamicParams = ['{name}' => 'Recipient name'];
        if (! empty($validated['dynamic_params'])) {
            $decodedParams = json_decode((string) $validated['dynamic_params'], true);
            if (is_array($decodedParams)) {
                $dynamicParams = $decodedParams;
            }
        }

        if (Schema::hasTable('notification_templates')) {
            NotificationTemplate::updateOrCreate(
                ['template_key' => $key],
                [
                    'name' => $validated['name'],
                    'title_template' => $validated['title_template'],
                    'body_template' => $validated['body_template'],
                    'default_payload' => $payload,
                    'dynamic_params' => $dynamicParams,
                ]
            );
        }

        $message = "New notification '{$validated['name']}' has been created and automatically added to the App Notifications list.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'notification' => $this->catalogService->getByKey($key),
            ]);
        }

        return redirect()->route('admin.app-notifications.index')->with('success', $message);
    }

    /**
     * Display the App Notifications hub with navigation screens, payloads, and peer sender.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $category = $request->string('category')->toString();

        $notifications = $this->catalogService->getAll($search ?: null, $category ?: null);
        $categories = $this->catalogService->getCategories();
        $navigationScreens = $this->catalogService->getNavigationScreens();

        // Calculate dashboard overview statistics
        $hasAppNotifications = Schema::hasTable('app_notifications');
        $hasPushTokens = Schema::hasTable('user_push_tokens');
        $hasDeliveryLogs = Schema::hasTable('notification_delivery_logs');

        $userIdColumn = UserPushToken::getUserIdColumn();
        $pushReadyPeersCount = $hasPushTokens
            ? DB::table('user_push_tokens')
                ->where('is_active', true)
                ->whereNotNull('token')
                ->distinct($userIdColumn)
                ->count($userIdColumn)
            : 0;

        $stats = [
            'total_catalog_items' => $notifications->count(),
            'total_registered_types' => count($this->catalogService->getAll()),
            'total_navigation_screens' => count($navigationScreens),
            'push_ready_peers' => $pushReadyPeersCount,
            'today_sent' => $hasAppNotifications
                ? AppNotification::whereDate('created_at', today())->count()
                : 0,
            'today_delivered' => $hasDeliveryLogs
                ? NotificationDeliveryLog::where('channel', 'push')
                    ->where('status', 'sent')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
        ];

        // Fetch recent delivery logs
        $recentLogs = $hasDeliveryLogs
            ? NotificationDeliveryLog::with(['user', 'notification'])
                ->where('channel', 'push')
                ->latest()
                ->limit(15)
                ->get()
            : collect();

        // Preload an initial batch of active peers for quick selection
        $initialPeers = User::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => $this->formatPeerData($user));

        return view('admin.app-notifications.index', compact(
            'notifications',
            'categories',
            'navigationScreens',
            'stats',
            'recentLogs',
            'initialPeers',
            'search',
            'category'
        ));
    }

    /**
     * AJAX search endpoint to find peers by name, email, phone, or circle.
     */
    public function searchPeers(Request $request): JsonResponse
    {
        $queryStr = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $perPage = 25;

        $query = User::query();

        if (Schema::hasColumn('users', 'status')) {
            $query->where('status', 'active');
        }

        if ($queryStr !== '') {
            $needle = '%'.$queryStr.'%';
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($needle, $queryStr, $like): void {
                $q->where('first_name', $like, $needle)
                    ->orWhere('last_name', $like, $needle)
                    ->orWhere('name', $like, $needle)
                    ->orWhere('email', $like, $needle)
                    ->orWhere('phone', $like, $needle)
                    ->orWhere('mobile', $like, $needle);

                if (Str::isUuid($queryStr)) {
                    $q->orWhere('id', $queryStr);
                }

                if (Schema::hasTable('circles') && Schema::hasTable('circle_members') && method_exists(User::class, 'circles')) {
                    $q->orWhereHas('circles', fn (Builder $c) => $c->where('name', $like, $needle));
                }
            });
        }

        $total = (clone $query)->count();
        $users = $query->forPage($page, $perPage)->get();

        $results = $users->map(fn (User $user) => $this->formatPeerData($user))->values();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    /**
     * Get single peer details with FCM tokens status.
     */
    public function peerDetails(string $id): JsonResponse
    {
        $user = (Schema::hasTable('circles') && Schema::hasTable('circle_members'))
            ? User::with('circles')->find($id)
            : User::find($id);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Peer not found.'], 404);
        }

        $tokens = $this->fcmService->activeTokensForUser($user->id);

        $recentNotifications = Schema::hasTable('app_notifications')
            ? AppNotification::where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        return response()->json([
            'success' => true,
            'peer' => $this->formatPeerData($user),
            'tokens_count' => $tokens->count(),
            'tokens' => $tokens->map(fn (UserPushToken $t) => [
                'id' => (string) $t->id,
                'platform' => $t->platform ?? 'unknown',
                'app_version' => $t->app_version,
                'device_id' => $t->device_id,
                'is_active' => (bool) ($t->is_active ?? true),
                'last_used_at' => optional($t->last_used_at ?? $t->updated_at)->toDateTimeString(),
            ]),
            'recent_notifications' => $recentNotifications,
        ]);
    }

    /**
     * Preview notification rendered for a specific peer or default sample data.
     */
    public function preview(Request $request, string $key): JsonResponse
    {
        $peerId = $request->input('peer_id');
        $user = $peerId ? User::find($peerId) : null;

        $customParams = (array) $request->input('custom_params', []);
        $rendered = $this->catalogService->renderForUser($key, $user, $customParams);

        return response()->json([
            'success' => true,
            'key' => $key,
            'title' => $rendered['title'],
            'body' => $rendered['body'],
            'navigation_screen' => $rendered['navigation_screen'],
            'payload' => $rendered['payload'],
            'peer' => $user ? $this->formatPeerData($user) : null,
        ]);
    }

    /**
     * Send an App Notification to selected peer(s).
     */
    public function sendToPeers(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'string', 'exists:users,id'],
            'notification_key' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'navigation_screen' => ['required', 'string', 'max:255'],
            'payload_json' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:urgent,high,medium,low'],
            'channel' => ['nullable', 'in:push,in_app_only,push_email'],
        ]);

        $userIds = $validated['user_ids'];
        $notificationKey = $validated['notification_key'] ?? 'custom_app_notification';
        $channel = $validated['channel'] ?? 'push';
        $priority = $validated['priority'] ?? 'high';
        $navScreen = $validated['navigation_screen'];

        $parsedPayload = [];
        if (! empty($validated['payload_json'])) {
            $decoded = json_decode((string) $validated['payload_json'], true);
            if (is_array($decoded)) {
                $parsedPayload = $decoded;
            }
        }

        $catalogItem = $this->catalogService->getByKey($notificationKey);
        $category = $catalogItem['category'] ?? 'System & General';

        $users = User::whereIn('id', $userIds)->get();

        $successCount = 0;
        $pushPushedCount = 0;
        $failedCount = 0;
        $deliveryDetails = [];

        foreach ($users as $user) {
            try {
                // Render personalized placeholders if needed
                $personalizedTitle = str_replace('{name}', $this->getUserDisplayName($user), $validated['title']);
                $personalizedBody = str_replace('{name}', $this->getUserDisplayName($user), $validated['body']);

                $dataPayload = array_merge([
                    'navigation_screen' => $navScreen,
                    'screen' => ltrim($navScreen, '/'),
                    'tap_destination' => $navScreen,
                    'type' => $notificationKey,
                    'user_id' => (string) $user->id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ], $parsedPayload);

                // 1. Create In-App Notification (legacy table if exists)
                if (Schema::hasTable('notifications')) {
                    try {
                        Notification::create([
                            'user_id' => $user->id,
                            'type' => $notificationKey,
                            'payload' => $dataPayload,
                            'is_read' => false,
                            'created_at' => now(),
                        ]);
                    } catch (Throwable) {
                        // ignore legacy table errors
                    }
                }

                // 2. Create AppNotification record
                $appNotification = null;
                if (Schema::hasTable('app_notifications')) {
                    $appNotification = AppNotification::create([
                        'user_id' => $user->id,
                        'type' => $notificationKey,
                        'category' => $category,
                        'title' => $personalizedTitle,
                        'body' => $personalizedBody,
                        'channel' => $channel,
                        'priority' => $priority,
                        'screen' => $navScreen,
                        'data' => $dataPayload,
                        'status' => 'pending',
                    ]);
                }

                // 3. Dispatch FCM Push if channel includes push
                $pushResult = ['success' => false, 'error' => 'In-app only'];
                if ($channel !== 'in_app_only') {
                    $pushResult = $this->fcmService->sendToUser(
                        $user,
                        $personalizedTitle,
                        $personalizedBody,
                        $dataPayload,
                        $appNotification
                    );

                    if ($pushResult['success'] ?? false) {
                        $pushPushedCount++;
                        $appNotification?->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);
                    } else {
                        $appNotification?->update([
                            'status' => ($pushResult['error'] ?? '') === 'No active push token' ? 'sent' : 'failed',
                            'failed_at' => ($pushResult['error'] ?? '') === 'No active push token' ? null : now(),
                            'failure_reason' => $pushResult['error'] ?? null,
                            'sent_at' => now(),
                        ]);
                    }
                } else {
                    $appNotification?->update(['status' => 'sent', 'sent_at' => now()]);
                }

                $successCount++;
                $deliveryDetails[] = [
                    'user_id' => (string) $user->id,
                    'user_name' => $this->getUserDisplayName($user),
                    'success' => true,
                    'push_delivered' => $pushResult['success'] ?? false,
                    'error' => $pushResult['error'] ?? null,
                ];
            } catch (Throwable $e) {
                $failedCount++;
                Log::error('AppNotificationAdminController::sendToPeers failed for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $deliveryDetails[] = [
                    'user_id' => (string) $user->id,
                    'user_name' => $this->getUserDisplayName($user),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $message = "Notification sent to {$successCount} peer(s). ({$pushPushedCount} received push alert).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $successCount > 0,
                'message' => $message,
                'total_targeted' => count($userIds),
                'success_count' => $successCount,
                'push_delivered_count' => $pushPushedCount,
                'failed_count' => $failedCount,
                'details' => $deliveryDetails,
            ]);
        }

        return redirect()->route('admin.app-notifications.index')
            ->with('success', $message);
    }

    /**
     * Send ALL sample notifications to a selected peer for complete mobile UI/UX and payload testing.
     */
    public function sendAllToPeer(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $allCatalogItems = $this->catalogService->getAll();

        $tokens = $this->fcmService->activeTokensForUser($user->id);
        $tokenCount = $tokens->count();

        $sentCount = 0;
        $pushDeliveredCount = 0;
        $dispatchedList = [];

        foreach ($allCatalogItems as $index => $item) {
            $rendered = $this->catalogService->renderForUser($item['key'], $user);

            $dataPayload = array_merge($rendered['payload'], [
                'batch_test' => true,
                'test_index' => $index + 1,
                'total_test_items' => $allCatalogItems->count(),
            ]);

            // Save in-app notification
            $appNotification = null;
            if (Schema::hasTable('app_notifications')) {
                $appNotification = AppNotification::create([
                    'user_id' => $user->id,
                    'type' => $item['key'],
                    'category' => $item['category'] ?? 'General',
                    'title' => $rendered['title'],
                    'body' => $rendered['body'],
                    'channel' => 'push',
                    'priority' => $item['priority'] ?? 'high',
                    'screen' => $rendered['navigation_screen'],
                    'data' => $dataPayload,
                    'dedupe_key' => 'batch_all:'.$user->id.':'.$item['key'].':'.now()->timestamp.':'.$index,
                    'status' => 'pending',
                ]);
            }

            // Also insert in legacy notifications table
            if (Schema::hasTable('notifications')) {
                try {
                    Notification::create([
                        'user_id' => $user->id,
                        'type' => $item['key'],
                        'payload' => $dataPayload,
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                } catch (Throwable) {
                    // ignore
                }
            }

            // Attempt FCM push to all user tokens
            $pushed = false;
            foreach ($tokens as $token) {
                $res = $this->fcmService->sendToToken(
                    $token,
                    $rendered['title'],
                    $rendered['body'],
                    $appNotification ? $appNotification->dataPayload() : $dataPayload,
                    $appNotification
                );
                if ($res['success'] ?? false) {
                    $pushed = true;
                    $pushDeliveredCount++;
                }
            }

            $appNotification?->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $sentCount++;
            $dispatchedList[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'title' => $rendered['title'],
                'navigation_screen' => $rendered['navigation_screen'],
                'pushed' => $pushed,
            ];
        }

        $userName = $this->getUserDisplayName($user);
        $message = "Successfully dispatched all {$sentCount} app notifications to {$userName}. ({$pushDeliveredCount} push message(s) delivered via FCM).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => $this->formatPeerData($user),
                'total_sent' => $sentCount,
                'push_delivered_count' => $pushDeliveredCount,
                'active_tokens_count' => $tokenCount,
                'dispatched_notifications' => $dispatchedList,
            ]);
        }

        return redirect()->route('admin.app-notifications.index')
            ->with('success', $message);
    }

    /**
     * Get paginated delivery logs with payload inspection.
     */
    public function deliveryLogs(Request $request): JsonResponse
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return response()->json(['data' => []]);
        }

        $logs = NotificationDeliveryLog::with(['user', 'notification'])
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }

    /**
     * Helper to format peer data consistently.
     *
     * @return array<string, mixed>
     */
    private function formatPeerData(User $user): array
    {
        $displayName = $this->getUserDisplayName($user);
        $phone = (string) ($user->phone ?? $user->mobile ?? '');
        $circle = (Schema::hasTable('circles') && Schema::hasTable('circle_members') && method_exists($user, 'circles'))
            ? ($user->circles()->first()?->name ?? 'General')
            : 'General';

        $tokensCount = 0;
        if (Schema::hasTable('user_push_tokens')) {
            $userIdCol = UserPushToken::getUserIdColumn();
            $tokensCount = UserPushToken::where($userIdCol, $user->id)
                ->where('is_active', true)
                ->whereNotNull('token')
                ->count();
        }

        if ($tokensCount === 0 && (filled($user->android_fcm_token) || filled($user->ios_fcm_token))) {
            $tokensCount = (filled($user->android_fcm_token) ? 1 : 0) + (filled($user->ios_fcm_token) ? 1 : 0);
        }

        return [
            'id' => (string) $user->id,
            'name' => $displayName,
            'email' => (string) ($user->email ?? ''),
            'phone' => $phone,
            'avatar' => $user->avatar ?? $user->profile_photo_url ?? null,
            'circle' => $circle,
            'push_ready' => $tokensCount > 0,
            'tokens_count' => $tokensCount,
        ];
    }

    /**
     * Helper to get user display name cleanly.
     */
    private function getUserDisplayName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $fullName = trim($first.' '.$last);

        if ($fullName !== '') {
            return $fullName;
        }

        return (string) ($user->name ?? $user->display_name ?? $user->email ?? 'Peer');
    }
}
