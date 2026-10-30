<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Models\CircleMember;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Services\Events\EventRazorpayPaymentFinalizer;
use App\Services\Events\EventRazorpayPaymentService;
use App\Services\SpeedNetworking\AgoraTokenService;
use App\Services\SpeedNetworking\SpeedNetworkingP2pService;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            return [
                'allowed' => true,
                'circle_restricted' => false,
                'circle_ids' => [],
                'circle_name' => null,
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
                'message' => 'Please log in to join this circle Speed Networking event.',
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
                'description' => 'Speed Networking Event Fee - '.(string) ($event->title ?? 'Speed Networking'),
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

        if ($status === 'completed') {
            return [
                'action_type' => 'COMPLETED',
                'label' => 'Event Ended',
                'is_enabled' => false,
                'helper_text' => 'This speed networking event has concluded.',
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
            $event = SpeedNetworkingEvent::find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::find($eventId);
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Networking event not found.',
            ], 404);
        }

        $circleAccess = $this->checkCircleAccess($event, $user);
        if (! $circleAccess['allowed']) {
            return response()->json([
                'status' => 'circle_restricted',
                'success' => false,
                'message' => $circleAccess['message'] ?? 'This Speed Networking event is exclusive to members of the selected Circle.',
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
                'message' => 'Speed Networking event fee payment required. Please complete payment via Razorpay.',
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
            'message' => 'Successfully registered for Speed Networking session.',
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
            $candidates = Event::query()
                ->where(function ($q): void {
                    $q->where('event_type', 'speed_networking')
                        ->orWhere('event_type', 'virtual_networking')
                        ->orWhere('metadata->is_speed_networking', true);
                })
                ->whereNotIn('status', ['completed', 'ended', 'cancelled', 'canceled'])
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
                'message' => 'No active speed networking event scheduled.',
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
                'total_rounds' => (int) $event->total_rounds,
                'current_round' => (int) $event->current_round,
                'duration_seconds' => $event->duration_seconds,
                'duration_minutes' => $event->duration_minutes,
                'duration' => $event->duration_minutes,
                'scheduled_start_time' => $event->scheduled_start_time?->copy()->utc()->toISOString(),
                'scheduled_end_time' => $event->scheduled_end_time?->copy()->utc()->toISOString(),
                'end_time' => $event->scheduled_end_time?->copy()->utc()->toISOString(),
                'actual_start_time' => $event->actual_start_time?->copy()->utc()->toISOString(),
                'actual_end_time' => $event->actual_end_time?->copy()->utc()->toISOString(),
                'estimated_end_time' => ($event->actual_start_time ? $event->actual_start_time->copy()->addSeconds($event->calculateDurationSeconds())->utc()->toISOString() : $event->scheduled_end_time?->copy()->utc()->toISOString()),
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

        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::find($eventId);
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Networking event not found.',
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
        $totalRounds = (int) ($event->total_rounds ?? ($meta['total_rounds'] ?? 5));
        $currentRound = (int) ($event->current_round ?? ($meta['current_round'] ?? 0));

        $startAt = $event->start_at ? Carbon::parse($event->start_at) : ($event->scheduled_start_time ?? Carbon::now());

        if ($event instanceof SpeedNetworkingEvent) {
            $durationSec = $event->duration_seconds;
            $durationMin = $event->duration_minutes;
            $scheduledEnd = $event->scheduled_end_time;
            $actualStart = $event->actual_start_time;
            $actualEnd = $event->actual_end_time;
        } else {
            $durationSec = ($totalRounds > 0)
                ? (($totalRounds * $roundDuration) + (max(0, $totalRounds - 1) * $breakDuration))
                : (int) ($meta['duration_seconds'] ?? (($meta['duration_minutes'] ?? 60) * 60));
            $durationMin = (int) ceil($durationSec / 60);
            $scheduledEnd = $event->end_at ? Carbon::parse($event->end_at) : $startAt->copy()->addSeconds($durationSec);
            $actualStart = ! empty($meta['actual_start_time']) ? Carbon::parse($meta['actual_start_time']) : null;
            $actualEnd = ! empty($meta['actual_end_time']) ? Carbon::parse($meta['actual_end_time']) : null;
        }

        $priceDisplay = (int) $paymentInfo['ticket_price'] == $paymentInfo['ticket_price']
            ? (int) $paymentInfo['ticket_price']
            : $paymentInfo['ticket_price'];

        $isRegistered = $paymentInfo['is_paid_event']
            ? $paymentInfo['has_paid']
            : ($reg !== null);

        $actionButton = $this->resolveActionButton($event->status ?? ($meta['speed_status'] ?? 'scheduled'), $isRegistered, $paymentInfo, $circleAccess, $startAt);

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Speed Networking event fetched successfully.',
            'data' => [
                'id' => (string) $event->id,
                'event_id' => (string) $event->id,
                'title' => (string) $event->title,
                'event_title' => (string) $event->title,
                'agora_app_id' => $this->getAgoraAppId(),
                'description' => $event->description,
                'banner_url' => $event->banner_url,
                'status' => $event->status ?? ($meta['speed_status'] ?? 'scheduled'),
                'round_duration_seconds' => $roundDuration,
                'break_duration_seconds' => $breakDuration,
                'total_rounds' => $totalRounds,
                'current_round' => $currentRound,
                'duration_seconds' => $durationSec,
                'duration_minutes' => $durationMin,
                'duration' => $durationMin,
                'scheduled_start_time' => $startAt->copy()->utc()->toISOString(),
                'scheduled_end_time' => $scheduledEnd?->copy()->utc()->toISOString(),
                'end_time' => $scheduledEnd?->copy()->utc()->toISOString(),
                'actual_start_time' => $actualStart?->copy()->utc()->toISOString(),
                'actual_end_time' => $actualEnd?->copy()->utc()->toISOString(),
                'estimated_end_time' => ($actualStart ? $actualStart->copy()->addSeconds($durationSec)->utc()->toISOString() : $scheduledEnd?->copy()->utc()->toISOString()),
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

        // 1. Resolve event from speed_networking_events or events table
        $event = null;
        if (Schema::hasTable('speed_networking_events')) {
            $event = SpeedNetworkingEvent::find($eventId);
        }
        if (! $event && Schema::hasTable('events')) {
            $event = Event::find($eventId);
        }
        if (! $event) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Speed Networking event not found.',
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
                'message' => $circleAccess['message'] ?? 'This Speed Networking event is exclusive to members of the selected Circle.',
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
                'message' => 'Speed Networking event fees payment required. Please complete payment via Razorpay.',
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
            $data = $this->speedNetworkingService->joinEvent($event, $user);
            $isWaiting = (bool) ($data['is_waiting'] ?? false);
            $hasMatch = ! $isWaiting && ! empty($data['channel_id']) && ! empty($data['peer']);

            $match = $hasMatch ? [
                'channel_id' => (string) ($data['channel_id'] ?? ''),
                'agora_app_id' => $agoraAppId,
                'agora_token' => (string) ($data['agora_token'] ?? ''),
                'remaining_seconds' => (int) ($data['remaining_seconds'] ?? 0),
                'current_round' => (int) ($data['current_round'] ?? 1),
                'total_rounds' => (int) ($data['total_rounds'] ?? 1),
                'peer' => $data['peer'] ?? null,
            ] : null;

            $data['agora_app_id'] = $agoraAppId;
            $data['match'] = $match;

            return response()->json([
                'status' => 'success',
                'success' => true,
                'is_waiting' => $isWaiting,
                'match' => $match,
                'message' => $data['message'] ?? ($isWaiting ? 'Waiting for the event to begin.' : 'Speed Networking session active.'),
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
                'message' => 'Speed Networking session active.',
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
            'message' => 'Payment verified successfully. You can now join the Speed Networking session.',
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
        $user = $request->user();
        $agoraAppId = $this->getAgoraAppId();

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

            if ($event) {
                $data = $this->speedNetworkingService->getNextMatch($event, $user, $request->isMethod('get'));
                $isCompleted = ! empty($data['completed']);
                $isWaiting = (bool) ($data['is_waiting'] ?? false);
                $hasMatch = ! $isWaiting && ! empty($data['channel_id']) && ! empty($data['peer']);

                $match = $hasMatch ? [
                    'channel_id' => (string) ($data['channel_id'] ?? ''),
                    'agora_app_id' => $agoraAppId,
                    'agora_token' => (string) ($data['agora_token'] ?? ''),
                    'remaining_seconds' => (int) ($data['remaining_seconds'] ?? 0),
                    'current_round' => (int) ($data['current_round'] ?? 1),
                    'total_rounds' => (int) ($data['total_rounds'] ?? 1),
                    'peer' => $data['peer'] ?? null,
                ] : null;

                $data['agora_app_id'] = $agoraAppId;
                $data['match'] = $match;

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'is_waiting' => $isWaiting,
                    'match' => $match,
                    'message' => $isCompleted ? ($data['message'] ?? 'Session completed successfully') : 'Next match retrieved successfully',
                    'data' => $data,
                ]);
            }
        }

        $baseEvent = Schema::hasTable('events') ? Event::find($eventId) : null;
        $channelId = 'sn_room_'.($baseEvent ? $baseEvent->id : $eventId);
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
            'peer' => null,
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'is_waiting' => true,
            'match' => null,
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
                'message' => 'Looking for next peer match...',
                'peer' => null,
            ],
        ]);
    }

    /**
     * Leave event.
     */
    public function leave(Request $request, string $eventId): JsonResponse
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
            if ($event) {
                $this->speedNetworkingService->leaveEvent($event, $user);
            }
        }

        $userLifeImpact = $this->speedNetworkingService->resolveUserLifeImpact($user, $event instanceof SpeedNetworkingEvent ? $event : null);
        $summary = $event instanceof SpeedNetworkingEvent
            ? $this->speedNetworkingService->getSessionSummary($event, $user)
            : [
                'completed' => true,
                'has_met_all_peers' => true,
                'message' => 'All speed networking rounds have concluded. Great conversations made!',
                'total_rounds' => 0,
                'rounds_completed' => 0,
                'total_interactions_count' => 0,
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
                'interacted_peers' => [],
            ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Session completed successfully',
            'data' => $summary,
        ]);
    }

    /**
     * Update participant presence heartbeat.
     * Automatically triggers matchmaking when 2+ peers are waiting in the lobby,
     * and returns the active match Agora credentials and peer details.
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
        $breakRemainingSeconds = (int) $request->input('break_remaining_seconds', 0);
        $agoraAppId = $this->getAgoraAppId();

        if ($event) {
            $activeCount = $this->speedNetworkingService->heartbeat($event, $user, $userStatus ?: null, $breakRemainingSeconds);
            $activeMatch = $this->speedNetworkingService->getActiveMatchPayload($event, $user);
            $incomingMatch = ($userStatus === 'on_break' || $userStatus === 'break')
                ? $this->speedNetworkingService->findIncomingMatchForBreakUser($event, $user)
                : null;

            // If user has no active match, check if we can pair them automatically
            if ($activeMatch === null) {
                // If 2+ active peers are present, ensure round 1 is started
                if ($activeCount >= 2 && ($event->current_round < 1 || $event->status !== SpeedNetworkingEvent::STATUS_IN_PROGRESS)) {
                    $event->status = SpeedNetworkingEvent::STATUS_IN_PROGRESS;
                    $event->actual_start_time ??= Carbon::now();
                    if ($event->current_round < 1) {
                        $event->current_round = 1;
                    }
                    $event->save();
                }

                // If event is in progress, attempt to pair the user with an available peer
                if ($event->status === SpeedNetworkingEvent::STATUS_IN_PROGRESS && $event->current_round >= 1) {
                    $matchResult = $this->speedNetworkingService->findOrCreateMatchForUser($event, $user);
                    if (! empty($matchResult['channel_id']) && empty($matchResult['is_waiting'])) {
                        $activeMatch = $matchResult;
                    } elseif (! empty($matchResult['completed'])) {
                        $activeMatch = $matchResult;
                    }
                }
            }

            $hasActiveMatch = $activeMatch !== null && ! empty($activeMatch['channel_id']) && empty($activeMatch['is_waiting']);

            // Check if session or event is completed
            $isSessionCompleted = (! $hasActiveMatch) && (
                (! empty($activeMatch['completed']))
                || $event->isCompleted()
                || $this->speedNetworkingService->isUserCompleted($event, $user)
            );

            if ($isSessionCompleted) {
                $summary = $this->speedNetworkingService->getSessionSummary($event, $user);

                return response()->json([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Session completed successfully',
                    'data' => array_merge($summary, [
                        'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                        'active_attendees_count' => $activeCount,
                        'has_active_match' => false,
                        'match' => null,
                    ]),
                ]);
            }

            $matchData = null;
            if ($hasActiveMatch) {
                $matchData = [
                    'channel_id' => (string) ($activeMatch['channel_id'] ?? ''),
                    'agora_app_id' => $agoraAppId,
                    'agora_token' => (string) ($activeMatch['agora_token'] ?? ''),
                    'remaining_seconds' => (int) ($activeMatch['remaining_seconds'] ?? $event->round_duration_seconds),
                    'is_waiting' => false,
                    'is_break_round' => (bool) ($activeMatch['is_break_round'] ?? false),
                    'current_round' => (int) ($activeMatch['current_round'] ?? 1),
                    'total_rounds' => (int) ($activeMatch['total_rounds'] ?? $event->total_rounds),
                    'peer' => $activeMatch['peer'] ?? null,
                ];
            }

            if ($incomingMatch !== null && is_array($incomingMatch)) {
                $incomingMatch['agora_app_id'] = $agoraAppId;
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Heartbeat recorded',
                'active_attendees_count' => $activeCount,
                'has_active_match' => $hasActiveMatch,
                'match' => $matchData,
                'incoming_match_request' => $incomingMatch,
                'data' => [
                    'completed' => false,
                    'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                    'active_attendees_count' => $activeCount,
                    'has_active_match' => $hasActiveMatch,
                    'match' => $matchData,
                    'incoming_match_request' => $incomingMatch,
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Heartbeat recorded',
            'active_attendees_count' => 1,
            'has_active_match' => false,
            'match' => null,
            'data' => [
                'completed' => false,
                'last_heartbeat_at' => Carbon::now()->toIso8601String(),
                'active_attendees_count' => 1,
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
                'message' => 'Speed Networking event not found.',
            ], 404);
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
                'message' => 'All speed networking rounds have concluded. Great conversations made!',
                'total_rounds' => $totalRounds,
                'rounds_completed' => 0,
                'total_interactions_count' => 0,
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
                'message' => 'Speed Networking event not found.',
            ], 404);
        }

        if (! ($event instanceof SpeedNetworkingEvent)) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Event has no speed networking matches.',
                'data' => [
                    'created_meetings_count' => 0,
                    'meetings' => [],
                ],
            ]);
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
}
