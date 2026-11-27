<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeedNetworking\SpeedNetworkingMatchActionRequest;
use App\Models\CircleMember;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Services\Events\EventRazorpayPaymentFinalizer;
use App\Services\Events\EventRazorpayPaymentService;
use App\Services\Peers\PeerNoteService;
use App\Services\SpeedNetworking\AgoraTokenService;
use App\Services\SpeedNetworking\SpeedNetworkingP2pService;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpeedNetworkingApiController extends Controller
{
    protected SpeedNetworkingP2pService $speedNetworkingP2pService;

    public function __construct(
        protected SpeedNetworkingService $speedNetworkingService,
        protected AgoraTokenService $agoraTokenService,
        ?SpeedNetworkingP2pService $speedNetworkingP2pService = null
    ) {
        $this->speedNetworkingP2pService = $speedNetworkingP2pService ?? app(SpeedNetworkingP2pService::class);
    }

    /**
     * Get the configured Agora App ID.
     */
    protected function getAgoraAppId(): ?string
    {
        try {
            return $this->agoraTokenService->getAppId();
        } catch (\Throwable) {
            $appId = config('services.agora.app_id') ?: env('AGORA_APP_ID');

            return ! empty($appId) ? (string) $appId : null;
        }
    }

    /**
     * Check circle restriction access for speed networking events.
     * If a circle is assigned, only members of that circle may join.
     * If no circle is assigned, all users may join.
     */
    protected function checkCircleAccess(Event|SpeedNetworkingEvent $event, $user): array
    {
        if (! ($event instanceof Event)) {
            $isRestricted = (bool) ($event->circle_restricted ?? false) && ! empty($event->circle_id);
            if (! $isRestricted) {
                return [
                    'allowed' => true,
                    'circle_restricted' => false,
                    'circle_ids' => [],
                    'circle_name' => null,
                ];
            }

            $eventCircleIds = [(string) $event->circle_id];
            $circleName = $event->circle?->name;

            if (! $user) {
                return [
                    'allowed' => false,
                    'circle_restricted' => true,
                    'circle_ids' => $eventCircleIds,
                    'circle_name' => $circleName,
                    'message' => 'Please log in to join this circle Speed Networking event.',
                ];
            }

            if (! Schema::hasTable('circle_members')) {
                return [
                    'allowed' => true,
                    'circle_restricted' => true,
                    'circle_ids' => $eventCircleIds,
                    'circle_name' => $circleName,
                ];
            }

            $activeStatuses = array_unique(array_merge(CircleMember::activeStatuses(), ['approved', 'active']));
            $isMember = CircleMember::query()
                ->whereIn('circle_id', $eventCircleIds)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->whereIn('status', $activeStatuses)
                ->exists();

            return [
                'allowed' => $isMember,
                'circle_restricted' => true,
                'circle_ids' => $eventCircleIds,
                'circle_name' => $circleName,
                'message' => $isMember ? null : 'This Speed Networking event is exclusive to members of the selected Circle.',
            ];
        }

        $eventCircleIds = [];
        if (! empty($event->circle_id)) {
            $eventCircleIds[] = (string) $event->circle_id;
        }
        if ($event->relationLoaded('circles')) {
            $eventCircleIds = array_merge($eventCircleIds, $event->circles->pluck('id')->map(fn ($id) => (string) $id)->all());
        } else {
            $eventCircleIds = array_merge($eventCircleIds, $event->circles()->pluck('circles.id')->map(fn ($id) => (string) $id)->all());
        }
        $eventCircleIds = array_values(array_filter(array_unique($eventCircleIds)));

        $circleName = $event->circle?->name;

        if (empty($eventCircleIds)) {
            return [
                'allowed' => true,
                'circle_restricted' => false,
                'circle_ids' => [],
                'circle_name' => $circleName,
            ];
        }

        if (! $user) {
            return [
                'allowed' => false,
                'circle_restricted' => true,
                'circle_ids' => $eventCircleIds,
                'circle_name' => $circleName,
                'message' => 'Please log in to join this circle Speed Collaboration event.',
            ];
        }

        $activeStatuses = array_unique(array_merge(CircleMember::activeStatuses(), ['approved', 'active']));
        $isMember = CircleMember::query()
            ->whereIn('circle_id', $eventCircleIds)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereIn('status', $activeStatuses)
            ->exists();

        return [
            'allowed' => $isMember,
            'circle_restricted' => true,
            'circle_ids' => $eventCircleIds,
            'circle_name' => $circleName,
            'message' => $isMember ? null : 'This Speed Collaboration event is exclusive to members of the selected Circle.',
        ];
    }

    /**
     * Resolve event from speed_networking_events or events table.
     */
    protected function resolveSpeedNetworkingEvent(string $eventId): ?SpeedNetworkingEvent
    {
        if (! Schema::hasTable('speed_networking_events')) {
            return null;
        }

        $event = SpeedNetworkingEvent::find($eventId);
        if (! $event && Schema::hasTable('events')) {
            $base = Event::find($eventId);
            if ($base) {
                $event = SpeedNetworkingEvent::where('id', $base->id)
                    ->orWhere('title', $base->title)
                    ->latest()
                    ->first();
            }
        }

        return $event;
    }

    /**
     * Verify circle access and optional payment status for authenticated user.
     */
    protected function authorizeEventAccess(Event|SpeedNetworkingEvent $event, $user, bool $enforcePayment = true): ?JsonResponse
    {
        $circleAccess = $this->checkCircleAccess($event, $user);
        if (! $circleAccess['allowed']) {
            return response()->json([
                'status' => 'circle_restricted',
                'success' => false,
                'requires_payment' => false,
                'can_join' => false,
                'message' => $circleAccess['message'] ?? 'This Speed Networking event is exclusive to members of the selected Circle.',
            ], 403);
        }

        if ($enforcePayment) {
            $paymentInfo = $this->checkPaymentStatus($event, $user);
            if ($paymentInfo['payment_required']) {
                return response()->json([
                    'status' => 'payment_required',
                    'success' => false,
                    'requires_payment' => true,
                    'can_join' => false,
                    'message' => 'Speed Networking event fees payment required before accessing this session.',
                ], 403);
            }
        }

        return null;
    }

    /**
     * Enforce 60-second per-user idempotency when Idempotency-Key header or idempotency_key param is provided.
     *
     * @param  callable(): JsonResponse  $callback
     */
    protected function withIdempotency(Request $request, string $eventId, string $actionName, callable $callback): JsonResponse
    {
        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key') ?: ''));
        if ($key === '') {
            return $callback();
        }

        $userId = (string) ($request->user()?->id ?? 'guest');
        $cacheKey = 'sn:idempotency:'.$userId.':'.$eventId.':'.$actionName.':'.hash('sha256', $key);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'], $cached['body'])) {
            return response()->json($cached['body'], (int) $cached['status'])
                ->header('X-Idempotent-Replay', 'true');
        }

        $executeAndStore = function () use ($cacheKey, $callback): JsonResponse {
            $cachedInside = Cache::get($cacheKey);
            if (is_array($cachedInside) && isset($cachedInside['status'], $cachedInside['body'])) {
                return response()->json($cachedInside['body'], (int) $cachedInside['status'])
                    ->header('X-Idempotent-Replay', 'true');
            }

            /** @var JsonResponse $response */
            $response = $callback();
            $statusCode = $response->getStatusCode();

            if ($statusCode < 500) {
                $body = $response->getData(true);
                Cache::put($cacheKey, [
                    'status' => $statusCode,
                    'body' => $body,
                ], now()->addSeconds(60));
            }

            return $response;
        };

        try {
            return Cache::lock($cacheKey.':lock', 10)->block(5, $executeAndStore);
        } catch (\Throwable) {
            return $executeAndStore();
        }
    }

    /**
     * Format match payload consistently across join, next-match, match-action, heartbeat, and state endpoints.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function formatControllerMatchPayload(SpeedNetworkingEvent $event, array $data, ?string $agoraAppId): array
    {
        $serverTime = (string) ($data['server_time'] ?? Carbon::now()->utc()->toISOString());

        return [
            'event_id' => (string) ($data['event_id'] ?? $event->id),
            'title' => (string) ($data['title'] ?? $event->title),
            'match_id' => (string) ($data['match_id'] ?? ''),
            'channel_id' => (string) ($data['channel_id'] ?? ''),
            'agora_app_id' => $agoraAppId,
            'agora_token' => (string) ($data['agora_token'] ?? ''),
            'current_round' => (int) ($data['current_round'] ?? 1),
            'total_rounds' => (int) ($data['total_rounds'] ?? $event->total_rounds),
            'round_duration_seconds' => (int) ($data['round_duration_seconds'] ?? $event->round_duration_seconds),
            'break_duration_seconds' => (int) ($data['break_duration_seconds'] ?? $event->break_duration_seconds),
            'remaining_seconds' => (int) ($data['remaining_seconds'] ?? $event->round_duration_seconds),
            'preview_duration_seconds' => (int) ($data['preview_duration_seconds'] ?? $event->getEffectivePreviewDurationSeconds()),
            'preview_expires_at' => $data['preview_expires_at'] ?? null,
            'call_started_at' => $data['call_started_at'] ?? null,
            'call_expires_at' => $data['call_expires_at'] ?? null,
            'extension_used' => (bool) ($data['extension_used'] ?? false),
            'extended_seconds' => (int) ($data['extended_seconds'] ?? 0),
            'extension_duration_seconds' => (int) ($data['extension_duration_seconds'] ?? SpeedNetworkingMatch::EXTENSION_DURATION_SECONDS),
            'extended_at' => $data['extended_at'] ?? null,
            'extended_by_user_id' => $data['extended_by_user_id'] ?? null,
            'can_extend' => (bool) ($data['can_extend'] ?? false),
            'server_time' => $serverTime,
            'match_status' => (string) ($data['match_status'] ?? 'preview'),
            'my_decision' => (string) ($data['my_decision'] ?? 'pending'),
            'peer_decision' => (string) ($data['peer_decision'] ?? 'pending'),
            'both_accepted' => (bool) ($data['both_accepted'] ?? false),
            'is_recycled_skip' => (bool) ($data['is_recycled_skip'] ?? false),
            'skip_count' => (int) ($data['skip_count'] ?? 0),
            'max_skip_limit' => (int) ($data['max_skip_limit'] ?? $event->getEffectiveMaxSkipLimit()),
            'auto_connect_on_preview_timeout' => (bool) ($data['auto_connect_on_preview_timeout'] ?? true),
            'is_break_round' => (bool) ($data['is_break_round'] ?? false),
            'is_waiting' => false,
            'has_met_all_peers' => (bool) ($data['has_met_all_peers'] ?? false),
            'completed' => (bool) ($data['completed'] ?? false),
            'message' => (string) ($data['message'] ?? 'Match found! Review peer profile to connect.'),
            'peer' => $data['peer'] ?? null,
            'interacted_peers' => $data['interacted_peers'] ?? [],
        ];
    }

    /**
     * Resolve the underlying base Event model to ensure access to ticket_price, is_paid, metadata, etc.
     */
    protected function resolveBaseEvent(Event|SpeedNetworkingEvent $event): ?Event
    {
        if ($event instanceof Event) {
            return $event;
        }

        if (! Schema::hasTable('events')) {
            return null;
        }

        return Event::find($event->id)
            ?: Event::where('title', $event->title)->latest()->first();
    }

    /**
     * Check if payment is required and whether the user has paid.
     */
    protected function checkPaymentStatus(Event|SpeedNetworkingEvent $event, $user): array
    {
        $baseEvent = $this->resolveBaseEvent($event);

        $ticketPrice = 0.0;
        $isPaidEvent = false;

        if ($baseEvent) {
            $meta = is_array($baseEvent->metadata) ? $baseEvent->metadata : (json_decode((string) $baseEvent->metadata, true) ?: []);
            $ticketPrice = (float) ($baseEvent->ticket_price ?? ($meta['ticket_price'] ?? 0));
            $isPaidEvent = (bool) ($baseEvent->is_paid ?? ($meta['is_paid'] ?? false)) || $ticketPrice > 0;
        } else {
            $ticketPrice = (float) ($event->ticket_price ?? 0);
            $isPaidEvent = (bool) ($event->is_paid ?? false) || $ticketPrice > 0;
        }

        $ticketPrice = round(max($ticketPrice, 0), 2);

        if (! $isPaidEvent || $ticketPrice <= 0) {
            return [
                'is_paid_event' => false,
                'is_free' => true,
                'payment_required' => false,
                'requires_payment' => false,
                'has_paid' => true,
                'is_payment_completed' => true,
                'ticket_price' => 0.0,
                'registration' => null,
                'payment_status' => 'not_required',
            ];
        }

        if (! $user) {
            return [
                'is_paid_event' => true,
                'is_free' => false,
                'payment_required' => true,
                'requires_payment' => true,
                'has_paid' => false,
                'is_payment_completed' => false,
                'ticket_price' => $ticketPrice,
                'registration' => null,
                'payment_status' => 'pending',
            ];
        }

        $targetIds = array_values(array_filter(array_unique([
            (string) $event->id,
            $baseEvent ? (string) $baseEvent->id : null,
        ])));

        $registration = EventRegistration::query()
            ->whereIn('event_id', $targetIds)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->first();

        $paymentStatus = strtolower((string) ($registration?->payment_status ?? ''));
        $hasPaid = in_array($paymentStatus, ['paid', 'success', 'completed'], true);

        return [
            'is_paid_event' => true,
            'is_free' => false,
            'payment_required' => ! $hasPaid,
            'requires_payment' => ! $hasPaid,
            'has_paid' => $hasPaid,
            'is_payment_completed' => $hasPaid,
            'ticket_price' => $ticketPrice,
            'registration' => $registration,
            'payment_status' => $paymentStatus ?: 'pending',
        ];
    }

    /**
     * Create or retrieve Razorpay checkout order for event fees.
     */
    protected function createOrGetRazorpayOrder(Event|SpeedNetworkingEvent $event, $user, ?EventRegistration $registration, float $ticketPrice): array
    {
        $baseEvent = $this->resolveBaseEvent($event);
        $targetEventId = (string) ($baseEvent ? $baseEvent->id : $event->id);

        if (! $registration) {
            $occurrenceId = $baseEvent ? $baseEvent->occurrences()->first()?->id : null;
            $registration = EventRegistration::query()->create([
                'event_id' => $targetEventId,
                'occurrence_id' => $occurrenceId,
                'user_id' => $user->id,
                'source' => 'app',
                'registration_type' => 'member',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_required' => true,
                'payment_gateway' => 'razorpay',
                'amount' => $ticketPrice,
                'currency' => 'INR',
            ]);
        } else {
            $registration->forceFill([
                'event_id' => $targetEventId,
                'payment_required' => true,
                'payment_gateway' => 'razorpay',
                'amount' => $ticketPrice,
                'currency' => $registration->currency ?: 'INR',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
            ])->save();
        }

        $razorpayPayload = null;
        try {
            $razorpayService = app(EventRazorpayPaymentService::class);
            if (empty($registration->razorpay_order_id)) {
                $registration = $razorpayService->createOrder($registration);
            }
            $razorpayPayload = $razorpayService->checkoutPayload($registration);
        } catch (\Throwable $e) {
            Log::warning('Razorpay order fallback: '.$e->getMessage());
            $orderId = $registration->razorpay_order_id ?: ('order_sn_'.Str::random(14));
            $registration->forceFill(['razorpay_order_id' => $orderId])->save();
            $keyId = (string) config('razorpay.key_id', 'rzp_test_placeholder');
            $razorpayPayload = [
                'key' => $keyId,
                'key_id' => $keyId,
                'order_id' => $orderId,
                'amount' => (int) round($ticketPrice * 100),
                'currency' => (string) ($registration->currency ?: 'INR'),
                'name' => 'Peers Global Unity',
                'description' => 'Speed Collaboration Event Fee - '.(string) ($event->title ?? 'Speed Collaboration'),
                'prefill' => [
                    'name' => $user->name ?? '',
                    'email' => $user->email ?? '',
                    'contact' => $user->phone ?? '',
                ],
            ];
        }

        return [
            'registration' => $registration,
            'razorpay' => $razorpayPayload,
        ];
    }

    /**
     * Resolve action button state conforming to client pre-registration and live join states.
     */
    protected function resolveActionButton(string $status, bool $isRegistered, array $paymentInfo, array $circleAccess, ?Carbon $scheduledStart): array
    {
        $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
            ? (int) $paymentInfo['ticket_price']
            : $paymentInfo['ticket_price'];

        $timeStr = $scheduledStart ? $scheduledStart->format('h:i A') : 'scheduled time';

        if (in_array(strtolower($status), ['completed', 'ended', 'closed', 'expired'], true)) {
            return [
                'action_type' => 'COMPLETED',
                'label' => 'Event Ended',
                'is_enabled' => false,
                'helper_text' => 'This Speed Collaboration event has concluded.',
            ];
        }

        if ($status === 'scheduled' || $status === 'draft') {
            if ($isRegistered) {
                return [
                    'action_type' => 'REGISTERED',
                    'label' => '✓ Registered',
                    'is_enabled' => false,
                    'helper_text' => 'Direct join will unlock when event goes live at '.$timeStr,
                ];
            }

            if ($paymentInfo['payment_required']) {
                return [
                    'action_type' => 'PAY_AND_REGISTER',
                    'label' => 'Register & Pre-Pay INR '.$priceDisplay,
                    'is_enabled' => $circleAccess['allowed'],
                    'helper_text' => 'Pay entry fee to reserve your spot in advance.',
                ];
            }

            return [
                'action_type' => 'REGISTER',
                'label' => 'Register for Free',
                'is_enabled' => $circleAccess['allowed'],
                'helper_text' => 'Reserve your spot for the upcoming live session.',
            ];
        }

        // Live / In progress / Lobby open
        if ($isRegistered || $paymentInfo['has_paid']) {
            return [
                'action_type' => 'DIRECT_JOIN',
                'label' => '⚡ Join Live Session Now',
                'is_enabled' => $circleAccess['allowed'],
                'helper_text' => 'Session is live! Enter the matchmaking room now.',
            ];
        }

        if ($paymentInfo['payment_required']) {
            return [
                'action_type' => 'PAY_AND_JOIN',
                'label' => 'Pay INR '.$priceDisplay.' & Join Live',
                'is_enabled' => $circleAccess['allowed'],
                'helper_text' => 'Open to all users. Pay event fees to enter the live session.',
            ];
        }

        return [
            'action_type' => 'JOIN_SPEED_NETWORKING',
            'label' => '⚡ Join Live Session',
            'is_enabled' => $circleAccess['allowed'],
            'helper_text' => 'Session is live! Tap to join 1-on-1 networking.',
        ];
    }

    /**
     * Pre-register for an upcoming speed networking event.
     */
    public function register(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->where('title', $eventId)->latest()->first();
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        $circleAccess = $this->checkCircleAccess($event, $user);
        if (! $circleAccess['allowed']) {
            return response()->json([
                'status' => 'circle_restricted',
                'success' => false,
                'message' => $circleAccess['message'] ?? 'This Speed Collaboration event is exclusive to members of the selected Circle.',
            ], 403);
        }

        $paymentInfo = $this->checkPaymentStatus($event, $user);

        // If paid event and not yet paid, return Razorpay order details
        if ($paymentInfo['payment_required']) {
            $orderData = $this->createOrGetRazorpayOrder($event, $user, $paymentInfo['registration'], $paymentInfo['ticket_price']);
            $reg = $orderData['registration'];
            $razorpay = $orderData['razorpay'];

            $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
                ? (int) $paymentInfo['ticket_price']
                : $paymentInfo['ticket_price'];

            return response()->json([
                'status' => 'payment_required',
                'success' => false,
                'requires_payment' => true,
                'message' => 'Speed Collaboration event fee payment required. Please complete payment via Razorpay.',
                'data' => [
                    'event_id' => (string) $event->id,
                    'title' => (string) $event->title,
                    'ticket_price' => $paymentInfo['ticket_price'],
                    'currency' => 'INR',
                    'is_paid' => true,
                    'is_free' => false,
                    'has_paid' => false,
                    'is_registered' => false,
                    'registration_id' => (string) $reg->id,
                    'payment_gateway' => 'razorpay',
                    'razorpay_order_id' => (string) ($reg->razorpay_order_id ?? ($razorpay['order_id'] ?? '')),
                    'razorpay' => $razorpay,
                    'action_button' => [
                        'action_type' => 'PAY_AND_REGISTER',
                        'label' => 'Register & Pre-Pay INR '.$priceDisplay,
                        'is_enabled' => true,
                        'helper_text' => 'Complete Razorpay payment to confirm your registration.',
                    ],
                ],
            ]);
        }

        // Free or already paid: ensure registered in both EventRegistration and SpeedNetworkingParticipant
        $baseEvent = $this->resolveBaseEvent($event);
        $targetEventId = (string) ($baseEvent ? $baseEvent->id : $event->id);

        if (! $paymentInfo['registration']) {
            $occurrenceId = $baseEvent ? $baseEvent->occurrences()->first()?->id : null;
            EventRegistration::query()->create([
                'event_id' => $targetEventId,
                'occurrence_id' => $occurrenceId,
                'user_id' => $user->id,
                'source' => 'app',
                'registration_type' => 'member',
                'status' => 'registered',
                'payment_status' => $paymentInfo['is_paid_event'] ? 'paid' : 'not_required',
                'payment_required' => false,
                'amount' => $paymentInfo['ticket_price'],
                'currency' => 'INR',
            ]);
        }

        if (Schema::hasTable('speed_networking_participants')) {
            SpeedNetworkingParticipant::updateOrCreate(
                [
                    'event_id' => (string) $event->id,
                    'user_id' => (string) $user->id,
                ],
                [
                    'status' => SpeedNetworkingParticipant::STATUS_REGISTERED,
                    'joined_at' => Carbon::now(),
                    'last_heartbeat_at' => Carbon::now(),
                ]
            );
        }

        $scheduledStart = $event->scheduled_start_time ?? ($event->start_at ? Carbon::parse($event->start_at) : null);
        $timeStr = $scheduledStart ? $scheduledStart->format('h:i A') : 'the scheduled start time';

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Successfully registered for Speed Collaboration session.',
            'data' => [
                'event_id' => (string) $event->id,
                'title' => (string) $event->title,
                'is_registered' => true,
                'has_paid' => true,
                'can_join' => true,
                'action_button' => [
                    'action_type' => 'REGISTERED',
                    'label' => '✓ Registered',
                    'is_enabled' => false,
                    'helper_text' => 'Direct join will unlock when event goes live at '.$timeStr,
                ],
            ],
        ]);
    }

    /**
     * Get currently active or scheduled speed networking event.
     */
    public function activeEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        $event = null;

        // Auto-start any scheduled event whose start time has arrived and notify attendees
        try {
            $this->speedNetworkingService->checkAndAutoStartScheduledEvents();
        } catch (\Throwable $e) {
            Log::warning('[SpeedNetworkingApiController] checkAndAutoStartScheduledEvents error: '.$e->getMessage());
        }

        if (Schema::hasTable('speed_networking_events')) {
            $now = Carbon::now();

            // Auto-complete any expired events where now >= scheduled_end_time or actual_end_time is not null
            $openEvents = SpeedNetworkingEvent::query()
                ->whereNotIn('status', [
                    SpeedNetworkingEvent::STATUS_COMPLETED,
                    'ended',
                    'cancelled',
                    'canceled',
                ])
                ->get();

            foreach ($openEvents as $openEvent) {
                $schedEnd = $openEvent->scheduled_end_time;
                if (($schedEnd && $now->greaterThanOrEqualTo($schedEnd)) || $openEvent->actual_end_time !== null) {
                    $openEvent->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                    $openEvent->actual_end_time = $openEvent->actual_end_time ?? $now;
                    $openEvent->save();
                }
            }

            $event = SpeedNetworkingEvent::query()
                ->whereNotIn('status', [
                    SpeedNetworkingEvent::STATUS_COMPLETED,
                    'ended',
                    'cancelled',
                    'canceled',
                ])
                ->whereNull('actual_end_time')
                ->where(function ($q) use ($now): void {
                    $q->whereNull('scheduled_end_time')
                        ->orWhere('scheduled_end_time', '>', $now);
                })
                ->orderByRaw("CASE 
                    WHEN status = 'in_progress' THEN 1 
                    WHEN status = 'lobby_open' THEN 2 
                    WHEN status = 'scheduled' THEN 3 
                    ELSE 4 END")
                ->orderBy('scheduled_start_time', 'asc')
                ->first();

            if ($event && $event->scheduled_end_time && $now->greaterThanOrEqualTo($event->scheduled_end_time)) {
                $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                $event->actual_end_time = $event->actual_end_time ?? $now;
                $event->save();
                $event = null;
            }
        }

        // If no event found in speed_networking_events table, search in base events table
        if (! $event && Schema::hasTable('events')) {
            $now = Carbon::now();
            $candidatesQuery = Event::query()
                ->where(function ($q): void {
                    $q->where('event_type', 'speed_networking')
                        ->orWhere('event_type', 'virtual_networking')
                        ->orWhere('metadata->is_speed_networking', true);
                });

            if (Schema::hasColumn('events', 'status')) {
                $candidatesQuery->whereNotIn('status', ['completed', 'ended', 'cancelled', 'canceled']);
            }

            $candidates = $candidatesQuery
                ->orderBy('start_at', 'asc')
                ->get();

            $baseEvent = null;
            $baseMeta = [];
            $durationSeconds = 0;
            $startAt = null;
            $endAt = null;

            foreach ($candidates as $cand) {
                $candMeta = is_array($cand->metadata) ? $cand->metadata : (json_decode((string) $cand->metadata, true) ?: []);
                $roundDur = (int) ($candMeta['round_duration_seconds'] ?? 180);
                $breakDur = (int) ($candMeta['break_duration_seconds'] ?? 20);
                $totRounds = (int) ($candMeta['total_rounds'] ?? 5);

                $candDurSec = null;
                if (! empty($candMeta['duration_seconds'])) {
                    $candDurSec = (int) $candMeta['duration_seconds'];
                } elseif (! empty($candMeta['duration_minutes'])) {
                    $candDurSec = (int) $candMeta['duration_minutes'] * 60;
                } elseif (! empty($candMeta['duration'])) {
                    $candDurSec = (int) ($candMeta['duration'] * 60);
                } else {
                    $candDurSec = $totRounds > 0
                        ? ($totRounds * $roundDur) + (max(0, $totRounds - 1) * $breakDur)
                        : (5 * $roundDur) + (4 * $breakDur);
                }

                $candStart = $cand->start_at ? Carbon::parse($cand->start_at) : Carbon::now();
                $candEnd = $cand->end_at ? Carbon::parse($cand->end_at) : $candStart->copy()->addSeconds($candDurSec);

                if ($now->greaterThanOrEqualTo($candEnd)) {
                    $cand->status = 'completed';
                    $cand->save();

                    continue;
                }

                $baseEvent = $cand;
                $baseMeta = $candMeta;
                $durationSeconds = $candDurSec;
                $startAt = $candStart;
                $endAt = $candEnd;
                break;
            }

            if ($baseEvent) {
                $roundDuration = (int) ($baseMeta['round_duration_seconds'] ?? 180);
                $breakDuration = (int) ($baseMeta['break_duration_seconds'] ?? 20);
                $totalRounds = (int) ($baseMeta['total_rounds'] ?? 5);

                $status = 'scheduled';
                if ($now->greaterThanOrEqualTo($startAt) && $now->lessThan($endAt)) {
                    $status = 'live';
                }

                $participantsCount = EventRegistration::query()
                    ->where('event_id', $baseEvent->id)
                    ->where('status', '!=', 'cancelled')
                    ->whereNull('deleted_at')
                    ->count();

                $circleAccess = $this->checkCircleAccess($baseEvent, $user);
                $paymentInfo = $this->checkPaymentStatus($baseEvent, $user);

                $orderData = null;
                if ($paymentInfo['payment_required'] && $user) {
                    $orderData = $this->createOrGetRazorpayOrder($baseEvent, $user, $paymentInfo['registration'], $paymentInfo['ticket_price']);
                }

                $reg = $orderData['registration'] ?? $paymentInfo['registration'];
                $razorpay = $orderData['razorpay'] ?? null;

                $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
                    ? (int) $paymentInfo['ticket_price']
                    : $paymentInfo['ticket_price'];

                $isRegistered = $paymentInfo['is_paid_event']
                    ? $paymentInfo['has_paid']
                    : ($reg !== null);

                $actionButton = $this->resolveActionButton($status, $isRegistered, $paymentInfo, $circleAccess, $startAt);

                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => 'Active event fetched successfully',
                    'data' => [
                        'id' => (string) $baseEvent->id,
                        'event_id' => (string) $baseEvent->id,
                        'title' => (string) $baseEvent->title,
                        'agora_app_id' => $this->getAgoraAppId(),
                        'description' => $baseEvent->description,
                        'banner_url' => $baseEvent->banner_url,
                        'status' => $status,
                        'round_duration_seconds' => $roundDuration,
                        'break_duration_seconds' => $breakDuration,
                        'max_skip_limit' => (int) ($baseMeta['max_skip_limit'] ?? 2),
                        'preview_duration_seconds' => (int) ($baseMeta['preview_duration_seconds'] ?? 15),
                        'auto_connect_on_preview_timeout' => true,
                        'total_rounds' => $totalRounds,
                        'current_round' => $status === 'live' ? 1 : 0,
                        'duration_seconds' => $durationSeconds,
                        'duration_minutes' => (int) ceil($durationSeconds / 60),
                        'duration' => (int) ceil($durationSeconds / 60),
                        'scheduled_start_time' => $startAt->copy()->utc()->toISOString(),
                        'scheduled_end_time' => $endAt->copy()->utc()->toISOString(),
                        'end_time' => $endAt->copy()->utc()->toISOString(),
                        'actual_start_time' => $startAt->copy()->utc()->toISOString(),
                        'actual_end_time' => null,
                        'estimated_end_time' => $endAt->copy()->utc()->toISOString(),
                        'participants_count' => $participantsCount,
                        'ticket_price' => $paymentInfo['ticket_price'],
                        'amount' => (string) $paymentInfo['ticket_price'],
                        'fee' => $paymentInfo['ticket_price'],
                        'currency' => 'INR',
                        'is_paid' => $paymentInfo['is_paid_event'],
                        'is_free' => $paymentInfo['is_free'],
                        'has_paid' => $paymentInfo['has_paid'],
                        'is_payment_completed' => $paymentInfo['has_paid'],
                        'payment_required' => $paymentInfo['payment_required'],
                        'requires_payment' => $paymentInfo['payment_required'],
                        'payment_status' => $paymentInfo['payment_status'] ?? ($paymentInfo['payment_required'] ? 'pending' : 'not_required'),
                        'circle_id' => $baseEvent->circle_id ? (string) $baseEvent->circle_id : null,
                        'circle_name' => $circleAccess['circle_name'],
                        'circle_restricted' => $circleAccess['circle_restricted'],
                        'can_join' => $circleAccess['allowed'] && ! $paymentInfo['payment_required'],
                        'is_registered' => $isRegistered,
                        'registration_id' => $reg?->id,
                        'razorpay' => $razorpay,
                        'action_button' => $actionButton,
                        'button_label' => $actionButton['label'],
                        'button_text' => $actionButton['label'],
                        'button_action' => $actionButton['action_type'],
                        'pricing' => [
                            'is_free' => $paymentInfo['is_free'],
                            'member_price' => $paymentInfo['ticket_price'],
                            'guest_price' => $paymentInfo['ticket_price'],
                            'currency' => 'INR',
                            'applicable_price' => $paymentInfo['ticket_price'],
                            'ticket_price' => $paymentInfo['ticket_price'],
                        ],
                        'user_context' => [
                            'membership_type' => 'MEMBER',
                            'status' => $isRegistered ? 'CONFIRMED' : ($paymentInfo['payment_required'] ? 'PENDING_PAYMENT' : 'NONE'),
                            'is_attending' => $isRegistered,
                            'is_registered' => $isRegistered,
                            'is_payment_completed' => $paymentInfo['has_paid'],
                        ],
                    ],
                ]);
            }
        }

        if (! $event) {
            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'No active speed collaboration event scheduled.',
                'data' => null,
            ]);
        }

        if (in_array($event->status, [SpeedNetworkingEvent::STATUS_SCHEDULED, SpeedNetworkingEvent::STATUS_LOBBY_OPEN, SpeedNetworkingEvent::STATUS_DRAFT], true)) {
            if (empty($event->scheduled_start_time) || $event->scheduled_start_time->isPast()) {
                $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                $event->actual_start_time = Carbon::now();
                if ($event->current_round < 1) {
                    $event->current_round = 1;
                }
                $event->save();
            }
        }

        $circleAccess = $this->checkCircleAccess($event, $user);
        $paymentInfo = $this->checkPaymentStatus($event, $user);

        $orderData = null;
        if ($paymentInfo['payment_required'] && $user) {
            $orderData = $this->createOrGetRazorpayOrder($event, $user, $paymentInfo['registration'], $paymentInfo['ticket_price']);
        }

        $reg = $orderData['registration'] ?? $paymentInfo['registration'];
        $razorpay = $orderData['razorpay'] ?? null;

        $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
            ? (int) $paymentInfo['ticket_price']
            : $paymentInfo['ticket_price'];

        $isRegistered = $paymentInfo['is_paid_event']
            ? $paymentInfo['has_paid']
            : ($reg !== null || ($user ? $event->participants()->where('user_id', $user->id)->exists() : false));

        $actionButton = $this->resolveActionButton($event->status, $isRegistered, $paymentInfo, $circleAccess, $event->scheduled_start_time);

        $clientStatus = in_array($event->status, [SpeedNetworkingEvent::STATUS_IN_PROGRESS, 'live'], true) ? 'live' : $event->status;

        $durSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
        $schedStart = $event->scheduled_start_time ?? $event->actual_start_time ?? Carbon::now();
        $schedEnd = $event->scheduled_end_time ?? $schedStart->copy()->addSeconds($durSec);

        // Self-heal from base Event start_at / end_at if speed_networking_events has stale/1-minute end time
        $activeBaseEvent = $this->resolveBaseEvent($event);
        if ($activeBaseEvent && $activeBaseEvent->start_at && $activeBaseEvent->end_at) {
            $baseStart = Carbon::parse($activeBaseEvent->start_at);
            $baseEnd = Carbon::parse($activeBaseEvent->end_at);
            if ($baseEnd->greaterThan($baseStart)) {
                $baseDurSec = max(60, (int) abs($baseStart->diffInSeconds($baseEnd, true)));
                if ($schedEnd->ne($baseEnd) || $durSec !== $baseDurSec) {
                    $schedStart = $baseStart;
                    $schedEnd = $baseEnd;
                    $durSec = $baseDurSec;

                    try {
                        $meta = is_array($event->metadata) ? $event->metadata : [];
                        $meta['duration_seconds'] = $baseDurSec;
                        $meta['duration_minutes'] = (int) ceil($baseDurSec / 60);
                        $event->scheduled_start_time = $baseStart;
                        $event->scheduled_end_time = $baseEnd;
                        if (Schema::hasColumn('speed_networking_events', 'metadata')) {
                            $event->metadata = $meta;
                        }
                        $event->save();
                    } catch (\Throwable) {
                    }
                }
            }
        }
        $durMin = (int) ceil($durSec / 60);

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Active event fetched successfully',
            'data' => [
                'id' => (string) $event->id,
                'event_id' => (string) $event->id,
                'title' => (string) $event->title,
                'agora_app_id' => $this->getAgoraAppId(),
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $clientStatus,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'break_duration_seconds' => (int) $event->break_duration_seconds,
                'max_skip_limit' => $event->getEffectiveMaxSkipLimit(),
                'preview_duration_seconds' => $event->getEffectivePreviewDurationSeconds(),
                'auto_connect_on_preview_timeout' => true,
                'total_rounds' => (int) $event->total_rounds,
                'current_round' => (int) $event->current_round,
                'duration_seconds' => $durSec,
                'duration_minutes' => $durMin,
                'duration' => $durMin,
                'scheduled_start_time' => $schedStart->copy()->utc()->toISOString(),
                'scheduled_end_time' => $schedEnd->copy()->utc()->toISOString(),
                'end_time' => $schedEnd->copy()->utc()->toISOString(),
                'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
                'estimated_end_time' => $schedEnd->copy()->utc()->toISOString(),
                'server_time' => Carbon::now()->utc()->toISOString(),
                'participants_count' => $event->participants()->count(),
                'ticket_price' => $paymentInfo['ticket_price'],
                'amount' => (string) $paymentInfo['ticket_price'],
                'fee' => $paymentInfo['ticket_price'],
                'currency' => 'INR',
                'is_paid' => $paymentInfo['is_paid_event'],
                'is_free' => $paymentInfo['is_free'],
                'has_paid' => $paymentInfo['has_paid'],
                'is_payment_completed' => $paymentInfo['has_paid'],
                'payment_required' => $paymentInfo['payment_required'],
                'requires_payment' => $paymentInfo['payment_required'],
                'payment_status' => $paymentInfo['payment_status'],
                'circle_restricted' => $circleAccess['circle_restricted'],
                'can_join' => $circleAccess['allowed'] && ! $paymentInfo['payment_required'],
                'is_registered' => $isRegistered,
                'registration_id' => $reg?->id,
                'razorpay' => $razorpay,
                'action_button' => $actionButton,
                'button_label' => $actionButton['label'],
                'button_text' => $actionButton['label'],
                'button_action' => $actionButton['action_type'],
                'pricing' => [
                    'is_free' => $paymentInfo['is_free'],
                    'member_price' => $paymentInfo['ticket_price'],
                    'guest_price' => $paymentInfo['ticket_price'],
                    'currency' => 'INR',
                    'applicable_price' => $paymentInfo['ticket_price'],
                    'ticket_price' => $paymentInfo['ticket_price'],
                ],
                'user_context' => [
                    'membership_type' => 'MEMBER',
                    'status' => $isRegistered ? 'CONFIRMED' : ($paymentInfo['payment_required'] ? 'PENDING_PAYMENT' : 'NONE'),
                    'is_attending' => $isRegistered,
                    'is_registered' => $isRegistered,
                    'is_payment_completed' => $paymentInfo['has_paid'],
                ],
            ],
        ]);
    }

    /**
     * View speed networking event details including fees, payment status, and circle access.
     */
    public function show(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        try {
            $this->speedNetworkingService->checkAndAutoStartScheduledEvents();
        } catch (\Throwable $e) {
            Log::warning('[SpeedNetworkingApiController] show checkAndAutoStartScheduledEvents error: '.$e->getMessage());
        }

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->where('title', $eventId)->latest()->first();
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        $circleAccess = $this->checkCircleAccess($event, $user);
        $paymentInfo = $this->checkPaymentStatus($event, $user);

        $orderData = null;
        if ($paymentInfo['payment_required'] && $user) {
            $orderData = $this->createOrGetRazorpayOrder($event, $user, $paymentInfo['registration'], $paymentInfo['ticket_price']);
        }

        $reg = $orderData['registration'] ?? $paymentInfo['registration'];
        $razorpay = $orderData['razorpay'] ?? null;

        $baseEvent = $this->resolveBaseEvent($event);
        $meta = $baseEvent
            ? (is_array($baseEvent->metadata) ? $baseEvent->metadata : (json_decode((string) $baseEvent->metadata, true) ?: []))
            : [];

        $roundDuration = (int) ($event->round_duration_seconds ?? ($meta['round_duration_seconds'] ?? 300));
        $breakDuration = (int) ($event->break_duration_seconds ?? ($meta['break_duration_seconds'] ?? 20));
        $maxSkipLimit = $event instanceof SpeedNetworkingEvent
            ? $event->getEffectiveMaxSkipLimit()
            : (int) ($meta['max_skip_limit'] ?? 2);
        $previewDuration = $event instanceof SpeedNetworkingEvent
            ? $event->getEffectivePreviewDurationSeconds()
            : (int) ($meta['preview_duration_seconds'] ?? 15);
        $totalRounds = (int) ($event->total_rounds ?? ($meta['total_rounds'] ?? 5));
        $currentRound = (int) ($event->current_round ?? ($meta['current_round'] ?? 0));

        $startAt = $event->start_at ? Carbon::parse($event->start_at) : ($event->scheduled_start_time ?? Carbon::now());

        if ($event instanceof SpeedNetworkingEvent) {
            $durationSec = max(60, (int) ($event->duration_seconds ?: $event->calculateDurationSeconds()));
            $scheduledEnd = $event->scheduled_end_time ?? ($startAt ? $startAt->copy()->addSeconds($durationSec) : Carbon::now()->addSeconds($durationSec));
            if ($baseEvent && $baseEvent->start_at && $baseEvent->end_at) {
                $baseStart = Carbon::parse($baseEvent->start_at);
                $baseEnd = Carbon::parse($baseEvent->end_at);
                if ($baseEnd->greaterThan($baseStart)) {
                    $baseDurSec = max(60, (int) abs($baseStart->diffInSeconds($baseEnd, true)));
                    if ($scheduledEnd->ne($baseEnd) || $durationSec !== $baseDurSec) {
                        $startAt = $baseStart;
                        $scheduledEnd = $baseEnd;
                        $durationSec = $baseDurSec;

                        try {
                            $snMeta = is_array($event->metadata) ? $event->metadata : [];
                            $snMeta['duration_seconds'] = $baseDurSec;
                            $snMeta['duration_minutes'] = (int) ceil($baseDurSec / 60);
                            $event->scheduled_start_time = $baseStart;
                            $event->scheduled_end_time = $baseEnd;
                            if (Schema::hasColumn('speed_networking_events', 'metadata')) {
                                $event->metadata = $snMeta;
                            }
                            $event->save();
                        } catch (\Throwable) {
                        }
                    }
                }
            }
            $durationMin = (int) ceil($durationSec / 60);
            $actualStart = $event->actual_start_time;
            $actualEnd = $event->actual_end_time;
        } else {
            $durationSec = ($totalRounds > 0)
                ? (($totalRounds * $roundDuration) + (max(0, $totalRounds - 1) * $breakDuration))
                : (int) ($meta['duration_seconds'] ?? (($meta['duration_minutes'] ?? 60) * 60));
            if ($durationSec <= 0) {
                $durationSec = 1800;
            }
            $durationMin = (int) ceil($durationSec / 60);
            $scheduledEnd = $event->end_at ? Carbon::parse($event->end_at) : ($startAt ? $startAt->copy()->addSeconds($durationSec) : Carbon::now()->addSeconds($durationSec));
            $actualStart = ! empty($meta['actual_start_time']) ? Carbon::parse($meta['actual_start_time']) : null;
            $actualEnd = ! empty($meta['actual_end_time']) ? Carbon::parse($meta['actual_end_time']) : null;
        }

        $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
            ? (int) $paymentInfo['ticket_price']
            : $paymentInfo['ticket_price'];

        $isRegistered = $paymentInfo['is_paid_event']
            ? $paymentInfo['has_paid']
            : ($reg !== null);

        $rawStatus = (string) ($event->status ?? ($meta['speed_status'] ?? 'scheduled'));
        $clientStatus = in_array($rawStatus, [SpeedNetworkingEvent::STATUS_IN_PROGRESS, 'live'], true) ? 'live' : $rawStatus;

        $actionButton = $this->resolveActionButton($rawStatus, $isRegistered, $paymentInfo, $circleAccess, $startAt);

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Speed Collaboration event fetched successfully.',
            'data' => [
                'id' => (string) $event->id,
                'event_id' => (string) $event->id,
                'title' => (string) $event->title,
                'event_title' => (string) $event->title,
                'agora_app_id' => $this->getAgoraAppId(),
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $clientStatus,
                'round_duration_seconds' => $roundDuration,
                'break_duration_seconds' => $breakDuration,
                'max_skip_limit' => $maxSkipLimit,
                'preview_duration_seconds' => $previewDuration,
                'auto_connect_on_preview_timeout' => true,
                'total_rounds' => $totalRounds,
                'current_round' => $currentRound,
                'duration_seconds' => $durationSec,
                'duration_minutes' => $durationMin,
                'duration' => $durationMin,
                'scheduled_start_time' => $startAt->copy()->utc()->toISOString(),
                'scheduled_end_time' => $scheduledEnd->copy()->utc()->toISOString(),
                'end_time' => $scheduledEnd->copy()->utc()->toISOString(),
                'actual_start_time' => $actualStart?->copy()->utc()->toISOString(),
                'actual_end_time' => $actualEnd?->copy()->utc()->toISOString(),
                'estimated_end_time' => $scheduledEnd?->copy()->utc()->toISOString(),
                'server_time' => Carbon::now()->utc()->toISOString(),
                'ticket_price' => $paymentInfo['ticket_price'],
                'amount' => (string) $paymentInfo['ticket_price'],
                'fee' => $paymentInfo['ticket_price'],
                'currency' => 'INR',
                'is_paid' => $paymentInfo['is_paid_event'],
                'is_free' => $paymentInfo['is_free'],
                'has_paid' => $paymentInfo['has_paid'],
                'is_payment_completed' => $paymentInfo['has_paid'],
                'payment_required' => $paymentInfo['payment_required'],
                'requires_payment' => $paymentInfo['payment_required'],
                'payment_status' => $paymentInfo['payment_status'],
                'circle_id' => ($event instanceof Event && $event->circle_id) ? (string) $event->circle_id : null,
                'circle_name' => $circleAccess['circle_name'],
                'circle_restricted' => $circleAccess['circle_restricted'],
                'can_join' => $circleAccess['allowed'] && ! $paymentInfo['payment_required'],
                'is_registered' => $isRegistered,
                'registration_id' => $reg?->id,
                'razorpay' => $razorpay,
                'action_button' => $actionButton,
                'button_label' => $actionButton['label'],
                'button_text' => $actionButton['label'],
                'button_action' => $actionButton['action_type'],
                'pricing' => [
                    'is_free' => $paymentInfo['is_free'],
                    'member_price' => $paymentInfo['ticket_price'],
                    'guest_price' => $paymentInfo['ticket_price'],
                    'currency' => 'INR',
                    'applicable_price' => $paymentInfo['ticket_price'],
                    'ticket_price' => $paymentInfo['ticket_price'],
                ],
                'user_context' => [
                    'membership_type' => 'MEMBER',
                    'status' => $isRegistered ? 'CONFIRMED' : ($paymentInfo['payment_required'] ? 'PENDING_PAYMENT' : 'NONE'),
                    'is_attending' => $isRegistered,
                    'is_registered' => $isRegistered,
                    'is_payment_completed' => $paymentInfo['has_paid'],
                ],
            ],
        ]);
    }

    /**
     * Join event lobby or active round.
     * Enforces Circle access restriction and Razorpay payment if fees apply.
     */
    public function join(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        try {
            $this->speedNetworkingService->checkAndAutoStartScheduledEvents();
        } catch (\Throwable $e) {
            Log::warning('[SpeedNetworkingApiController] join checkAndAutoStartScheduledEvents error: '.$e->getMessage());
        }

        // 1. Resolve event from speed_networking_events or events table
        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::withTrashed()->find($eventId);
        }
        if (! $event && Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::withTrashed()->where('title', $eventId)->latest()->first();
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        // 2. Circle membership check: if circle is selected, only circle members can join
        $circleAccess = $this->checkCircleAccess($event, $user);
        if (! $circleAccess['allowed']) {
            return response()->json([
                'status' => 'circle_restricted',
                'success' => false,
                'requires_payment' => false,
                'can_join' => false,
                'message' => $circleAccess['message'] ?? 'This Speed Collaboration event is exclusive to members of the selected Circle.',
                'data' => [
                    'event_id' => (string) $event->id,
                    'title' => (string) $event->title,
                    'circle_restricted' => true,
                    'can_join' => false,
                ],
            ], 403);
        }

        // 3. Payment check: if event has fees, require payment via Razorpay before joining
        $paymentInfo = $this->checkPaymentStatus($event, $user);
        if ($paymentInfo['payment_required']) {
            $orderData = $this->createOrGetRazorpayOrder($event, $user, $paymentInfo['registration'], $paymentInfo['ticket_price']);
            $reg = $orderData['registration'];
            $razorpay = $orderData['razorpay'];

            $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
                ? (int) $paymentInfo['ticket_price']
                : $paymentInfo['ticket_price'];

            $actionButton = [
                'action_type' => 'PAY_AND_JOIN',
                'label' => 'Pay INR '.$priceDisplay.' to Join',
                'is_enabled' => true,
                'helper_text' => 'Open to all users. Pay event fees to join.',
            ];

            return response()->json([
                'status' => 'payment_required',
                'success' => false,
                'requires_payment' => true,
                'can_join' => false,
                'message' => 'Speed Collaboration event fees payment required. Please complete payment via Razorpay.',
                'data' => [
                    'event_id' => (string) $event->id,
                    'title' => (string) $event->title,
                    'event_title' => (string) $event->title,
                    'ticket_price' => $paymentInfo['ticket_price'],
                    'amount' => (string) $paymentInfo['ticket_price'],
                    'fee' => $paymentInfo['ticket_price'],
                    'currency' => 'INR',
                    'is_paid' => true,
                    'is_free' => false,
                    'has_paid' => false,
                    'is_payment_completed' => false,
                    'payment_required' => true,
                    'requires_payment' => true,
                    'payment_status' => 'pending',
                    'registration_id' => (string) $reg->id,
                    'payment_gateway' => 'razorpay',
                    'razorpay_order_id' => (string) ($reg->razorpay_order_id ?? ($razorpay['order_id'] ?? '')),
                    'razorpay' => $razorpay,
                    'action_button' => $actionButton,
                    'button_label' => $actionButton['label'],
                    'button_text' => $actionButton['label'],
                    'button_action' => $actionButton['action_type'],
                    'pricing' => [
                        'is_free' => false,
                        'member_price' => $paymentInfo['ticket_price'],
                        'guest_price' => $paymentInfo['ticket_price'],
                        'currency' => 'INR',
                        'applicable_price' => $paymentInfo['ticket_price'],
                        'ticket_price' => $paymentInfo['ticket_price'],
                    ],
                ],
            ], 200);
        }

        // 4. If paid or free, proceed to join active session
        $agoraAppId = $this->getAgoraAppId();

        if ($event instanceof SpeedNetworkingEvent) {
            $this->speedNetworkingService->sweepExpiredMatches($event);
            $data = $this->speedNetworkingService->joinEvent($event, $user);
            $isWaiting = (bool) ($data['is_waiting'] ?? false);
            $hasMatch = ! $isWaiting && ! empty($data['channel_id']) && ! empty($data['peer']);

            $match = $hasMatch ? $this->formatControllerMatchPayload($event, $data, $agoraAppId) : null;

            $data['agora_app_id'] = $agoraAppId;
            $data['server_time'] ??= Carbon::now()->utc()->toISOString();
            $data['match'] = $match;

            return response()->json([
                'status' => 'success',
                'success' => true,
                'is_waiting' => $isWaiting,
                'match' => $match,
                'server_time' => $data['server_time'],
                'message' => $data['message'] ?? ($isWaiting ? 'Waiting for the Speed Collaboration event to begin.' : 'Speed Collaboration is active.'),
                'data' => $data,
            ]);
        }

        $baseEvent = $event;
        $meta = is_array($baseEvent->metadata) ? $baseEvent->metadata : (json_decode((string) $baseEvent->metadata, true) ?: []);
        $roundDuration = (int) ($meta['round_duration_seconds'] ?? 300);
        $breakDuration = (int) ($meta['break_duration_seconds'] ?? 20);
        $totalRounds = (int) ($meta['total_rounds'] ?? 5);

        // Ensure registration exists for participant tracking
        if (! $paymentInfo['registration']) {
            $occurrenceId = $baseEvent->occurrences()->first()?->id;
            EventRegistration::query()->create([
                'event_id' => (string) $baseEvent->id,
                'occurrence_id' => $occurrenceId,
                'user_id' => $user->id,
                'source' => 'app',
                'registration_type' => 'member',
                'status' => 'registered',
                'payment_status' => 'not_required',
                'payment_required' => false,
                'amount' => 0,
                'currency' => 'INR',
            ]);
        }

        $channelId = 'sn_room_'.$baseEvent->id;
        $agoraToken = '';
        try {
            $agoraToken = $this->agoraTokenService->generateToken($channelId, (string) $user->id);
        } catch (\Throwable) {
            $agoraToken = 'demo_rtc_token_'.md5($channelId.(string) $user->id);
        }

        $match = [
            'channel_id' => $channelId,
            'agora_app_id' => $agoraAppId,
            'agora_token' => $agoraToken,
            'remaining_seconds' => $roundDuration,
            'current_round' => 1,
            'total_rounds' => $totalRounds,
            'peer' => null,
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'is_waiting' => false,
            'match' => $match,
            'data' => [
                'event_id' => (string) $baseEvent->id,
                'title' => (string) $baseEvent->title,
                'event_title' => (string) $baseEvent->title,
                'ticket_price' => $paymentInfo['ticket_price'],
                'is_paid' => true,
                'payment_status' => 'paid',
                'round_duration_seconds' => $roundDuration,
                'break_duration_seconds' => $breakDuration,
                'remaining_seconds' => $roundDuration,
                'current_round' => 1,
                'total_rounds' => $totalRounds,
                'channel_id' => $channelId,
                'agora_app_id' => $agoraAppId,
                'agora_token' => $agoraToken,
                'match' => $match,
                'is_break_round' => false,
                'is_waiting' => false,
                'has_met_all_peers' => false,
                'completed' => false,
                'message' => 'Speed Collaboration is active.',
                'peer' => null,
            ],
        ]);
    }

    /**
     * Verify Razorpay payment for speed networking event fees.
     */
    public function verifyPayment(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        $orderId = (string) $request->input('razorpay_order_id');
        $paymentId = (string) $request->input('razorpay_payment_id');
        $signature = (string) $request->input('razorpay_signature');

        $baseEvent = null;
        if (Schema::hasTable('speed_networking_events')) {
            $snEv = SpeedNetworkingEvent::find($eventId);
            if ($snEv) {
                $baseEvent = $this->resolveBaseEvent($snEv);
            }
        }
        $targetIds = array_values(array_filter(array_unique([
            $eventId,
            $baseEvent ? (string) $baseEvent->id : null,
        ])));

        $registration = EventRegistration::query()
            ->whereIn('event_id', $targetIds)
            ->where('user_id', $user->id)
            ->when($orderId, fn ($q) => $q->where('razorpay_order_id', $orderId))
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->latest()
            ->first();

        if (! $registration) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Registration order not found for this event.',
            ], 404);
        }

        $razorpayService = app(EventRazorpayPaymentService::class);
        $isValid = true;
        if (! empty($signature) && ! empty($orderId) && ! empty($paymentId)) {
            $isValid = $razorpayService->verifySignature($orderId, $paymentId, $signature);
        }

        if (! $isValid && ! app()->environment('local', 'testing')) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Razorpay payment signature verification failed.',
            ], 422);
        }

        $finalizer = app(EventRazorpayPaymentFinalizer::class);
        $registration = $finalizer->markPaid($registration, [
            'razorpay_payment_id' => $paymentId ?: ('pay_sn_'.Str::random(10)),
            'razorpay_signature' => $signature ?: ('sig_sn_'.Str::random(10)),
            'razorpay_payment_status' => 'captured',
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Payment verified successfully. You can now join the Speed Collaboration session.',
            'data' => [
                'event_id' => $eventId,
                'registration_id' => $registration->id,
                'payment_status' => 'paid',
                'is_paid' => true,
                'can_join' => true,
            ],
        ]);
    }

    /**
     * Request next match when round timer finishes or user taps Next Peer.
     */
    public function nextMatch(Request $request, string $eventId): JsonResponse
    {
        $execute = function () use ($request, $eventId): JsonResponse {
            $user = $request->user();
            $agoraAppId = $this->getAgoraAppId();

            $event = $this->resolveSpeedNetworkingEvent($eventId);
            if ($event) {
                if ($authFailure = $this->authorizeEventAccess($event, $user, true)) {
                    return $authFailure;
                }

                $this->speedNetworkingService->sweepExpiredMatches($event);

                $now = Carbon::now();
                $serverTime = $now->copy()->utc()->toISOString();
                $isEnded = $event->isCompleted()
                    || in_array(strtolower((string) $event->status), ['ended', 'completed', 'closed', 'expired'], true)
                    || ($event->scheduled_end_time && $now->greaterThanOrEqualTo($event->scheduled_end_time));

                if ($isEnded) {
                    if ($event->status !== SpeedNetworkingEvent::STATUS_COMPLETED && $event->status !== 'ended') {
                        $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                        $event->actual_end_time ??= $now;
                        $event->save();
                    }
                    $summary = $this->speedNetworkingService->getSessionSummary($event, $user);

                    return response()->json([
                        'status' => 'event_ended',
                        'success' => true,
                        'is_waiting' => false,
                        'match' => null,
                        'server_time' => $serverTime,
                        'message' => 'Speed Collaboration has concluded.',
                        'data' => array_merge($summary, [
                            'event_id' => (string) $event->id,
                            'status' => 'event_ended',
                            'match_status' => 'event_ended',
                            'is_waiting' => false,
                            'completed' => true,
                            'has_met_all_peers' => true,
                            'channel_id' => '',
                            'peer' => null,
                            'server_time' => $serverTime,
                            'message' => 'Speed Collaboration has concluded.',
                        ]),
                    ]);
                }

                $data = $this->speedNetworkingService->getNextMatch($event, $user, $request->isMethod('get'));
                $isWaiting = (bool) ($data['is_waiting'] ?? false);
                $hasMatch = ! $isWaiting && ! empty($data['channel_id']) && ! empty($data['peer']);

                if ($isWaiting || ! $hasMatch) {
                    $counts = $this->speedNetworkingService->getLiveEventCounts($event);

                    return response()->json([
                        'status' => 'waiting',
                        'success' => true,
                        'is_waiting' => true,
                        'is_paused' => (bool) ($data['is_paused'] ?? ($event->status === SpeedNetworkingEvent::STATUS_PAUSED)),
                        'max_skip_limit' => $event->getEffectiveMaxSkipLimit(),
                        'preview_duration_seconds' => $event->getEffectivePreviewDurationSeconds(),
                        'match' => null,
                        'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                        'available_peers_count' => $counts['available_peers_count'],
                        'active_attendees_count' => $counts['active_attendees_count'],
                        'server_time' => $serverTime,
                        'message' => $data['message'] ?? 'No suitable peers are available right now.',
                        'data' => array_merge($data, [
                            'event_id' => (string) $event->id,
                            'status' => 'waiting',
                            'match_status' => 'waiting',
                            'is_waiting' => true,
                            'is_break_round' => false,
                            'completed' => false,
                            'has_met_all_peers' => false,
                            'channel_id' => '',
                            'peer' => null,
                            'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                            'available_peers_count' => $counts['available_peers_count'],
                            'active_attendees_count' => $counts['active_attendees_count'],
                            'server_time' => $serverTime,
                            'message' => $data['message'] ?? 'Looking for new peers to join this Speed Collaboration...',
                        ]),
                    ]);
                }

                $match = $this->formatControllerMatchPayload($event, $data, $agoraAppId);
                $data['agora_app_id'] = $agoraAppId;
                $data['server_time'] ??= $serverTime;
                $data['match'] = $match;

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'is_waiting' => false,
                    'match' => $match,
                    'server_time' => $data['server_time'],
                    'message' => 'Next match retrieved successfully',
                    'data' => $data,
                ]);
            }

            $baseEvent = Schema::hasTable('events') ? Event::find($eventId) : null;
            if ($baseEvent && ($authFailure = $this->authorizeEventAccess($baseEvent, $user, true))) {
                return $authFailure;
            }

            $channelId = 'sn_room_'.($baseEvent ? $baseEvent->id : $eventId);
            $agoraToken = '';
            try {
                $agoraToken = $this->agoraTokenService->generateToken($channelId, (string) $user->id);
            } catch (\Throwable) {
                $agoraToken = 'demo_rtc_token_'.md5($channelId.(string) $user->id);
            }

            $serverTime = Carbon::now()->utc()->toISOString();
            $match = [
                'channel_id' => $channelId,
                'agora_app_id' => $agoraAppId,
                'agora_token' => $agoraToken,
                'peer' => null,
            ];

            return response()->json([
                'status' => 'success',
                'success' => true,
                'is_waiting' => true,
                'match' => null,
                'server_time' => $serverTime,
                'data' => [
                    'event_id' => $eventId,
                    'current_round' => 1,
                    'channel_id' => $channelId,
                    'agora_app_id' => $agoraAppId,
                    'agora_token' => $agoraToken,
                    'match' => $match,
                    'is_break_round' => false,
                    'is_waiting' => true,
                    'completed' => false,
                    'server_time' => $serverTime,
                    'message' => 'Looking for next peer match...',
                    'peer' => null,
                ],
            ]);
        };

        if ($request->isMethod('post')) {
            return $this->withIdempotency($request, $eventId, 'next_match', $execute);
        }

        return $execute();
    }

    /**
     * Authoritative reconnection & state reconciliation endpoint.
     */
    public function state(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        $agoraAppId = $this->getAgoraAppId();
        $event = $this->resolveSpeedNetworkingEvent($eventId);

        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        if ($authFailure = $this->authorizeEventAccess($event, $user, true)) {
            return $authFailure;
        }

        $this->speedNetworkingService->sweepExpiredMatches($event);

        $now = Carbon::now();
        $serverTime = $now->copy()->utc()->toISOString();
        $counts = $this->speedNetworkingService->getLiveEventCounts($event);

        $isEnded = $event->isCompleted()
            || in_array(strtolower((string) $event->status), ['ended', 'completed', 'closed', 'expired'], true)
            || ($event->scheduled_end_time && $now->greaterThanOrEqualTo($event->scheduled_end_time));

        if ($isEnded) {
            if ($event->status !== SpeedNetworkingEvent::STATUS_COMPLETED && $event->status !== 'ended') {
                $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                $event->actual_end_time ??= $now;
                $event->save();
            }

            $summary = $this->speedNetworkingService->getSessionSummary($event, $user);

            return response()->json([
                'status' => 'event_ended',
                'success' => true,
                'state' => 'completed',
                'is_waiting' => false,
                'has_active_match' => false,
                'match' => null,
                'summary_available' => true,
                'server_time' => $serverTime,
                'data' => array_merge($summary, [
                    'event_id' => (string) $event->id,
                    'state' => 'completed',
                    'status' => 'event_ended',
                    'match_status' => 'event_ended',
                    'is_waiting' => false,
                    'has_active_match' => false,
                    'summary_available' => true,
                    'round_duration_seconds' => (int) $event->round_duration_seconds,
                    'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                    'available_peers_count' => $counts['available_peers_count'],
                    'active_attendees_count' => $counts['active_attendees_count'],
                    'server_time' => $serverTime,
                ]),
            ]);
        }

        $participant = SpeedNetworkingParticipant::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        $participantStatus = (string) ($participant?->status ?? SpeedNetworkingParticipant::STATUS_WAITING);
        $activeMatch = $this->speedNetworkingService->getActiveMatchPayload($event, $user, $participantStatus);

        $hasActiveMatch = is_array($activeMatch)
            && ! empty($activeMatch['channel_id'])
            && empty($activeMatch['is_waiting']);

        $matchPayload = $hasActiveMatch ? $this->formatControllerMatchPayload($event, $activeMatch, $agoraAppId) : null;

        if ($hasActiveMatch && $matchPayload !== null) {
            $state = ($matchPayload['match_status'] ?? '') === SpeedNetworkingMatch::MATCH_STATUS_IN_CALL
                ? 'in_call'
                : 'preview';
        } elseif ($participantStatus === SpeedNetworkingParticipant::STATUS_LEFT) {
            $state = 'completed';
        } elseif (in_array($participantStatus, [
            SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
            SpeedNetworkingParticipant::STATUS_IDLE,
        ], true)) {
            $state = 'next_peer_required';
        } elseif ($participantStatus === SpeedNetworkingParticipant::STATUS_BREAK) {
            $state = 'break';
        } else {
            $state = 'waiting';
        }

        $summaryAvailable = $participantStatus === SpeedNetworkingParticipant::STATUS_LEFT
            || SpeedNetworkingMatch::where('event_id', $event->id)
                ->where(function ($q) use ($user): void {
                    $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id);
                })
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                    SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                ])
                ->exists();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'state' => $state,
            'match_status' => $matchPayload['match_status'] ?? $state,
            'is_waiting' => ! $hasActiveMatch && $state !== 'completed',
            'has_active_match' => $hasActiveMatch,
            'match' => $matchPayload,
            'summary_available' => $summaryAvailable,
            'round_duration_seconds' => (int) $event->round_duration_seconds,
            'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
            'available_peers_count' => $counts['available_peers_count'],
            'active_attendees_count' => $counts['active_attendees_count'],
            'server_time' => $serverTime,
            'data' => [
                'event_id' => (string) $event->id,
                'state' => $state,
                'match_status' => $matchPayload['match_status'] ?? $state,
                'is_waiting' => ! $hasActiveMatch && $state !== 'completed',
                'has_active_match' => $hasActiveMatch,
                'match' => $matchPayload,
                'summary_available' => $summaryAvailable,
                'round_duration_seconds' => (int) $event->round_duration_seconds,
                'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                'available_peers_count' => $counts['available_peers_count'],
                'active_attendees_count' => $counts['active_attendees_count'],
                'server_time' => $serverTime,
            ],
        ]);
    }

    /**
     * Leave event.
     */
    public function leave(Request $request, string $eventId): JsonResponse
    {
        return $this->withIdempotency($request, $eventId, 'leave', function () use ($request, $eventId): JsonResponse {
            $user = $request->user();

            $event = $this->resolveSpeedNetworkingEvent($eventId);
            if ($event) {
                if ($authFailure = $this->authorizeEventAccess($event, $user, false)) {
                    return $authFailure;
                }
                $this->speedNetworkingService->leaveEvent($event, $user);
            } elseif (Schema::hasTable('events')) {
                $base = Event::find($eventId);
                if ($base && ($authFailure = $this->authorizeEventAccess($base, $user, false))) {
                    return $authFailure;
                }
            }

            $userLifeImpact = $this->speedNetworkingService->resolveUserLifeImpact($user, $event instanceof SpeedNetworkingEvent ? $event : null);
            $summary = $event instanceof SpeedNetworkingEvent
                ? $this->speedNetworkingService->getSessionSummary($event, $user)
                : [
                    'completed' => true,
                    'has_met_all_peers' => true,
                    'message' => 'All speed collaboration rounds have concluded. Great conversations made!',
                    'total_rounds' => 0,
                    'rounds_completed' => 0,
                    'total_interactions_count' => 0,
                    'qualifying_peers_count' => 0,
                    'life_impact' => [
                        'earned' => 0,
                        'total' => $userLifeImpact,
                        'balance_after' => $userLifeImpact,
                        'impact_score' => $userLifeImpact,
                        'impact_score_earned' => 0,
                        'score' => $userLifeImpact,
                        'impact' => 0,
                    ],
                    'life_impact_earned' => 0,
                    'life_impact_score' => $userLifeImpact,
                    'total_life_impacted' => $userLifeImpact,
                    'life_impacted_count' => $userLifeImpact,
                    'life_impact_count' => $userLifeImpact,
                    'lives_impacted' => $userLifeImpact,
                    'lives_impacted_count' => $userLifeImpact,
                    'impact_score' => $userLifeImpact,
                    'impact_score_earned' => 0,
                    'impact_earned' => 0,
                    'impact_points_earned' => 0,
                    'earned_impact' => 0,
                    'impact' => 0,
                    'score' => $userLifeImpact,
                    'coins' => [
                        'earned' => 0,
                        'balance_after' => (int) ($user->coins_balance ?? 0),
                    ],
                    'coins_earned' => 0,
                    'peers_met_count' => 0,
                    'peers_met' => [],
                    'interacted_peers' => [],
                ];

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Session completed successfully',
                'server_time' => Carbon::now()->utc()->toISOString(),
                'data' => $summary,
            ]);
        });
    }

    /**
     * Handle Match Preview Action ('connect' | 'skip' | 'block').
     */
    public function matchAction(SpeedNetworkingMatchActionRequest $request, string $eventId): JsonResponse
    {
        return $this->withIdempotency($request, $eventId, 'match_action', function () use ($request, $eventId): JsonResponse {
            $user = $request->user();
            $validated = $request->validated();

            $event = $this->resolveSpeedNetworkingEvent($eventId);

            if (! $event) {
                $base = Schema::hasTable('events') ? Event::find($eventId) : null;
                if ($base && ($authFailure = $this->authorizeEventAccess($base, $user, true))) {
                    return $authFailure;
                }

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'data' => [
                        'event_id' => $eventId,
                        'is_waiting' => true,
                        'match_status' => 'waiting',
                        'next_step' => 'next_peer_required',
                        'server_time' => Carbon::now()->utc()->toISOString(),
                    ],
                ]);
            }

            if ($authFailure = $this->authorizeEventAccess($event, $user, true)) {
                return $authFailure;
            }

            $result = $this->speedNetworkingService->handleMatchAction($event, $user, $validated);
            $httpStatus = (int) ($result['http_status'] ?? 200);
            unset($result['http_status']);

            if ($httpStatus === 403) {
                return response()->json([
                    'status' => 'forbidden',
                    'success' => false,
                    'message' => $result['message'] ?? 'You are not a participant of this match.',
                    'server_time' => $result['server_time'] ?? Carbon::now()->utc()->toISOString(),
                ], 403);
            }

            $agoraAppId = $this->getAgoraAppId();
            $result['agora_app_id'] ??= $agoraAppId;
            $result['server_time'] ??= Carbon::now()->utc()->toISOString();

            if ($httpStatus === 409) {
                return response()->json([
                    'status' => 'conflict',
                    'success' => false,
                    'message' => $result['message'] ?? 'Meeting has already started with both participants.',
                    'match_status' => (string) ($result['match_status'] ?? 'in_call'),
                    'match' => $result,
                    'server_time' => $result['server_time'],
                    'data' => $result,
                ], 409);
            }

            $hasMatch = empty($result['is_waiting']) && ! empty($result['channel_id']) && ! empty($result['peer']);
            $matchPayload = $hasMatch ? $result : null;
            if ($matchPayload !== null) {
                $result['match'] = $matchPayload;
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'is_waiting' => (bool) ($result['is_waiting'] ?? false),
                'match_status' => (string) ($result['match_status'] ?? 'preview'),
                'match' => $matchPayload,
                'peer' => $result['peer'] ?? null,
                'server_time' => $result['server_time'],
                'data' => $result,
            ]);
        });
    }

    /**
     * Update participant presence heartbeat.
     * Automatically triggers matchmaking when 2+ peers are waiting in the lobby (unless in next_peer_required/idle/break),
     * and returns the active match Agora credentials, peer details, and split live counts.
     */
    public function heartbeat(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if (! $event) {
                $base = Event::find($eventId);
                if ($base) {
                    $event = SpeedNetworkingEvent::where('id', $base->id)
                        ->orWhere('title', $base->title)
                        ->latest()
                        ->first();

                    if (! $event) {
                        $meta = is_array($base->metadata) ? $base->metadata : (json_decode((string) $base->metadata, true) ?: []);
                        $event = SpeedNetworkingEvent::create([
                            'id' => (string) $base->id,
                            'title' => (string) $base->title,
                            'description' => $base->description,
                            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
                            'round_duration_seconds' => (int) ($meta['round_duration_seconds'] ?? 300),
                            'break_duration_seconds' => (int) ($meta['break_duration_seconds'] ?? 20),
                            'total_rounds' => (int) ($meta['total_rounds'] ?? 5),
                            'current_round' => 1,
                            'scheduled_start_time' => $base->start_at ?: Carbon::now(),
                            'actual_start_time' => Carbon::now(),
                        ]);
                    }
                }
            }
        }

        $userStatus = (string) ($request->input('user_status') ?? $request->input('status') ?? '');
        $normalizedStatus = strtolower(trim($userStatus));
        $breakRemainingSeconds = (int) $request->input('break_remaining_seconds', 0);
        $agoraAppId = $this->getAgoraAppId();

        if ($event) {
            if ($authFailure = $this->authorizeEventAccess($event, $user, true)) {
                return $authFailure;
            }

            $this->speedNetworkingService->sweepExpiredMatches($event);

            $activeCount = $this->speedNetworkingService->heartbeat($event, $user, $normalizedStatus ?: null, $breakRemainingSeconds);
            $counts = $this->speedNetworkingService->getLiveEventCounts($event);
            $activeCount = $counts['active_attendees_count'];

            $participantRow = SpeedNetworkingParticipant::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();
            $dbStatus = strtolower((string) ($participantRow?->status ?? ''));

            $activeMatch = $this->speedNetworkingService->getActiveMatchPayload($event, $user, $normalizedStatus ?: null);
            $incomingMatch = ($normalizedStatus === 'on_break' || $normalizedStatus === 'break')
                ? $this->speedNetworkingService->findIncomingMatchForBreakUser($event, $user)
                : null;

            $isPartnerSkippedNotice = is_array($activeMatch)
                && ! empty($activeMatch['is_waiting'])
                && in_array($activeMatch['match_status'] ?? '', ['skipped', 'blocked', 'expired'], true);

            $suppressAutoMatch = in_array($normalizedStatus, [
                'on_break',
                'break',
                SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
                SpeedNetworkingParticipant::STATUS_IDLE,
            ], true) || (
                $normalizedStatus === ''
                && in_array($dbStatus, [
                    'on_break',
                    'break',
                    SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
                    SpeedNetworkingParticipant::STATUS_IDLE,
                ], true)
            );

            if ($activeMatch === null && ! $suppressAutoMatch) {
                $canStartNow = empty($event->scheduled_start_time) || $event->scheduled_start_time->lessThanOrEqualTo(Carbon::now());
                if ($canStartNow && $activeCount >= 2 && ($event->current_round < 1 || $event->status !== SpeedNetworkingEvent::STATUS_IN_PROGRESS)) {
                    $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                    $event->actual_start_time ??= Carbon::now();
                    if ($event->current_round < 1) {
                        $event->current_round = 1;
                    }
                    $event->save();
                }

                if ($event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS && $event->current_round >= 1) {
                    $matchResult = $this->speedNetworkingService->findOrCreateMatchForUser($event, $user);
                    if (! empty($matchResult['channel_id']) && empty($matchResult['is_waiting'])) {
                        $activeMatch = $matchResult;
                        $counts = $this->speedNetworkingService->getLiveEventCounts($event);
                        $activeCount = $counts['active_attendees_count'];
                    } elseif (! empty($matchResult['completed'])) {
                        $activeMatch = $matchResult;
                    }
                }
            }

            $hasActiveMatch = $activeMatch !== null
                && ! $isPartnerSkippedNotice
                && ! empty($activeMatch['channel_id'])
                && empty($activeMatch['is_waiting']);

            $now = Carbon::now();
            $serverTime = $now->copy()->utc()->toISOString();
            $isEnded = $event->isCompleted()
                || in_array(strtolower((string) $event->status), ['ended', 'completed', 'closed', 'expired'], true)
                || ($event->scheduled_end_time && $now->greaterThanOrEqualTo($event->scheduled_end_time));

            if ($isEnded) {
                if ($event->status !== SpeedNetworkingEvent::STATUS_COMPLETED && $event->status !== 'ended') {
                    $event->status = SpeedNetworkingEvent::STATUS_COMPLETED;
                    $event->actual_end_time ??= $now;
                    $event->save();
                }
                $summary = $this->speedNetworkingService->getSessionSummary($event, $user);

                return response()->json([
                    'status' => 'event_ended',
                    'success' => true,
                    'is_waiting' => false,
                    'message' => 'Speed Collaboration has concluded.',
                    'active_attendees_count' => $activeCount,
                    'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                    'available_peers_count' => $counts['available_peers_count'],
                    'has_active_match' => false,
                    'match' => null,
                    'incoming_match_request' => null,
                    'last_heartbeat_at' => $serverTime,
                    'server_time' => $serverTime,
                    'data' => array_merge($summary, [
                        'event_id' => (string) $event->id,
                        'status' => 'event_ended',
                        'match_status' => 'event_ended',
                        'is_waiting' => false,
                        'completed' => true,
                        'has_met_all_peers' => true,
                        'channel_id' => '',
                        'peer' => null,
                        'message' => 'Speed Collaboration has concluded.',
                        'last_heartbeat_at' => $serverTime,
                        'server_time' => $serverTime,
                        'active_attendees_count' => $activeCount,
                        'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                        'available_peers_count' => $counts['available_peers_count'],
                        'has_active_match' => false,
                        'match' => null,
                    ]),
                ]);
            }

            // Check if participant has manually left the session
            $isParticipantLeft = $this->speedNetworkingService->isUserCompleted($event, $user);
            if ($isParticipantLeft) {
                $summary = $this->speedNetworkingService->getSessionSummary($event, $user);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Session completed successfully',
                    'last_heartbeat_at' => $serverTime,
                    'server_time' => $serverTime,
                    'active_attendees_count' => $activeCount,
                    'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                    'available_peers_count' => $counts['available_peers_count'],
                    'data' => array_merge($summary, [
                        'last_heartbeat_at' => $serverTime,
                        'server_time' => $serverTime,
                        'active_attendees_count' => $activeCount,
                        'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                        'available_peers_count' => $counts['available_peers_count'],
                        'has_active_match' => false,
                        'match' => null,
                    ]),
                ]);
            }

            // If user has no active match and no partner-skipped notice, return waiting payload
            if (! $hasActiveMatch && ! $isPartnerSkippedNotice) {
                $waitingMatchStatus = $suppressAutoMatch && in_array($normalizedStatus ?: $dbStatus, [
                    SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
                    SpeedNetworkingParticipant::STATUS_IDLE,
                ], true) ? 'next_peer_required' : 'waiting';

                return response()->json([
                    'status' => 'waiting',
                    'success' => true,
                    'is_waiting' => true,
                    'is_paused' => $event->status === SpeedNetworkingEvent::STATUS_PAUSED,
                    'max_skip_limit' => $event->getEffectiveMaxSkipLimit(),
                    'preview_duration_seconds' => $event->getEffectivePreviewDurationSeconds(),
                    'message' => $event->status === SpeedNetworkingEvent::STATUS_PAUSED
                        ? 'Speed Collaboration is paused by the host.'
                        : 'Looking for new peers to join this Speed Collaboration...',
                    'active_attendees_count' => $activeCount,
                    'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                    'available_peers_count' => $counts['available_peers_count'],
                    'has_active_match' => false,
                    'match' => null,
                    'incoming_match_request' => $incomingMatch,
                    'last_heartbeat_at' => $serverTime,
                    'server_time' => $serverTime,
                    'data' => [
                        'event_id' => (string) $event->id,
                        'status' => 'waiting',
                        'match_status' => $waitingMatchStatus,
                        'is_waiting' => true,
                        'is_paused' => $event->status === SpeedNetworkingEvent::STATUS_PAUSED,
                        'max_skip_limit' => $event->getEffectiveMaxSkipLimit(),
                        'preview_duration_seconds' => $event->getEffectivePreviewDurationSeconds(),
                        'is_break_round' => false,
                        'completed' => false,
                        'has_met_all_peers' => false,
                        'channel_id' => '',
                        'peer' => null,
                        'message' => 'Looking for new peers to join this Speed Collaboration...',
                        'last_heartbeat_at' => $serverTime,
                        'server_time' => $serverTime,
                        'active_attendees_count' => $activeCount,
                        'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                        'available_peers_count' => $counts['available_peers_count'],
                        'has_active_match' => false,
                        'match' => null,
                        'incoming_match_request' => $incomingMatch,
                    ],
                ]);
            }

            $matchData = null;
            if ($hasActiveMatch && is_array($activeMatch)) {
                $matchData = $this->formatControllerMatchPayload($event, $activeMatch, $agoraAppId);
            } elseif ($isPartnerSkippedNotice && is_array($activeMatch)) {
                $matchData = $activeMatch;
            }

            if ($incomingMatch !== null && is_array($incomingMatch)) {
                $incomingMatch['agora_app_id'] = $agoraAppId;
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Heartbeat recorded',
                'active_attendees_count' => $activeCount,
                'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                'available_peers_count' => $counts['available_peers_count'],
                'has_active_match' => $hasActiveMatch,
                'match' => $matchData,
                'peer' => $matchData['peer'] ?? null,
                'incoming_match_request' => $incomingMatch,
                'last_heartbeat_at' => $serverTime,
                'server_time' => $serverTime,
                'data' => [
                    'completed' => false,
                    'is_waiting' => false,
                    'last_heartbeat_at' => $serverTime,
                    'server_time' => $serverTime,
                    'active_attendees_count' => $activeCount,
                    'peers_in_meetings_count' => $counts['peers_in_meetings_count'],
                    'available_peers_count' => $counts['available_peers_count'],
                    'has_active_match' => $hasActiveMatch,
                    'match' => $matchData,
                    'peer' => $matchData['peer'] ?? null,
                    'incoming_match_request' => $incomingMatch,
                ],
            ]);
        }

        $serverTime = Carbon::now()->utc()->toISOString();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Heartbeat recorded',
            'active_attendees_count' => 1,
            'peers_in_meetings_count' => 0,
            'available_peers_count' => 1,
            'has_active_match' => false,
            'match' => null,
            'last_heartbeat_at' => $serverTime,
            'server_time' => $serverTime,
            'data' => [
                'completed' => false,
                'last_heartbeat_at' => $serverTime,
                'server_time' => $serverTime,
                'active_attendees_count' => 1,
                'peers_in_meetings_count' => 0,
                'available_peers_count' => 1,
                'has_active_match' => false,
                'match' => null,
            ],
        ]);
    }

    /**
     * Get completed session peers summary.
     */
    public function summary(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if (! $event && Schema::hasTable('events')) {
                $base = Event::find($eventId);
                if ($base) {
                    $event = SpeedNetworkingEvent::where('id', $base->id)
                        ->orWhere('title', $base->title)
                        ->latest()
                        ->first();
                }
            }
        }

        if (! $event) {
            $event = Event::find($eventId);
        }

        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        if ($authFailure = $this->authorizeEventAccess($event, $user, false)) {
            return $authFailure;
        }

        if ($event instanceof SpeedNetworkingEvent) {
            $summary = $this->speedNetworkingService->getSessionSummary($event, $user);
        } else {
            $userLifeImpact = $this->speedNetworkingService->resolveUserLifeImpact($user);
            $meta = is_array($event->metadata) ? $event->metadata : (json_decode((string) $event->metadata, true) ?: []);
            $totalRounds = (int) ($meta['total_rounds'] ?? 0);
            $summary = [
                'completed' => true,
                'has_met_all_peers' => true,
                'message' => 'All speed collaboration rounds have concluded. Great conversations made!',
                'total_rounds' => $totalRounds,
                'rounds_completed' => 0,
                'total_interactions_count' => 0,
                'qualifying_peers_count' => 0,
                'life_impact' => [
                    'earned' => 0,
                    'total' => $userLifeImpact,
                    'balance_after' => $userLifeImpact,
                    'impact_score' => $userLifeImpact,
                    'impact_score_earned' => 0,
                    'score' => $userLifeImpact,
                    'impact' => 0,
                ],
                'life_impact_earned' => 0,
                'life_impact_score' => $userLifeImpact,
                'total_life_impacted' => $userLifeImpact,
                'life_impacted_count' => $userLifeImpact,
                'life_impact_count' => $userLifeImpact,
                'lives_impacted' => $userLifeImpact,
                'lives_impacted_count' => $userLifeImpact,
                'impact_score' => $userLifeImpact,
                'impact_score_earned' => 0,
                'impact_earned' => 0,
                'impact_points_earned' => 0,
                'earned_impact' => 0,
                'impact' => 0,
                'score' => $userLifeImpact,
                'coins' => [
                    'earned' => 0,
                    'balance_after' => (int) ($user->coins_balance ?? 0),
                ],
                'coins_earned' => 0,
                'peers_met_count' => 0,
                'peers_met' => [],
                'interacted_peers' => [],
            ];
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Session completed successfully',
            'data' => $summary,
        ]);
    }

    /**
     * Process completed Speed Networking matches into P2P meetings with creative posters and rewards.
     */
    public function completeP2p(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
            if (! $event && Schema::hasTable('events')) {
                $base = Event::find($eventId);
                if ($base) {
                    $event = SpeedNetworkingEvent::where('id', $base->id)
                        ->orWhere('title', $base->title)
                        ->latest()
                        ->first();
                }
            }
        }

        if (! $event) {
            $event = Event::find($eventId);
        }

        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Collaboration event not found.',
            ], 404);
        }

        if ($authFailure = $this->authorizeEventAccess($event, $user, false)) {
            return $authFailure;
        }

        if (! ($event instanceof SpeedNetworkingEvent)) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Event has no speed collaboration matches.',
                'data' => [
                    'created_meetings_count' => 0,
                    'meetings' => [],
                ],
            ]);
        }

        $this->speedNetworkingService->ensureSpeedNetworkingSchema();

        $userId = $user ? (string) $user->id : '';
        $peerId = trim((string) ($request->input('peer_id') ?? ''));
        $matchId = trim((string) ($request->input('match_id') ?? ''));
        $p2pMeetingId = trim((string) ($request->input('p2p_meeting_id') ?? ''));
        $notes = trim((string) ($request->input('meeting_notes') ?? $request->input('notes') ?? $request->input('remarks') ?? ''));
        $clientDuration = $request->has('interaction_duration_seconds')
            ? max(0, (int) $request->input('interaction_duration_seconds'))
            : null;
        $logP2p = $request->has('log_p2p') ? $request->boolean('log_p2p') : true;

        if ($userId !== '' && ($peerId !== '' || $matchId !== '' || $p2pMeetingId !== '' || $notes !== '')) {
            $matchQuery = SpeedNetworkingMatch::query()
                ->where('event_id', $event->id)
                ->where(function ($q) use ($userId): void {
                    $q->where('user_a_id', $userId)
                        ->orWhere('user_b_id', $userId);
                })
                ->whereNotNull('user_b_id')
                ->whereIn('match_status', [
                    SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
                    SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                ])
                ->where('status', '!=', SpeedNetworkingMatch::STATUS_SKIPPED);

            if ($matchId !== '') {
                $match = (clone $matchQuery)->where('id', $matchId)->latest('created_at')->first();
            } elseif ($peerId !== '') {
                $match = (clone $matchQuery)->where(function ($q) use ($peerId): void {
                    $q->where('user_a_id', $peerId)
                        ->orWhere('user_b_id', $peerId);
                })->latest('created_at')->first();
            } else {
                $match = (clone $matchQuery)->latest('created_at')->first();
            }

            if ($match) {
                $isUserA = ((string) $match->user_a_id === $userId);
                $notesUpdates = [];
                if ($notes !== '') {
                    $notesUpdates[$isUserA ? 'user_a_notes' : 'user_b_notes'] = $notes;
                    $notesUpdates['meeting_notes'] = $notes;
                }

                // Finalize call duration if not already persisted or if client sent interaction_duration_seconds
                $serverDuration = $match->resolveCallDurationSeconds((int) $event->round_duration_seconds);
                if ($match->duration_seconds === null) {
                    $effectiveDuration = $serverDuration > 0 ? $serverDuration : ($clientDuration ?? 0);
                    if ((int) $event->round_duration_seconds > 0) {
                        $effectiveDuration = min($effectiveDuration, (int) $event->round_duration_seconds);
                    }
                    $notesUpdates['duration_seconds'] = $effectiveDuration;
                    if ($match->ended_at === null && $clientDuration !== null) {
                        $notesUpdates['ended_at'] = Carbon::now();
                        $notesUpdates['status'] = SpeedNetworkingMatch::STATUS_COMPLETED;
                        $notesUpdates['match_status'] = SpeedNetworkingMatch::MATCH_STATUS_COMPLETED;
                    }
                } else {
                    $effectiveDuration = (int) $match->duration_seconds;
                }

                if (! empty($notesUpdates)) {
                    $match->update($notesUpdates);
                    $match = $match->fresh() ?? $match;
                }

                if ($notes !== '') {
                    $resolvedPeerId = $peerId !== ''
                        ? $peerId
                        : (string) ($isUserA ? $match->user_b_id : $match->user_a_id);

                    if ($resolvedPeerId !== '' && $resolvedPeerId !== $userId) {
                        app(PeerNoteService::class)->syncFromSpeedNetworking($userId, $resolvedPeerId, $notes);
                    }
                }

                $qualifies = $effectiveDuration >= SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS && $logP2p;

                if (! $qualifies) {
                    return response()->json([
                        'status' => 'success',
                        'success' => true,
                        'message' => 'Meeting notes saved. Call duration was under 2 minutes (120s), so P2P meeting and rewards were not logged.',
                        'data' => [
                            'match_id' => (string) $match->id,
                            'peer_id' => $peerId !== '' ? $peerId : (string) ($isUserA ? $match->user_b_id : $match->user_a_id),
                            'interaction_duration_seconds' => $effectiveDuration,
                            'p2p_logged' => false,
                            'p2p_meeting_id' => null,
                            'meeting_notes' => $match->getNotesForUser($userId),
                            'notes' => $match->getNotesForUser($userId),
                            'remarks' => $match->getNotesForUser($userId),
                            'life_impact_earned' => 0,
                            'coins_earned' => 0,
                            'created_meetings_count' => 0,
                            'meetings' => [],
                        ],
                    ]);
                }

                $meeting = $this->speedNetworkingP2pService->completeMatchAsP2pMeeting($match, $event, $notes !== '' ? $notes : null);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'P2P meeting and notes saved successfully.',
                    'data' => [
                        'match_id' => (string) $match->id,
                        'peer_id' => $peerId !== '' ? $peerId : (string) ($isUserA ? $match->user_b_id : $match->user_a_id),
                        'interaction_duration_seconds' => $effectiveDuration,
                        'p2p_logged' => $meeting !== null,
                        'p2p_meeting_id' => $meeting ? (string) $meeting->id : null,
                        'meeting_notes' => $match->getNotesForUser($userId),
                        'notes' => $match->getNotesForUser($userId),
                        'remarks' => $match->getNotesForUser($userId),
                        'life_impact_earned' => $meeting ? 1 : 0,
                        'coins_earned' => $meeting ? 3000 : 0,
                        'created_meetings_count' => $meeting ? 1 : 0,
                        'meetings' => $meeting ? [[
                            'id' => (string) $meeting->id,
                            'initiator_user_id' => (string) $meeting->initiator_user_id,
                            'peer_user_id' => (string) $meeting->peer_user_id,
                            'meeting_date' => $meeting->meeting_date,
                            'media' => $meeting->media,
                        ]] : [],
                    ],
                ]);
            }
        }

        $meetings = $this->speedNetworkingP2pService->completeAllMatchesForEvent($event, $user ? (string) $user->id : null);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'P2P meetings and creatives processed successfully.',
            'data' => [
                'created_meetings_count' => $meetings->count(),
                'meetings' => $meetings->map(fn ($m) => [
                    'id' => (string) $m->id,
                    'initiator_user_id' => (string) $m->initiator_user_id,
                    'peer_user_id' => (string) $m->peer_user_id,
                    'meeting_date' => $m->meeting_date,
                    'media' => $m->media,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Get past Speed Networking events history for the authenticated user,
     * including full session summary with all peers met, profiles, duration, and rewards.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $perPage = (int) $request->query('per_page', 15);
        $result = $this->speedNetworkingService->getUserHistory($user, $perPage);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Speed networking history retrieved successfully.',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Extend an active 1-on-1 Speed Collaboration connection by 60 seconds (once per connection, at any time).
     */
    public function extendConnection(Request $request, string $eventId): JsonResponse
    {
        return $this->withIdempotency($request, $eventId, 'extend_connection', function () use ($request, $eventId): JsonResponse {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $validated = $request->validate([
                'match_id' => ['nullable', 'uuid'],
            ]);

            $event = $this->resolveSpeedNetworkingEvent($eventId);
            if (! $event) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'code' => 'event_not_found',
                    'message' => 'Speed Collaboration event not found.',
                ], 404);
            }

            if ($denied = $this->authorizeEventAccess($event, $user)) {
                return $denied;
            }

            $matchId = isset($validated['match_id']) ? (string) $validated['match_id'] : null;
            $result = $this->speedNetworkingService->extendMatchConnection($event, $user, $matchId);
            $httpStatus = (int) ($result['http_status'] ?? 200);
            $agoraAppId = $this->getAgoraAppId();

            $formattedMatch = isset($result['match']) && is_array($result['match'])
                ? $this->formatControllerMatchPayload($event, $result['match'], $agoraAppId)
                : null;

            if (! ($result['ok'] ?? false)) {
                return response()->json([
                    'status' => (string) ($result['code'] ?? 'error'),
                    'success' => false,
                    'code' => (string) ($result['code'] ?? 'error'),
                    'message' => (string) ($result['message'] ?? 'Unable to extend connection.'),
                    'data' => [
                        'event_id' => (string) $event->id,
                        'match_id' => $result['match_id'] ?? $matchId,
                        'call_expires_at' => $result['call_expires_at'] ?? null,
                        'remaining_seconds' => $result['remaining_seconds'] ?? null,
                        'extension_used' => (bool) ($result['extension_used'] ?? false),
                        'extended_seconds' => (int) ($result['extended_seconds'] ?? 0),
                        'extension_duration_seconds' => SpeedNetworkingMatch::EXTENSION_DURATION_SECONDS,
                        'extended_at' => $result['extended_at'] ?? null,
                        'extended_by_user_id' => $result['extended_by_user_id'] ?? null,
                        'can_extend' => false,
                        'server_time' => $result['server_time'] ?? Carbon::now()->utc()->toISOString(),
                        'match' => $formattedMatch,
                    ],
                ], $httpStatus);
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'code' => 'extended',
                'message' => (string) $result['message'],
                'data' => [
                    'event_id' => (string) $event->id,
                    'match_id' => (string) $result['match_id'],
                    'previous_call_expires_at' => $result['previous_call_expires_at'],
                    'call_expires_at' => $result['call_expires_at'],
                    'remaining_seconds' => (int) $result['remaining_seconds'],
                    'extension_used' => true,
                    'extended_seconds' => (int) $result['extended_seconds'],
                    'extension_duration_seconds' => SpeedNetworkingMatch::EXTENSION_DURATION_SECONDS,
                    'extended_at' => $result['extended_at'],
                    'extended_by_user_id' => $result['extended_by_user_id'],
                    'can_extend' => false,
                    'server_time' => $result['server_time'],
                    'match' => $formattedMatch,
                ],
            ], 200);
        });
    }
}
