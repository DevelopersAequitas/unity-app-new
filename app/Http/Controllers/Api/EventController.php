<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\V1\VerifyEventRazorpayPaymentRequest;
use App\Http\Requests\Event\CirclePastEventsRequest;
use App\Http\Requests\Event\EventCheckinRequest;
use App\Http\Requests\Event\EventRsvpRequest;
use App\Http\Requests\Event\RegisterEventOccurrenceRequest;
use App\Http\Requests\Event\ScanEventQrRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\VisitorEventRegistrationRequest;
use App\Http\Resources\Event\EventDetailResource;
use App\Http\Resources\Event\EventOccurrenceListResource;
use App\Http\Resources\Event\EventRegistrationResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventRsvpResource;
use App\Jobs\SendEventCreatedNotificationJob;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleMember;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\EventRegistrationRequest;
use App\Models\EventRsvp;
use App\Models\ScanAppUser;
use App\Models\User;
use App\Services\Events\EventCheckinService;
use App\Services\Events\EventCouponService;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventPaymentSyncService;
use App\Services\Events\EventQrService;
use App\Services\Events\EventRazorpayPaymentFinalizer;
use App\Services\Events\EventRazorpayPaymentService;
use App\Services\Events\EventRegistrationQrService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventScannerQrScanService;
use App\Services\Events\EventService;
use App\Services\Events\EventZohoInvoiceSyncService;
use App\Services\Referrals\ReferralService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventController extends BaseApiController
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventRegistrationService $registrations,
        private readonly EventCheckinService $checkins,
        private readonly EventScannerQrScanService $scannerQrScans,
        private readonly EventPaymentService $payments,
        private readonly EventPaymentSyncService $eventPaymentSync,
        private readonly EventRegistrationQrService $registrationQr,
        private readonly EventRazorpayPaymentService $razorpayPayments,
        private readonly EventRazorpayPaymentFinalizer $paymentFinalizer,
        private readonly EventZohoInvoiceSyncService $zohoInvoiceSync,
        private readonly EventCouponService $coupons,
    ) {}

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $paginator = $this->events->listOccurrences($request->only(['event_type', 'type', 'circle_id', 'mode', 'from_date', 'to_date', 'status', 'upcoming', 'search', 'title']), $request->user(), $perPage);

        return $this->success([
            'total' => $paginator->total(),
            'items' => EventOccurrenceListResource::collection($paginator->getCollection()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Events fetched successfully.');
    }

    public function pastEvents(CirclePastEventsRequest $request, ?string $circle_id = null)
    {
        $circleId = $request->validated('circle_id') ?? $request->input('circle_id') ?? $circle_id;

        if (! $circleId) {
            return $this->error('The circle_id field is required.', 422);
        }

        $circle = Circle::query()->find($circleId);

        if (! $circle) {
            return $this->error('Circle not found.', 404);
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $paginator = $this->events->listPastOccurrences($circleId, $request->user(), $perPage);

        return $this->success([
            'total' => $paginator->total(),
            'items' => EventOccurrenceListResource::collection($paginator->getCollection()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Past events fetched successfully.');
    }

    public function allWithLiveStatus(Request $request)
    {
        try {
            $timezone = config('app.timezone', 'UTC') ?: 'UTC';
            $now = now($timezone);

            $occurrences = EventOccurrence::query()
                ->with(['event.circle'])
                ->whereNotNull('start_at')
                ->whereNotNull('end_at')
                ->where(function ($query): void {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive', 'completed', 'complete']);
                })
                ->whereHas('event', function ($query): void {
                    if (Schema::hasColumn('events', 'is_active')) {
                        $query->where('is_active', true);
                    }

                    if (Schema::hasColumn('events', 'status')) {
                        $query->where(function ($statusQuery): void {
                            $statusQuery->whereNull('status')
                                ->orWhereNotIn('status', ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive', 'completed', 'complete']);
                        });
                    }
                })
                ->get()
                ->filter(fn (EventOccurrence $occurrence) => $occurrence->event !== null)
                ->map(function (EventOccurrence $occurrence) use ($now, $timezone): ?array {
                    $event = $occurrence->event;
                    $startAt = $this->localEventDateTime($occurrence->start_at, $timezone);
                    $endAt = $this->localEventDateTime($occurrence->end_at, $timezone);

                    $occurrenceStatus = strtolower((string) ($occurrence->status ?? ''));
                    $eventStatusStr = strtolower((string) ($event->status ?? ''));
                    $computedEventStatus = strtolower((string) ($event->computed_status ?? ''));

                    $excludedStatuses = ['cancelled', 'canceled', 'rejected', 'deleted', 'archived', 'inactive', 'completed', 'complete'];
                    if (in_array($occurrenceStatus, $excludedStatuses, true)) {
                        return null;
                    }
                    if (in_array($eventStatusStr, $excludedStatuses, true)) {
                        return null;
                    }
                    if (in_array($computedEventStatus, $excludedStatuses, true)) {
                        return null;
                    }

                    if (! $startAt || ! $endAt || $endAt->lt($now)) {
                        return null;
                    }

                    // If event starts in the future, ignore occurrences that start prior to event start_at
                    if ($event->start_at) {
                        $eventStartUtc = Carbon::parse($event->start_at);
                        if ($eventStartUtc->gt($now) && Carbon::parse($occurrence->start_at)->lt($eventStartUtc->copy()->subMinutes(15))) {
                            return null;
                        }
                    }

                    // For recurring events with fixed day of month, ensure occurrence matches the recurrence day
                    if ($event->recurrence_type === 'monthly' && $event->recurrence_day_of_month) {
                        $eventTz = data_get($event->metadata, 'timezone') ?: $timezone;
                        $occLocal = Carbon::parse($occurrence->start_at)->setTimezone($eventTz);
                        if ((int) $occLocal->format('j') !== (int) $event->recurrence_day_of_month) {
                            return null;
                        }
                    }

                    $isLiveEvent = $startAt->lte($now) && $endAt->gte($now);
                    $isUpcoming = $startAt->gt($now);

                    if (! $isLiveEvent && ! $isUpcoming) {
                        return null;
                    }

                    $eventStatus = $isLiveEvent ? 'live' : 'upcoming';

                    Log::debug('events_all_with_live_status_calculated', [
                        'event_id' => $event->id,
                        'occurrence_id' => $occurrence->id,
                        'timezone' => $timezone,
                        'current_time' => $now->format('Y-m-d H:i:s'),
                        'start_time' => $startAt->format('Y-m-d H:i:s'),
                        'end_time' => $endAt->format('Y-m-d H:i:s'),
                        'is_live_event' => $isLiveEvent,
                        'event_status' => $eventStatus,
                    ]);

                    return [
                        '_sort_status' => $isLiveEvent ? 0 : 1,
                        '_sort_start_at' => $startAt->getTimestamp(),
                        '_start_year' => $startAt->year,
                        '_start_month' => $startAt->month,
                        'event_id' => $event->id,
                        'occurrence_id' => $occurrence->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'event_type' => $event->event_type,
                        'circle_id' => $event->circle_id,
                        'circle_name' => $event->circle?->name,
                        'start_datetime' => $startAt->format('Y-m-d H:i:s'),
                        'end_datetime' => $endAt->format('Y-m-d H:i:s'),
                        'timezone' => $timezone,
                        'image_url' => $this->eventImageUrl($event),
                        'is_live_event' => $isLiveEvent,
                        'event_status' => $eventStatus,
                    ];
                })
                ->filter()
                ->sortBy([
                    ['_sort_status', 'asc'],
                    ['_sort_start_at', 'asc'],
                ]);

            $currentYear = $now->year;
            $currentMonth = $now->month;

            $groupedOccurrences = $occurrences->groupBy('event_id');
            $filteredOccurrences = collect();

            foreach ($groupedOccurrences as $group) {
                $currentMonthGroup = $group->filter(function (array $occ) use ($currentYear, $currentMonth): bool {
                    return ($occ['_start_year'] === $currentYear && $occ['_start_month'] === $currentMonth) || $occ['is_live_event'];
                });

                if ($currentMonthGroup->isNotEmpty()) {
                    $selected = $currentMonthGroup->firstWhere('is_live_event', true) ?? $currentMonthGroup->first();
                    if ($selected) {
                        $filteredOccurrences->push($selected);
                    }
                } elseif ($group->isNotEmpty()) {
                    $firstOccur = $group->first();
                    if ($firstOccur) {
                        $filteredOccurrences->push($firstOccur);
                    }
                }
            }

            $finalOccurrences = $filteredOccurrences
                ->sortBy([
                    ['_sort_status', 'asc'],
                    ['_sort_start_at', 'asc'],
                ])
                ->map(fn (array $event): array => collect($event)->except(['_sort_status', '_sort_start_at', '_start_year', '_start_month'])->all())
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Events fetched successfully.',
                'data' => $finalOccurrences,
            ]);
        } catch (\Throwable $e) {
            Log::error('events_all_with_live_status_failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching events.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function localEventDateTime(mixed $dateTime, string $timezone): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        return Carbon::parse($dateTime)->setTimezone($timezone);
    }

    private function eventImageUrl(Event $event): ?string
    {
        $bannerUrl = $event->banner_url;

        if (! is_string($bannerUrl) || trim($bannerUrl) === '') {
            return null;
        }

        $bannerUrl = trim($bannerUrl);

        if (str_starts_with($bannerUrl, 'http://') || str_starts_with($bannerUrl, 'https://') || str_starts_with($bannerUrl, '/')) {
            return $bannerUrl;
        }

        return url('/api/v1/files/'.$bannerUrl);
    }

    public function show(Request $request, string $id)
    {
        $event = Event::query()
            ->with(['circle', 'circles.cityRef', 'occurrences' => fn ($q) => $q->with(['event.circle', 'event.circles.cityRef', 'registrations' => fn ($r) => $r->where('user_id', $request->user()?->id)])->withCount(['registrations as registered_count' => fn ($r) => $r->where('status', '!=', 'cancelled')])->orderBy('start_at')])
            ->find($id);

        if (! $event) {
            return $this->error('Event not found', 404);
        }

        return $this->success(new EventDetailResource($event), 'Event fetched successfully.');
    }

    public function register(RegisterEventOccurrenceRequest $request, string $eventId, string $occurrenceId)
    {
        $user = $request->user();
        $event = Event::query()->with('circles')->findOrFail($eventId);
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->findOrFail($occurrenceId);
        $eventCircleId = $event->circle_id;
        $allowedCircleIds = $this->registrationAllowedCircleIds($event);

        // Resolve Inviter / Referrer from request
        $referralCode = $request->input('referral_code')
            ?? $request->input('inviter_code')
            ?? $request->input('invited_by_referral_code')
            ?? $request->input('invited_by')
            ?? $request->input('invited_by_user_id');
        $invitedByUserId = $this->resolveInvitedByUserId($referralCode, (string) $user->id);

        $coupon = null;
        $couponData = [];
        $couponCodeInput = $request->input('coupon_code');
        if ($couponCodeInput !== null && trim((string) $couponCodeInput) !== '') {
            try {
                $coupon = $this->coupons->validateCoupon((string) $couponCodeInput, $event, $occurrence);
                $originalPrice = $this->payments->amount($event);
                $discountCalculation = $this->coupons->calculateDiscount($coupon, $originalPrice);
                $finalAmount = $discountCalculation['final_price'];
                $discountAmount = $discountCalculation['discount_amount'];

                $couponData = [
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'original_amount' => $originalPrice,
                    'discount_amount' => $discountAmount,
                    'amount' => $finalAmount,
                ];
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired coupon code',
                ], 422);
            }
        }

        $inviterUserId = $this->resolveInviterUserId($request);
        $inviterCode = $this->extractInviterCode($request);

        Log::info('member_event_registration_start', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId]);
        Log::info('member_event_circle_check_start', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId]);

        $memberQuery = CircleMember::query()
            ->whereIn('circle_id', $allowedCircleIds ?: array_filter([$eventCircleId]))
            ->where('user_id', $user->id)
            ->whereNull('deleted_at');
        if (Schema::hasColumn('circle_members', 'status')) {
            $memberQuery->whereIn('status', CircleMember::activeStatuses());
        }
        if (Schema::hasColumn('circle_members', 'expires_at')) {
            $memberQuery->where(function ($q): void {
                $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
            });
        }
        $membership = $memberQuery->first();
        $eligibilityContext = ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId, 'allowed_circle_ids' => $allowedCircleIds];
        Log::info('event_register_eligibility_check_start', $eligibilityContext);

        if (! $membership) {
            Log::info('cross_circle_registration_attempt', $eligibilityContext);

            if ($coupon) {
                $registration = $this->registrations->registerCrossCircleMemberDirect(
                    $event,
                    $occurrence,
                    $user,
                    $request->input('source', 'app'),
                    $couponData
                );
                $this->coupons->applyCoupon($coupon);

                $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);

                if (($couponData['amount'] ?? 0) > 0) {
                    $registration = $this->payments->attachCheckout($registration);
                } else {
                    $registration = $this->registrationQr->ensureQrGenerated($registration);
                }

                $payload = $this->payments->responsePayload($registration);

                return $this->success(
                    $payload,
                    ($payload['requires_payment'] ?? false) ? 'Payment required. Please complete payment.' : 'Event registration successful.',
                    201
                );
            }

            if ($this->isDirectPaidCrossCircleEvent($event)) {
                Log::info('multi_circle_event_direct_cross_circle_registration_start', $eligibilityContext);
                $registration = $this->registrations->registerCrossCircleMemberDirect(
                    $event,
                    $occurrence,
                    $user,
                    $request->input('source', 'app')
                );
                $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);
                $payload = $this->payments->responsePayload($registration);
                Log::info('multi_circle_event_direct_cross_circle_registration_success', $eligibilityContext + [
                    'registration_id' => (string) $registration->id,
                    'payment_required' => (bool) ($registration->payment_required ?? false),
                    'payment_status' => $registration->payment_status ?? null,
                ]);

                return $this->success(
                    $payload,
                    ($payload['requires_payment'] ?? false) ? 'Payment required. Please complete payment.' : 'Event registration successful.',
                    201
                );
            }

            $approvedRequest = EventRegistrationRequest::query()
                ->where('event_id', $event->id)
                ->where('occurrence_id', $occurrence->id)
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->latest('approved_at')
                ->latest('created_at')
                ->first();

            if ($approvedRequest) {
                Log::info('event_register_approved_cross_circle_request_true', $eligibilityContext + ['request_id' => $approvedRequest->id, 'request_status' => $approvedRequest->status]);
                Log::info('cross_circle_registration_after_approval_start', $eligibilityContext + ['request_id' => $approvedRequest->id, 'request_status' => $approvedRequest->status]);
                $registration = $this->registrations->registerApprovedCrossCircleMember(
                    $event,
                    $occurrence,
                    $user,
                    (string) $approvedRequest->id,
                    $request->input('source', 'app'),
                    $couponData
                );

                $effectiveInviterUserId = $invitedByUserId ?: ($approvedRequest->invited_by_user_id ?? ($approvedRequest->metadata['invited_by_user_id'] ?? null));
                $effectiveInviterCode = $referralCode ?: ($approvedRequest->metadata['inviter_code'] ?? ($approvedRequest->metadata['referral_code'] ?? null));
                $this->attachInviterToRegistration($registration, $effectiveInviterUserId, $request->input('invited_by_type'), $effectiveInviterCode);
                $approvedRequest->forceFill(['registration_id' => $registration->id])->save();

                Log::info('cross_circle_registration_after_approval_payment_link_created', $eligibilityContext + ['request_id' => $approvedRequest->id, 'request_status' => $approvedRequest->status, 'registration_id' => (string) $registration->id]);
                Log::info('cross_circle_approved_registration_payment_link_created', $eligibilityContext + ['request_id' => $approvedRequest->id, 'registration_id' => (string) $registration->id]);

                return $this->success($this->payments->responsePayload($registration), 'Payment is required to complete registration.', 201);
            }

            $req = EventRegistrationRequest::query()
                ->where('event_id', $event->id)
                ->where('occurrence_id', $occurrence->id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'rejected'])
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->first();

            if ($req && $req->status === 'pending') {
                Log::info('event_register_eligibility_failed_pending_request', $eligibilityContext + ['request_id' => $req->id, 'request_status' => $req->status]);

                return $this->error('Your registration request is pending admin approval.', 403, [
                    'request_required' => true,
                    'request_status' => 'pending',
                    'request_id' => $req->id,
                ]);
            }
            if ($req && $req->status === 'rejected') {
                Log::info('event_register_eligibility_failed_rejected_request', $eligibilityContext + ['request_id' => $req->id, 'request_status' => $req->status]);

                return $this->error('Your registration request was rejected by admin.', 403, [
                    'request_required' => true,
                    'request_status' => 'rejected',
                ]);
            }

            if ($request->filled('reason') || $request->filled('request_reason')) {
                Log::info('cross_circle_register_auto_delegating_to_request', $eligibilityContext);

                return $this->createRegistrationRequest($request, (string) $event->id, (string) $occurrence->id);
            }

            Log::info('event_register_eligibility_failed_no_request', $eligibilityContext);
            Log::info('cross_circle_request_required', $eligibilityContext);

            return $this->error('You are not a member of this event circle. Please submit a registration request for admin approval.', 403, [
                'request_required' => true,
                'request_status' => 'not_requested',
            ]);
        }
        Log::info('event_register_same_circle_member_true', $eligibilityContext);
        Log::info('member_event_circle_check_passed', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId]);

        $existing = EventRegistration::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->first();
        if ($existing) {
            Log::info('member_event_registration_existing_found', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId, 'registration_id' => (string) $existing->id]);
        }

        if ($coupon) {
            $registration = $this->registrations->registerCrossCircleMemberDirect(
                $event,
                $occurrence,
                $user,
                $request->input('source', 'app'),
                $couponData
            );
            $this->coupons->applyCoupon($coupon);

            $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);

            if (($couponData['amount'] ?? 0) > 0) {
                $registration = $this->payments->attachCheckout($registration);
            } else {
                $registration = $this->registrationQr->ensureQrGenerated($registration);
            }

            $payload = $this->payments->responsePayload($registration);

            return $this->success(
                $payload,
                ($payload['requires_payment'] ?? false) ? 'Payment required. Please complete payment.' : 'Event registration successful.',
                201
            );
        }

        $registration = $this->registrations->registerMemberDirectNoPayment(
            $event,
            $occurrence,
            $user,
            $request->input('source', 'app')
        );

        $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);
        if ((! empty($registration->qr_code_url) || ! empty($registration->qr_code_path)) && ! $existing) {
            Log::info('member_event_registration_qr_generated', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId, 'registration_id' => (string) $registration->id]);
        }
        Log::info('member_event_registration_success', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $eventCircleId, 'registration_id' => (string) $registration->id]);

        return $this->success($this->payments->responsePayload($registration), 'Event registration successful.', 201);
    }

    public function createRegistrationRequest(Request $request, string $eventId, string $occurrenceId)
    {
        $user = $request->user();
        $event = Event::query()->with('circles')->findOrFail($eventId);
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->findOrFail($occurrenceId);
        $eventCircleId = $event->circle_id;
        $allowedCircleIds = $this->registrationAllowedCircleIds($event);

        $coupon = null;
        $couponCodeInput = $request->input('coupon_code');
        if ($couponCodeInput !== null && trim((string) $couponCodeInput) !== '') {
            try {
                $coupon = $this->coupons->validateCoupon((string) $couponCodeInput, $event, $occurrence);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired coupon code',
                ], 422);
            }
        }

        $sameCircle = CircleMember::query()->whereIn('circle_id', $allowedCircleIds ?: array_filter([$eventCircleId]))->where('user_id', $user->id)->whereNull('deleted_at')->whereIn('status', CircleMember::activeStatuses())->exists();
        if ($sameCircle && ! $coupon) {
            return $this->success([], 'You are already a member of this circle. You can register directly.');
        }

        $existingReg = EventRegistration::query()->where('occurrence_id', $occurrence->id)->where('user_id', $user->id)->where('status', '!=', 'cancelled')->whereNull('deleted_at')->first();
        if ($existingReg) {
            $existingReg = $this->registrationQr->ensureQrGenerated($existingReg);

            return response()->json([
                'success' => true,
                'message' => 'You are already registered for this event.',
                'data' => [
                    'user_registration' => [
                        'status' => 'approved',
                        'registration_id' => (string) $existingReg->id,
                        'qr_code_data' => $existingReg->qr_code_url ?? $existingReg->qr_token,
                    ],
                    'registration_id' => $existingReg->id,
                ],
            ], 200);
        }

        $reason = (string) ($request->input('reason') ?? $request->input('request_reason') ?? '');
        $inviterUserId = $this->resolveInviterUserId($request);
        $inviterCode = $this->extractInviterCode($request);

        if ($coupon) {
            $originalPrice = $this->payments->amount($event);
            $discountCalculation = $this->coupons->calculateDiscount($coupon, $originalPrice);
            $finalAmount = $discountCalculation['final_price'];
            $discountAmount = $discountCalculation['discount_amount'];

            $couponReqData = [
                'event_id' => $event->id,
                'occurrence_id' => $occurrence->id,
                'user_id' => $user->id,
                'event_circle_id' => $eventCircleId,
                'status' => 'approved',
                'request_reason' => $reason,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
            ];

            if ($inviterUserId && Schema::hasColumn('event_registration_requests', 'invited_by_user_id')) {
                $couponReqData['invited_by_user_id'] = $inviterUserId;
            }
            if (Schema::hasColumn('event_registration_requests', 'invited_by_type')) {
                $couponReqData['invited_by_type'] = 'circle_member_peer';
            }

            $meta = [
                'reason' => $reason,
                'business_category_id' => $request->input('business_category_id') ?? $request->input('category_id') ?? $request->input('visitor_business_category_id'),
            ];
            if ($inviterUserId) {
                $meta['invited_by_user_id'] = $inviterUserId;
                $meta['inviter_user_id'] = $inviterUserId;
            }
            if ($inviterCode) {
                $meta['inviter_code'] = $inviterCode;
                $meta['referral_code'] = $inviterCode;
            }
            $meta['invited_by_type'] = 'circle_member_peer';
            $couponReqData['metadata'] = array_filter($meta, fn ($v) => $v !== null && $v !== '');

            $req = EventRegistrationRequest::query()->create($couponReqData);

            $couponData = [
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'original_amount' => $originalPrice,
                'discount_amount' => $discountAmount,
                'amount' => $finalAmount,
            ];

            $registration = $this->registrations->registerApprovedCrossCircleMember(
                $event,
                $occurrence,
                $user,
                (string) $req->id,
                $request->input('source', 'app'),
                $couponData
            );

            if ($inviterUserId || $inviterCode) {
                $registration = $this->attachInviterAttribution($registration, $inviterUserId, $inviterCode);
            }

            $req->forceFill(['registration_id' => $registration->id])->save();
            $this->coupons->applyCoupon($coupon);

            if ($finalAmount > 0) {
                $registration = $this->payments->attachCheckout($registration);
            } else {
                $registration = $this->registrationQr->ensureQrGenerated($registration);
            }

            $responsePayload = $this->payments->responsePayload($registration);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => array_merge($responsePayload, [
                    'user_registration' => [
                        'status' => 'approved',
                        'registration_id' => (string) $registration->id,
                        'qr_code_data' => $registration->qr_code_url ?? $registration->qr_token,
                    ],
                ]),
            ], 200);
        }

        $existing = EventRegistrationRequest::query()->where('event_id', $event->id)->where('occurrence_id', $occurrence->id)->where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])->latest('created_at')->first();
        if ($existing) {
            Log::info('cross_circle_registration_request_existing', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'request_id' => $existing->id, 'status' => $existing->status]);

            return $this->success(['request_id' => $existing->id, 'status' => $existing->status, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'user_id' => $user->id], $existing->status === 'approved' ? 'Your request is approved. You can register now.' : 'Your registration request is pending admin approval.');
        }

        $reqData = [
            'event_id' => $event->id,
            'occurrence_id' => $occurrence->id,
            'user_id' => $user->id,
            'event_circle_id' => $eventCircleId,
            'status' => 'pending',
            'request_reason' => $reason,
        ];

        if ($inviterUserId && Schema::hasColumn('event_registration_requests', 'invited_by_user_id')) {
            $reqData['invited_by_user_id'] = $inviterUserId;
        }
        if (Schema::hasColumn('event_registration_requests', 'invited_by_type')) {
            $reqData['invited_by_type'] = 'circle_member_peer';
        }

        $meta = [
            'reason' => $reason,
            'business_category_id' => $request->input('business_category_id') ?? $request->input('category_id') ?? $request->input('visitor_business_category_id'),
        ];
        if ($inviterUserId) {
            $meta['invited_by_user_id'] = $inviterUserId;
            $meta['inviter_user_id'] = $inviterUserId;
        }
        if ($inviterCode) {
            $meta['inviter_code'] = $inviterCode;
            $meta['referral_code'] = $inviterCode;
        }
        $meta['invited_by_type'] = 'circle_member_peer';
        $reqData['metadata'] = array_filter($meta, fn ($v) => $v !== null && $v !== '');

        $req = EventRegistrationRequest::query()->create($reqData);
        Log::info('cross_circle_registration_request_created', ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'request_id' => $req->id]);

        return $this->success(['request_id' => $req->id, 'status' => $req->status, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'user_id' => $user->id], 'Registration request submitted successfully. Please wait for admin approval.');
    }

    private function isDirectPaidCrossCircleEvent(Event $event): bool
    {
        return in_array($event->event_type, ['global_event', 'state_event'], true);
    }

    private function registrationAllowedCircleIds(Event $event): array
    {
        if (in_array($event->event_type, ['global_event', 'state_event'], true)) {
            $ids = $event->relationLoaded('circles')
                ? $event->circles->pluck('id')->all()
                : $event->circles()->pluck('circles.id')->all();

            return array_values(array_filter(array_unique($ids)));
        }

        return array_values(array_filter([(string) $event->circle_id]));
    }

    public function myRegistrationRequests(Request $request)
    {
        $items = EventRegistrationRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['event', 'occurrence'])
            ->latest('created_at')
            ->get()
            ->map(fn ($r) => [
                'request_id' => $r->id,
                'event_id' => $r->event_id,
                'occurrence_id' => $r->occurrence_id,
                'status' => $r->status,
                'admin_note' => $r->admin_note,
                'registration_id' => $r->registration_id,
                'created_at' => optional($r->created_at)->toISOString(),
            ]);

        return $this->success(['items' => $items], 'Registration requests fetched successfully.');
    }

    public function adminRegistrationRequests(Request $request)
    {
        $query = EventRegistrationRequest::query()
            ->with(['event.circle', 'event.circles.cityRef', 'occurrence', 'user.circleMemberships.circle', 'registration'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->event_id, fn ($q, $v) => $q->where('event_id', $v))
            ->when($request->occurrence_id, fn ($q, $v) => $q->where('occurrence_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->search, function ($q, $term): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('request_reason', 'ilike', $like)
                        ->orWhere('admin_note', 'ilike', $like)
                        ->orWhereHas('user', function ($userQuery) use ($like): void {
                            $userQuery->where('display_name', 'ilike', $like)
                                ->orWhere('first_name', 'ilike', $like)
                                ->orWhere('last_name', 'ilike', $like)
                                ->orWhere('email', 'ilike', $like)
                                ->orWhere('phone', 'ilike', $like)
                                ->orWhere('company_name', 'ilike', $like);
                        })
                        ->orWhereHas('event', fn ($eventQuery) => $eventQuery->where('title', 'ilike', $like));
                });
            });

        $summary = [
            'pending' => EventRegistrationRequest::query()->where('status', 'pending')->count(),
            'approved' => EventRegistrationRequest::query()->where('status', 'approved')->count(),
            'rejected' => EventRegistrationRequest::query()->where('status', 'rejected')->count(),
            'total' => EventRegistrationRequest::query()->count(),
        ];

        $items = $query->latest('created_at')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)))
            ->withQueryString();

        return $this->success([
            'summary' => $summary,
            'items' => $items->getCollection()->map(fn (EventRegistrationRequest $joiningRequest) => $this->eventJoiningRequestPayload($joiningRequest))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ], 'Event joining requests fetched successfully.');
    }

    public function approveRegistrationRequest(Request $request, string $requestId)
    {
        $r = EventRegistrationRequest::query()->findOrFail($requestId);
        $r->forceFill(['status' => 'approved', 'admin_note' => $request->input('admin_note', 'Approved for cross-circle event registration.'), 'approved_by_user_id' => $request->user()->id, 'approved_at' => now()])->save();
        Log::info('cross_circle_registration_request_approved', ['request_id' => $r->id, 'user_id' => $r->user_id, 'event_id' => $r->event_id, 'occurrence_id' => $r->occurrence_id]);
        $r->load(['event.circle', 'event.circles.cityRef', 'occurrence', 'user.circleMemberships.circle', 'registration']);

        return $this->success($this->eventJoiningRequestPayload($r), 'Registration request approved successfully.');
    }

    public function rejectRegistrationRequest(Request $request, string $requestId)
    {
        $data = $request->validate(['admin_note' => ['required', 'string', 'max:2000']]);
        $r = EventRegistrationRequest::query()->findOrFail($requestId);
        $r->forceFill(['status' => 'rejected', 'admin_note' => $data['admin_note'], 'rejected_by_user_id' => $request->user()->id, 'rejected_at' => now()])->save();
        Log::info('cross_circle_registration_request_rejected', ['request_id' => $r->id, 'user_id' => $r->user_id, 'event_id' => $r->event_id, 'occurrence_id' => $r->occurrence_id]);
        $r->load(['event.circle', 'event.circles.cityRef', 'occurrence', 'user.circleMemberships.circle', 'registration']);

        return $this->success($this->eventJoiningRequestPayload($r), 'Registration request rejected successfully.');
    }

    public function cancelRegistrationRequest(Request $request, string $requestId)
    {
        $r = EventRegistrationRequest::query()->where('user_id', $request->user()->id)->where('status', 'pending')->findOrFail($requestId);
        $r->forceFill(['status' => 'cancelled'])->save();

        return $this->success($r, 'Registration request cancelled successfully.');
    }

    public function visitorRegister(VisitorEventRegistrationRequest $request, string $eventId, string $occurrenceId)
    {
        $event = Event::query()->findOrFail($eventId);
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->findOrFail($occurrenceId);
        if (! $this->events->visitorRegistrationEnabled($event)) {
            return $this->error('Visitor registration is not enabled for this event.', 403);
        }

        $data = $request->validated();
        $inviterUserId = $this->resolveInviterUserId($request);
        $inviterCode = $this->extractInviterCode($request);
        if ($inviterUserId) {
            $data['invited_by_user_id'] = $inviterUserId;
            $data['invited_by_type'] = 'circle_member_peer';
        }
        if ($inviterCode) {
            $data['referral_code'] = $inviterCode;
        }

        $existingBeforeSubmit = $this->findDuplicateVisitorRegistration($event->id, $occurrence->id, $data);

        $registration = $this->registrations->registerVisitor(
            $event,
            $occurrence,
            $data,
            $request->input('source', 'visitor_app')
        );

        $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);

        $registration = $this->registrations->ensureVisitorRegistrationFormUrl($registration);
        Log::info('public_event_registration_payment_link_created', ['event_id' => $event->id, 'occurrence_id' => $occurrenceId, 'registration_id' => (string) $registration->id]);

        if ($this->registrationUsesZohoPaymentLink($registration)
            && in_array(strtolower((string) ($registration->payment_status ?? '')), ['pending', 'processing', 'failed', 'expired'], true)) {
            try {
                $syncResult = $this->eventPaymentSync->syncRegistrationPayment($registration, ['source' => 'visitor_register_api']);
                $registration = $syncResult['registration'];
            } catch (\Throwable $e) {
                Log::warning('public_event_registration_api_zoho_sync_failed', [
                    'registration_id' => (string) $registration->id,
                    'error' => $e->getMessage(),
                ]);
                $registration = $registration->fresh(['event.circle', 'event.circles.cityRef', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']) ?? $registration;
            }
        }

        $registration = $this->ensurePendingRegistrationPaymentUrl($registration, 'visitor_register_api');

        if ($this->registrationPaymentCompleted($registration)) {
            $registration = $this->registrationQr->ensureQrGenerated($registration);
        }

        $requiresPayment = (bool) ($registration->payment_required ?? false) && ! $this->registrationPaymentCompleted($registration);
        $message = $existingBeforeSubmit && $this->registrationPaymentCompleted($registration)
            ? 'Already registered. Payment completed.'
            : ($requiresPayment ? 'Payment required. Please complete payment.' : 'Visitor registered successfully.');

        return $this->success(
            $this->payments->responsePayload($registration),
            $message,
            $existingBeforeSubmit ? 200 : 201
        );
    }

    private function findDuplicateVisitorRegistration(string $eventId, string $occurrenceId, array $data): ?EventRegistration
    {
        return EventRegistration::query()
            ->where('event_id', $eventId)
            ->where('occurrence_id', $occurrenceId)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($data): void {
                $matched = false;
                if (! empty($data['visitor_email'])) {
                    $query->orWhereRaw('LOWER(visitor_email) = ?', [strtolower((string) $data['visitor_email'])]);
                    $matched = true;
                }
                if (! empty($data['visitor_phone'])) {
                    $query->orWhere('visitor_phone', $data['visitor_phone']);
                    $matched = true;
                }
                if (! $matched) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->latest('created_at')
            ->first();
    }

    private function ensurePendingRegistrationPaymentUrl(EventRegistration $registration, string $source): EventRegistration
    {
        if (! (bool) ($registration->payment_required ?? false)
            || $this->registrationPaymentCompleted($registration)
            || ! empty($this->registrationPaymentUrl($registration))
            || ($registration->payment_gateway === 'razorpay' && ! empty($registration->razorpay_order_id))) {
            return $registration;
        }

        Log::warning('event_registration_payment_url_missing_before_response', [
            'source' => $source,
            'registration_id' => (string) $registration->id,
            'event_id' => (string) $registration->event_id,
            'occurrence_id' => (string) $registration->occurrence_id,
            'payment_gateway' => $registration->payment_gateway,
            'payment_status' => $registration->payment_status,
        ]);

        try {
            return $this->payments->attachCheckout($registration->fresh(['event.circle', 'event.circles.cityRef', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']));
        } catch (\Throwable $e) {
            Log::error('event_registration_payment_url_regeneration_failed', [
                'source' => $source,
                'registration_id' => (string) $registration->id,
                'error' => $e->getMessage(),
            ]);

            $registration->forceFill(array_filter([
                'payment_gateway' => 'zoho_billing_payment_link',
                'payment_status' => 'pending',
                'status' => 'pending_payment',
                'zoho_invoice_sync_error' => $e->getMessage(),
            ], fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH))->save();

            return $registration->fresh(['event.circle', 'event.circles.cityRef', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']) ?? $registration;
        }
    }

    private function registrationPaymentUrl(EventRegistration $registration): ?string
    {
        return $registration->payment_url
            ?? $registration->checkout_url
            ?? $registration->zoho_checkout_url
            ?? $registration->zoho_payment_link_url
            ?? $registration->zoho_hosted_page_url
            ?? null;
    }

    private function registrationUsesZohoPaymentLink(EventRegistration $registration): bool
    {
        return ($registration->payment_gateway ?? '') === 'zoho_billing_payment_link'
            || ! empty($registration->zoho_payment_link_url)
            || ! empty($registration->zoho_checkout_url)
            || ! empty($registration->zoho_hosted_page_url);
    }

    private function registrationPaymentGateway(EventRegistration $registration): string
    {
        $gateway = strtolower((string) ($registration->payment_gateway ?: config('services.event_payment_gateway', 'zoho_billing_payment_link')));

        if ($gateway === '' || in_array($gateway, ['none', 'not_required', 'null'], true)) {
            return 'zoho_billing_payment_link';
        }

        return $gateway;
    }

    private function registrationPaymentCompleted(EventRegistration $registration): bool
    {
        return in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);
    }

    public function visitorRegisterAsUser(Request $request, string $eventId, string $occurrenceId)
    {
        $user = $request->user();
        $event = Event::query()->findOrFail($eventId);
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->findOrFail($occurrenceId);
        $context = ['user_id' => $user->id, 'event_id' => $event->id, 'occurrence_id' => $occurrence->id, 'event_circle_id' => $event->circle_id];
        Log::info('app_user_visitor_registration_start', $context);

        try {
            if ($this->isActiveCircleMember($event->circle_id, $user->id)) {
                return $this->success([
                    'direct_registration_available' => true,
                    'registration_api' => '/api/v1/events/'.$event->id.'/occurrences/'.$occurrence->id.'/register',
                ], 'You are already a member of this circle. Please use direct member registration.');
            }

            $existing = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->whereNull('deleted_at')
                ->latest('created_at')
                ->first();
            if ($existing) {
                Log::info('app_user_visitor_existing_registration_found', $context + ['registration_id' => (string) $existing->id]);
            }

            $registration = $this->registrations->registerAppUserVisitor(
                $event,
                $occurrence,
                $user,
                $request->input('source', 'app')
            );

            $referralCode = $this->extractInviterCode($request);
            $invitedByUserId = $this->resolveInvitedByUserId($referralCode, (string) $user->id);
            $this->attachInviterToRegistration($registration, $invitedByUserId, $request->input('invited_by_type'), $referralCode);
            if (! empty($registration->payment_url) || ! empty($registration->zoho_payment_link_url)) {
                Log::info('app_user_visitor_payment_link_created', $context + ['registration_id' => (string) $registration->id]);
            }
            Log::info('app_user_visitor_registration_success', $context + ['registration_id' => (string) $registration->id]);

            return $this->success(
                $this->payments->responsePayload($registration),
                'Payment is required to complete your event registration.',
                $existing ? 200 : 201
            );
        } catch (\Throwable $e) {
            Log::error('app_user_visitor_registration_failed', $context + ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function paymentStatus(string $registrationId)
    {
        $registration = EventRegistration::query()->with(['event', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])->findOrFail($registrationId);

        if ($this->registrationUsesZohoPaymentLink($registration)
            && in_array(strtolower((string) ($registration->payment_status ?? '')), ['pending', 'processing', 'failed', 'expired'], true)) {
            try {
                $syncResult = $this->eventPaymentSync->syncRegistrationPayment($registration, ['source' => 'payment_status_api']);
                $registration = $syncResult['registration'];
            } catch (\Throwable $e) {
                Log::warning('event_payment_status_api_zoho_sync_failed', [
                    'registration_id' => (string) $registration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $registration = $this->registrations->ensureVisitorRegistrationFormUrl($registration);
        $registration = $this->ensurePendingRegistrationPaymentUrl($registration, 'payment_status_api');

        if (in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true)) {
            $registration = $this->registrationQr->ensureQrGenerated($registration);
        }

        $visitorFormUrl = $registration->visitor_registration_form_url ?: url('/events/'.$registration->event_id.'/occurrences/'.$registration->occurrence_id.'/visitor-register?registration_id='.$registration->id);

        $gateway = ($registration->payment_required ?? false) ? $this->registrationPaymentGateway($registration) : null;
        $isPaid = in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);

        $payload = [
            'registration_id' => $registration->id,
            'payment_required' => (bool) ($registration->payment_required ?? false),
            'payment_gateway' => $gateway,
            'payment_status' => $registration->payment_status ?? ((bool) ($registration->payment_required ?? false) ? 'pending' : 'not_required'),
            'status' => $registration->status,
            'amount' => $registration->amount !== null ? (string) $registration->amount : null,
            'currency' => $registration->currency ?? 'INR',
            'razorpay_order_id' => $registration->razorpay_order_id ?? null,
            'razorpay_payment_id' => $registration->razorpay_payment_id ?? null,
            'payment_completed_at' => optional($registration->payment_completed_at)->toISOString(),
            'visitor_registration_form_url' => $visitorFormUrl,
            'form_url' => $visitorFormUrl,
            'qr_token' => $registration->qr_token ?? null,
            'qr_code_url' => ($registration->payment_required ?? false) && ! $isPaid
                ? null
                : $this->registrationQr->qrCodeUrl($registration),
            'qr_code_svg' => $registration->qr_code_svg ?? null,
            'invoice_number' => $isPaid ? ($registration->invoice_number ?? $registration->zoho_invoice_number ?? null) : null,
            'invoice_date' => $isPaid ? optional($registration->payment_completed_at ?? $registration->zoho_invoice_synced_at ?? $registration->created_at)->toDateString() : null,
            'zoho_invoice_id' => $isPaid ? ($registration->zoho_invoice_id ?? null) : null,
            'zoho_invoice_number' => $isPaid ? ($registration->zoho_invoice_number ?? null) : null,
            'zoho_invoice_url' => $isPaid ? ($registration->zoho_invoice_url ?? null) : null,
            'zoho_invoice_pdf_url' => $isPaid ? ($registration->zoho_invoice_pdf_url ?? null) : null,
            'zoho_invoice_status' => $isPaid ? ($registration->zoho_invoice_status ?? null) : null,
            'zoho_payment_status' => $isPaid ? ($registration->zoho_payment_status ?? null) : null,
            'zoho_payment_id' => $isPaid ? ($registration->zoho_payment_id ?? null) : null,
            'invoice_sync_error' => $isPaid ? ($registration->zoho_invoice_sync_error ?? null) : null,
            'invoice' => array_merge($this->invoicePayload($registration), ['invoice_sync_error' => $isPaid ? ($registration->zoho_invoice_sync_error ?? null) : null]),
        ];

        if ((bool) ($registration->payment_required ?? false) && ! $isPaid) {
            if ($gateway === 'razorpay' && ! empty($registration->razorpay_order_id)) {
                $payload['razorpay'] = $this->razorpayPayments->checkoutPayload($registration);
                $payload['razorpay_order_id'] = $registration->razorpay_order_id;
            }
        }

        return $this->success($payload, 'Payment status fetched successfully.');
    }

    public function verifyRazorpay(VerifyEventRazorpayPaymentRequest $request, string $registrationId)
    {
        $data = $request->validated();

        $registration = EventRegistration::query()->with([
            'event.circle',
            'event.circles.cityRef',
            'occurrence',
            'user',
            'invitedByUser',
            'businessCategoryMain',
            'businessCategorySub',
        ])->find($registrationId);

        // 1. Registration exists
        if (! $registration) {
            return $this->error('Event registration not found.', 404);
        }

        // 2. Registration belongs to correct Event/occurrence
        if (! $registration->event || ! $registration->occurrence) {
            return $this->error('Registration event or occurrence not found.', 422);
        }

        if ($request->filled('event_id') && (string) $registration->event_id !== (string) $request->input('event_id')) {
            return $this->error('Registration does not belong to the specified event.', 422);
        }

        if ($request->filled('occurrence_id') && (string) $registration->occurrence_id !== (string) $request->input('occurrence_id')) {
            return $this->error('Registration does not belong to the specified event occurrence.', 422);
        }

        $authUser = $request->user();
        if ($registration->user_id && $authUser && (string) $registration->user_id !== (string) $authUser->id && ! $this->events->canViewAttendance($registration->event, $authUser)) {
            return $this->error('You are not authorized to verify payment for this registration.', 403);
        }

        if ($registration->status === 'cancelled') {
            return $this->error('This registration has been cancelled.', 422);
        }

        if (! (bool) ($registration->payment_required ?? false)) {
            return $this->error('This registration does not require payment.', 422);
        }

        // 3. payment_gateway = razorpay
        if (($registration->payment_gateway ?? '') !== 'razorpay') {
            return $this->error('Payment gateway for this registration is not Razorpay.', 422);
        }

        // 4. Stored Razorpay order ID matches callback order ID
        if (empty($registration->razorpay_order_id) || (string) $registration->razorpay_order_id !== (string) $data['razorpay_order_id']) {
            return $this->error('Payment order does not match this registration.', 422);
        }

        // Prevent duplicate replay of same payment ID across different registrations
        $otherPaid = EventRegistration::query()
            ->where('razorpay_payment_id', $data['razorpay_payment_id'])
            ->where('id', '!=', $registration->id)
            ->where('payment_status', 'paid')
            ->exists();

        if ($otherPaid) {
            return $this->error('This Razorpay payment has already been applied to another registration.', 422);
        }

        // 10. Idempotency / Payment has not already been finalized
        if (in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true)) {
            if ((string) ($registration->razorpay_payment_id ?? '') === (string) $data['razorpay_payment_id'] || empty($registration->razorpay_payment_id)) {
                return $this->success(new EventRegistrationResource($registration), 'Payment already verified.');
            }

            return $this->error('This registration has already been finalized with another payment.', 422);
        }

        // 5. Cryptographic HMAC signature check using key_secret
        if (! $this->razorpayPayments->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            Log::warning('Razorpay event payment signature verification failed', [
                'registration_id' => $registrationId,
                'order_id' => $data['razorpay_order_id'],
                'payment_id' => $data['razorpay_payment_id'],
            ]);

            return $this->error('Invalid payment signature.', 422);
        }

        // 6, 7, 8, 9. Server-side payment validation against Razorpay API
        $expectedAmount = (float) ($registration->amount ?? 0);
        $expectedCurrency = (string) ($registration->currency ?? 'INR');
        $apiValidation = $this->razorpayPayments->validatePaymentDetails(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $expectedAmount,
            $expectedCurrency
        );

        if (! ($apiValidation['verified'] ?? true)) {
            return $this->error($apiValidation['error'] ?? 'Payment validation failed.', 422);
        }

        // Only after all checks pass: call EventRazorpayPaymentFinalizer
        $registration = $this->paymentFinalizer->markPaid($registration, [
            'razorpay_payment_id' => $data['razorpay_payment_id'],
            'razorpay_signature' => $data['razorpay_signature'],
            'razorpay_payment_status' => 'captured',
        ]);

        return $this->success(new EventRegistrationResource($registration), 'Payment verified successfully.');
    }

    public function invoice(Request $request, string $registrationId)
    {
        $registration = EventRegistration::query()->with(['event', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])->findOrFail($registrationId);

        if ($registration->user_id && $request->user() && (string) $registration->user_id !== (string) $request->user()->id && ! $this->events->canViewAttendance($registration->event, $request->user())) {
            return $this->error('You are not authorized to view this invoice.', 403);
        }

        if ($registration->user_id && ! $request->user() && ! in_array($registration->registration_type, ['visitor', 'app_user_visitor'], true) && ! in_array($registration->source, ['visitor_app', 'web_form', 'visitor_web', 'api'], true)) {
            return $this->error('Authentication is required to view this invoice.', 401);
        }

        $isPaid = in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);
        if ((bool) ($registration->payment_required ?? false) && ! $isPaid) {
            return $this->error('Invoice is not available because payment is pending.', 422);
        }

        return $this->success($this->invoicePayload($registration), 'Invoice fetched successfully.');
    }

    public function publicOccurrence(string $eventId, string $occurrenceId)
    {
        $occurrence = EventOccurrence::query()
            ->with(['event.circle', 'event.circles.cityRef'])
            ->where('event_id', $eventId)
            ->findOrFail($occurrenceId);
        $event = $occurrence->event;

        if (! ($event->is_public || $event->visibility === 'public' || $this->events->visitorRegistrationEnabled($event))) {
            return $this->error('Event is not available for public registration.', 403);
        }

        return $this->success([
            'event_id' => $event->id,
            'occurrence_id' => $occurrence->id,
            'title' => $event->title,
            'description' => $event->description,
            'start_at' => optional($occurrence->start_at)->toISOString(),
            'end_at' => optional($occurrence->end_at)->toISOString(),
            'location_text' => $event->location_text,
            'mode' => $event->mode,
            'online_meeting_url' => $event->online_meeting_url,
            'is_paid' => (bool) $event->is_paid,
            'ticket_price' => (string) ($event->ticket_price ?? '0.00'),
            'currency' => $this->payments->currency($event),
            'visitor_registration_enabled' => $this->events->visitorRegistrationEnabled($event),
        ], 'Public event fetched successfully.');
    }

    public function publicRegistrationForm(string $eventId, string $occurrenceId)
    {
        $occurrence = EventOccurrence::query()
            ->with(['event.circle', 'event.circles.cityRef'])
            ->where('event_id', $eventId)
            ->findOrFail($occurrenceId);
        $event = $occurrence->event;

        if (! ($event->is_public || $event->visibility === 'public' || $this->events->visitorRegistrationEnabled($event))) {
            return $this->error('Event is not available for public registration.', 403);
        }

        return $this->success($this->publicRegistrationFormPayload($event, $occurrence), 'Public event registration form fetched successfully.');
    }

    public function publicRegister(VisitorEventRegistrationRequest $request, string $eventId, string $occurrenceId)
    {
        $event = Event::query()->findOrFail($eventId);
        if (! $this->events->visitorRegistrationEnabled($event)) {
            return $this->error('Visitor registration is not enabled for this event.', 403);
        }

        $registration = $this->registrations->registerVisitor(
            $event,
            EventOccurrence::query()->findOrFail($occurrenceId),
            $request->validated(),
            'api'
        );
        Log::info('public_event_registration_payment_link_created', ['event_id' => $event->id, 'occurrence_id' => $occurrenceId, 'registration_id' => (string) $registration->id]);
        $requiresPayment = (bool) ($registration->payment_required ?? false);

        return $this->success(
            $this->payments->responsePayload($registration),
            $requiresPayment ? 'Payment required. Please complete payment.' : 'Visitor registered successfully.',
            201
        );
    }

    public function myEventRegistrations(Request $request)
    {
        $user = $request->user();
        Log::info('my_event_registrations_fetch_start', ['user_id' => $user->id]);

        if ($user->email) {
            EventRegistration::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(visitor_email) = ?', [strtolower($user->email)])
                ->update(['user_id' => $user->id]);
        }

        $query = EventRegistration::query()
            ->with(['event.circle', 'event.circles.cityRef', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id);
                if ($user->email) {
                    $q->orWhereRaw('LOWER(visitor_email) = ?', [strtolower($user->email)]);
                }
                if ($user->phone) {
                    $q->orWhere('visitor_phone', $user->phone);
                }
            });

        foreach (['status', 'payment_status', 'registration_type', 'event_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->boolean('upcoming')) {
            $query->whereHas('occurrence', fn ($q) => $q->where('start_at', '>=', now()));
        }
        if ($request->boolean('past')) {
            $query->whereHas('occurrence', fn ($q) => $q->where('end_at', '<', now())->orWhere(fn ($inner) => $inner->whereNull('end_at')->where('start_at', '<', now())));
        }

        $items = $query->latest('registered_at')->paginate(max(1, min((int) $request->input('per_page', 20), 100)));
        $qr = app(EventQrService::class);
        $mapped = $items->getCollection()->map(fn (EventRegistration $registration) => $this->myEventRegistrationPayload($registration, $qr))->values();
        Log::info('my_event_registrations_fetch_success', ['user_id' => $user->id, 'total' => $items->total()]);

        return $this->success([
            'items' => $mapped,
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ], 'My event registrations fetched successfully.');
    }

    public function myRegistrations(Request $request)
    {
        $items = EventRegistration::query()
            ->with(['event.circle', 'event.circles.cityRef', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])
            ->where('user_id', $request->user()->id)
            ->latest('registered_at')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        $qr = app(EventQrService::class);

        return $this->success([
            'total' => $items->total(),
            'items' => $items->getCollection()->map(fn (EventRegistration $registration) => [
                'registration_id' => $registration->id,
                'event_id' => $registration->event_id,
                'occurrence_id' => $registration->occurrence_id,
                'title' => $registration->event?->title,
                'start_at' => optional($registration->occurrence?->start_at)->toISOString(),
                'end_at' => optional($registration->occurrence?->end_at)->toISOString(),
                'location_text' => $registration->event?->location_text,
                'mode' => $registration->event?->mode,
                'status' => $registration->status,
                'checkin_status' => $registration->checkin_status,
                'payment_gateway' => ($registration->payment_required ?? false) ? $this->registrationPaymentGateway($registration) : null,
                'payment_status' => $registration->payment_status ?? null,
                'razorpay_order_id' => $registration->razorpay_order_id ?? null,
                'payment_url' => $registration->payment_url ?? $registration->zoho_payment_link_url ?? $registration->zoho_hosted_page_url ?? null,
                'checkout_url' => $registration->payment_url ?? $registration->zoho_payment_link_url ?? $registration->zoho_hosted_page_url ?? null,
                'qr_code_url' => ($registration->payment_required ?? false) && ($registration->payment_status ?? null) !== 'paid' ? null : ($registration->qr_code_path ? $qr->url($registration->qr_code_path) : $registration->qr_code_url),
                'qr_status' => $registration->qr_status,
                'attendee_type' => $registration->user_id ? 'member' : 'visitor',
            ])->values(),
        ], 'My registrations fetched successfully.');
    }

    public function qr(Request $request, string $registrationId)
    {
        $registration = EventRegistration::query()->where('user_id', $request->user()->id)->findOrFail($registrationId);

        return $this->success($this->registrations->qrDetails($registration), 'QR details fetched successfully.');
    }

    public function scan(ScanEventQrRequest $request)
    {
        $authUser = $request->user();
        $qrToken = trim((string) $request->input('qr_token'));

        if ($authUser instanceof ScanAppUser) {
            return $this->scannerScanResponse(
                $this->scannerQrScans->scan($authUser, $qrToken, $this->deviceInfo($request))
            );
        }

        if ($authUser instanceof User) {
            $registration = $this->checkins->scan($qrToken, $authUser, (bool) $request->boolean('force'));

            return $this->success(new EventRegistrationResource($registration), 'Attendance marked successfully.');
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    private function scannerScanResponse(array $result)
    {
        if ($result['success']) {
            return $this->success($result['data'], $result['message'], $result['status']);
        }

        if ($result['errors'] !== null) {
            return $this->error($result['message'], $result['status'], $result['errors']);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], $result['status']);
    }

    private function deviceInfo(Request $request): ?array
    {
        $deviceInfo = $request->input('device_info');

        return is_array($deviceInfo) ? $deviceInfo : null;
    }

    public function attendance(Request $request, string $eventId)
    {
        $event = Event::query()->findOrFail($eventId);
        if (! $this->events->canViewAttendance($event, $request->user())) {
            return $this->error('You are not authorized to view attendance.', 403);
        }

        return $this->success(
            $this->events->attendanceReport($event, $request->only(['occurrence_id', 'status', 'checkin_status', 'attendee_type', 'search'])),
            'Attendance fetched successfully.'
        );
    }

    public function invoices(Request $request)
    {
        $q = EventRegistration::query()->with(['event', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])->where('payment_status', 'paid')->latest('created_at');
        if ($request->filled('payment_status')) {
            $q->where('payment_status', $request->input('payment_status'));
        }
        if ($request->filled('event_id')) {
            $q->where('event_id', $request->input('event_id'));
        }
        if ($request->filled('occurrence_id')) {
            $q->where('occurrence_id', $request->input('occurrence_id'));
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('visitor_email')) {
            $q->where('visitor_email', $request->input('visitor_email'));
        }

        $items = $q->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        return $this->success([
            'total' => $items->total(),
            'items' => $items->getCollection()->map(fn (EventRegistration $r) => $this->invoiceListItem($r))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ], 'Event invoices fetched successfully.');
    }

    public function invoiceDetails(Request $request, string $registrationId)
    {
        $r = EventRegistration::query()->with(['event', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub'])->findOrFail($registrationId);
        if ($r->user_id && $request->user() && $r->user_id !== $request->user()->id && ! $this->events->canViewAttendance($r->event, $request->user())) {
            return $this->error('You are not authorized to view this invoice.', 403);
        }

        $isPaid = in_array(strtolower((string) ($r->payment_status ?? '')), ['paid', 'success', 'completed'], true);
        if ((bool) ($r->payment_required ?? false) && ! $isPaid) {
            return $this->error('Invoice is not available because payment is pending.', 422);
        }

        return $this->success(array_merge($this->invoiceListItem($r), [
            'event' => [
                'title' => $r->event?->title,
                'location_text' => $r->event?->location_text,
                'mode' => $r->event?->mode,
                'start_at' => optional($r->occurrence?->start_at)->toISOString(),
                'end_at' => optional($r->occurrence?->end_at)->toISOString(),
            ],
            'qr_code_url' => $r->qr_code_path ? app(EventQrService::class)->url($r->qr_code_path) : $r->qr_code_url,
            'invoice_sync_error' => $r->zoho_invoice_sync_error,
        ]), 'Event invoice fetched successfully.');
    }

    private function invoiceListItem(EventRegistration $registration): array
    {
        $attendeeName = $registration->user?->display_name ?: trim(($registration->user?->first_name ?? '').' '.($registration->user?->last_name ?? '')) ?: $registration->visitor_name;
        $email = $registration->user?->email ?: $registration->visitor_email;
        $phone = $registration->user?->phone ?: $registration->visitor_phone;
        $isPaid = in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);

        return [
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'event_id' => $registration->event_id,
            'event_title' => $registration->event?->title,
            'occurrence_id' => $registration->occurrence_id,
            'attendee_name' => $attendeeName,
            'email' => $email,
            'phone' => $phone,
            'payment_status' => $registration->payment_status ?? ($registration->payment_required ? 'pending' : 'not_required'),
            'payment_gateway' => $registration->payment_gateway,
            'razorpay_order_id' => $registration->razorpay_order_id,
            'razorpay_payment_id' => $isPaid ? $registration->razorpay_payment_id : null,
            'zoho_payment_link_id' => $registration->zoho_payment_link_id,
            'amount' => $registration->amount !== null ? (string) $registration->amount : null,
            'currency' => $registration->currency ?? 'INR',
            'invoice_number' => $isPaid ? ($registration->invoice_number ?? $registration->zoho_invoice_number) : null,
            'invoice_date' => $isPaid ? optional($registration->payment_completed_at ?? $registration->zoho_invoice_synced_at ?? $registration->created_at)->toDateString() : null,
            'zoho_invoice_id' => $isPaid ? $registration->zoho_invoice_id : null,
            'zoho_invoice_number' => $isPaid ? $registration->zoho_invoice_number : null,
            'zoho_invoice_status' => $isPaid ? $registration->zoho_invoice_status : null,
            'zoho_invoice_url' => $isPaid ? $registration->zoho_invoice_url : null,
            'zoho_invoice_pdf_url' => $isPaid ? $registration->zoho_invoice_pdf_url : null,
            'zoho_invoice_sync_error' => $isPaid ? $registration->zoho_invoice_sync_error : null,
            'zoho_payment_id' => $isPaid ? $registration->zoho_payment_id : null,
            'paid_at' => $isPaid ? optional($registration->payment_completed_at)->toISOString() : null,
            'qr_code_url' => $isPaid ? ($registration->qr_code_path ? app(EventQrService::class)->url($registration->qr_code_path) : $registration->qr_code_url) : null,
            'visitor_designation' => $registration->visitor_designation ?? data_get($registration->metadata, 'visitor_designation'),
            'visitor_business_category_id' => $registration->visitor_business_category_id ?? data_get($registration->metadata, 'visitor_business_category_id'),
            'visitor_business_category' => $registration->visitor_business_category ?? data_get($registration->metadata, 'visitor_business_category'),
            'visitor_business_category_main_id' => $registration->visitor_business_category_main_id ?? data_get($registration->metadata, 'visitor_business_category_main_id'),
            'visitor_business_category_sub_id' => $registration->visitor_business_category_sub_id ?? data_get($registration->metadata, 'visitor_business_category_sub_id') ?? $registration->visitor_business_category_id ?? data_get($registration->metadata, 'visitor_business_category_id'),
            'business_category_main' => $registration->businessCategoryMainPayload(),
            'business_category_sub' => $registration->businessCategorySubPayload(),
            'visitor_business_website' => $registration->visitor_business_website ?? data_get($registration->metadata, 'visitor_business_website'),
            'visitor_business_brief' => $registration->visitor_business_brief ?? data_get($registration->metadata, 'visitor_business_brief'),
            'invited_by_type' => $registration->invited_by_type ?? data_get($registration->metadata, 'invited_by_type'),
            'invited_by_user_id' => $registration->invited_by_user_id ?? data_get($registration->metadata, 'invited_by_user_id'),
            'invited_by_user' => $this->invitedByUserPayload($registration->invitedByUser),
            'created_at' => optional($registration->created_at)->toISOString(),
        ];
    }

    private function publicRegistrationFormPayload(Event $event, EventOccurrence $occurrence, bool $includeCategories = true): array
    {
        return [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'name' => $event->title,
                'description' => $event->description,
                'basic_details' => [
                    'event_type' => $event->event_type,
                    'event_category' => $event->event_category,
                    'mode' => $event->mode,
                    'circle' => $event->circle ? [
                        'id' => $event->circle->id,
                        'name' => $event->circle->name ?? null,
                    ] : null,
                ],
                'start_at' => optional($occurrence->start_at ?? $event->start_at)->toISOString(),
                'end_at' => optional($occurrence->end_at ?? $event->end_at)->toISOString(),
                'location_text' => $event->location_text,
                'mode' => $event->mode,
                'online_meeting_url' => $event->online_meeting_url,
                'is_paid' => (bool) $event->is_paid,
                'ticket_price' => (string) ($event->ticket_price ?? '0.00'),
                'currency' => $this->payments->currency($event),
                'visitor_registration_enabled' => $this->events->visitorRegistrationEnabled($event),
            ],
            'occurrence' => [
                'id' => $occurrence->id,
                'event_id' => $occurrence->event_id,
                'occurrence_date' => optional($occurrence->occurrence_date)->toDateString(),
                'start_at' => optional($occurrence->start_at)->toISOString(),
                'end_at' => optional($occurrence->end_at)->toISOString(),
                'status' => $occurrence->status,
                'sequence' => $occurrence->sequence,
                'registration_limit' => $occurrence->registration_limit,
                'registered_count' => $occurrence->registered_count,
                'metadata' => $occurrence->metadata,
            ],
            'categories' => $includeCategories ? $this->publicRegistrationCategories() : null,
            'submit_url' => url('/api/v1/public/events/'.$event->id.'/occurrences/'.$occurrence->id.'/register'),
            'web_form_url' => url('/events/'.$event->id.'/occurrences/'.$occurrence->id.'/visitor-register'),
        ];
    }

    private function publicRegistrationCategories(): array
    {
        $main = Schema::hasTable('circle_categories')
            ? CircleCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values()->all()
            : [];

        $sub = (Schema::hasTable('level4_categories') || Schema::hasTable('circle_category_level4'))
            ? CircleCategoryLevel4::query()->orderBy('name')->get(['id', 'name'])->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values()->all()
            : [];

        return [
            'main' => $main,
            'sub' => $sub,
        ];
    }

    private function eventJoiningRequestPayload(EventRegistrationRequest $joiningRequest): array
    {
        $user = $joiningRequest->user;
        $userCircle = $user?->circleMemberships?->first()?->circle;
        $event = $joiningRequest->event;
        $occurrence = $joiningRequest->occurrence;
        $registration = $joiningRequest->registration;

        return [
            'id' => $joiningRequest->id,
            'status' => $joiningRequest->status,
            'request_reason' => $joiningRequest->request_reason,
            'admin_note' => $joiningRequest->admin_note,
            'created_at' => optional($joiningRequest->created_at)->toISOString(),
            'approved_at' => optional($joiningRequest->approved_at)->toISOString(),
            'rejected_at' => optional($joiningRequest->rejected_at)->toISOString(),
            'user' => [
                'id' => $user?->id,
                'display_name' => $user?->display_name ?: trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
                'email' => $user?->email,
                'phone' => $user?->phone,
                'company_name' => $user?->company_name,
                'city' => $user?->city ?? $user?->city_of_residence,
            ],
            'user_circle' => $userCircle ? ['id' => $userCircle->id, 'name' => $userCircle->name] : null,
            'event_circle' => $event?->circle ? ['id' => $event->circle->id, 'name' => $event->circle->name] : null,
            'event' => [
                'id' => $event?->id,
                'title' => $event?->title,
                'event_type' => $event?->event_type,
                'mode' => $event?->mode,
                'location_text' => $event?->location_text,
            ],
            'occurrence' => [
                'id' => $occurrence?->id,
                'start_at' => optional($occurrence?->start_at)->toISOString(),
                'end_at' => optional($occurrence?->end_at)->toISOString(),
            ],
            'registration' => $registration ? [
                'id' => $registration->id,
                'registration_type' => $registration->registration_type,
                'payment_status' => $registration->payment_status,
                'payment_required' => (bool) ($registration->payment_required ?? false),
                'qr_code_url' => $registration->qr_code_url,
                'checkin_status' => $registration->checkin_status,
            ] : null,
        ];
    }

    private function invitedByUserPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'display_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'company_name' => $user->company_name,
            'designation' => $user->designation,
            'profile_photo_url' => $user->profile_photo_url ?? null,
        ];
    }

    private function isActiveCircleMember(?string $circleId, string $userId): bool
    {
        if (! $circleId) {
            return false;
        }

        $query = CircleMember::query()
            ->where('circle_id', $circleId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at');
        if (Schema::hasColumn('circle_members', 'status')) {
            $query->whereIn('status', CircleMember::activeStatuses());
        }
        if (Schema::hasColumn('circle_members', 'expires_at')) {
            $query->where(function ($q): void {
                $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString());
            });
        }

        return $query->exists();
    }

    private function myEventRegistrationPayload(EventRegistration $registration, EventQrService $qr): array
    {
        $qrUrl = ($registration->payment_required ?? false) && ($registration->payment_status ?? null) !== 'paid'
            ? null
            : ($registration->qr_code_path ? $qr->url($registration->qr_code_path) : $registration->qr_code_url);

        return [
            'registration_id' => $registration->id,
            'registration_type' => $registration->registration_type ?? null,
            'status' => $registration->status,
            'checkin_status' => $registration->checkin_status,
            'payment_required' => (bool) ($registration->payment_required ?? false),
            'payment_status' => $registration->payment_status,
            'amount' => $registration->amount !== null ? (string) $registration->amount : null,
            'currency' => $registration->currency ?? 'INR',
            'payment_url' => $registration->payment_url ?? $registration->zoho_payment_link_url ?? $registration->zoho_hosted_page_url ?? null,
            'qr_code_url' => $qrUrl,
            'qr_status' => $registration->qr_status,
            'zoho_invoice_id' => $registration->zoho_invoice_id,
            'zoho_invoice_number' => $registration->zoho_invoice_number,
            'zoho_invoice_status' => $registration->zoho_invoice_status,
            'invoice_url' => $registration->zoho_invoice_url,
            'invoice_pdf_url' => $registration->zoho_invoice_pdf_url,
            'registered_at' => optional($registration->registered_at)->toISOString(),
            'checked_in_at' => optional($registration->checked_in_at)->toISOString(),
            'visitor_designation' => $registration->visitor_designation ?? data_get($registration->metadata, 'visitor_designation'),
            'visitor_business_category_id' => $registration->visitor_business_category_id ?? data_get($registration->metadata, 'visitor_business_category_id'),
            'visitor_business_category' => $registration->visitor_business_category ?? data_get($registration->metadata, 'visitor_business_category'),
            'visitor_business_category_main_id' => $registration->visitor_business_category_main_id ?? data_get($registration->metadata, 'visitor_business_category_main_id'),
            'visitor_business_category_sub_id' => $registration->visitor_business_category_sub_id ?? data_get($registration->metadata, 'visitor_business_category_sub_id') ?? $registration->visitor_business_category_id ?? data_get($registration->metadata, 'visitor_business_category_id'),
            'business_category_main' => $registration->businessCategoryMainPayload(),
            'business_category_sub' => $registration->businessCategorySubPayload(),
            'visitor_business_website' => $registration->visitor_business_website ?? data_get($registration->metadata, 'visitor_business_website'),
            'visitor_business_brief' => $registration->visitor_business_brief ?? data_get($registration->metadata, 'visitor_business_brief'),
            'invited_by_type' => $registration->invited_by_type ?? data_get($registration->metadata, 'invited_by_type'),
            'invited_by_user_id' => $registration->invited_by_user_id ?? data_get($registration->metadata, 'invited_by_user_id'),
            'invited_by_user' => $this->invitedByUserPayload($registration->invitedByUser),
            'event' => [
                'id' => $registration->event?->id,
                'title' => $registration->event?->title,
                'event_type' => $registration->event?->event_type,
                'mode' => $registration->event?->mode,
                'location_text' => $registration->event?->location_text,
                'circle_id' => $registration->event?->circle_id,
                'circle_name' => $registration->event?->circle?->name,
            ],
            'occurrence' => [
                'id' => $registration->occurrence?->id,
                'start_at' => optional($registration->occurrence?->start_at)->toISOString(),
                'end_at' => optional($registration->occurrence?->end_at)->toISOString(),
            ],
        ];
    }

    private function invoicePayload(EventRegistration $registration): array
    {
        $isPaid = in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);

        return [
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'event_id' => $registration->event_id,
            'event_title' => $registration->event?->title,
            'invoice_number' => $isPaid ? ($registration->invoice_number ?? $registration->zoho_invoice_number ?? null) : null,
            'invoice_date' => $isPaid ? optional($registration->payment_completed_at ?? $registration->zoho_invoice_synced_at ?? $registration->created_at)->toDateString() : null,
            'amount' => $registration->amount !== null ? (string) $registration->amount : null,
            'currency' => $registration->currency ?? 'INR',
            'payment_status' => $registration->payment_status ?? ($registration->payment_required ? 'pending' : 'not_required'),
            'payment_gateway' => $registration->payment_gateway,
            'razorpay_order_id' => $registration->razorpay_order_id ?? null,
            'razorpay_payment_id' => $isPaid ? ($registration->razorpay_payment_id ?? null) : null,
            'qr_code_url' => $isPaid ? ($registration->qr_code_path ? app(EventQrService::class)->url($registration->qr_code_path) : $registration->qr_code_url) : null,
            'zoho_invoice_id' => $isPaid ? ($registration->zoho_invoice_id ?? null) : null,
            'zoho_invoice_number' => $isPaid ? ($registration->zoho_invoice_number ?? null) : null,
            'invoice_url' => $isPaid ? ($registration->invoice_url ?? $registration->zoho_invoice_url ?? null) : null,
            'invoice_pdf_url' => $isPaid ? ($registration->invoice_pdf_url ?? $registration->zoho_invoice_pdf_url ?? null) : null,
            'zoho_invoice_status' => $isPaid ? ($registration->zoho_invoice_status ?? null) : null,
            'invoice_balance' => $isPaid ? data_get($registration->metadata ?? [], 'invoice_balance') : null,
            'amount_paid' => $isPaid ? (data_get($registration->metadata ?? [], 'invoice_amount_paid') ?? (string) $registration->amount) : '0.00',
            'payment_applied' => $isPaid ? data_get($registration->metadata ?? [], 'invoice_payment_applied') : null,
        ];
    }

    public function checkinQr(string $qrToken)
    {
        return $this->success(['qr_token' => $qrToken], 'QR token resolved successfully.');
    }

    public function store(StoreEventRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $circleId = $data['circle_id'];

        $membership = CircleMember::where('circle_id', $circleId)
            ->where('user_id', $authUser->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->first();

        if (! $membership) {
            return $this->error('You are not a member of this circle', 403);
        }

        $adminRoles = ['founder', 'director', 'chair', 'vice_chair', 'secretary'];
        if (! in_array($membership->role, $adminRoles, true)) {
            return $this->error('You are not allowed to create events for this circle', 403);
        }

        $event = new Event($data);
        $event->created_by_user_id = $authUser->id;
        $event->save();
        $event->load(['circle', 'createdByUser', 'rsvps.user']);

        SendEventCreatedNotificationJob::dispatch($event->id)->afterResponse();

        return $this->success(new EventResource($event), 'Event created successfully', 201);
    }

    public function rsvp(EventRsvpRequest $request, string $id)
    {
        $event = Event::find($id);
        if (! $event) {
            return $this->error('Event not found', 404);
        }

        $rsvp = EventRsvp::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $request->user()->id],
            ['status' => $request->validated()['status']]
        );

        return $this->success(new EventRsvpResource($rsvp->load('user')), 'RSVP updated successfully');
    }

    public function checkin(EventCheckinRequest $request, string $id)
    {
        $event = Event::find($id);
        if (! $event) {
            return $this->error('Event not found', 404);
        }

        $targetUserId = $request->validated()['user_id'] ?? $request->user()->id;

        $rsvp = EventRsvp::where('event_id', $event->id)->where('user_id', $targetUserId)->first();
        if (! $rsvp) {
            return $this->error('RSVP not found', 404);
        }

        $rsvp->checked_in = true;
        $rsvp->checkin_at = now();
        $rsvp->save();

        return $this->success(new EventRsvpResource($rsvp->load('user')), 'Checked in successfully');
    }

    private function resolveInvitedByUserId(?string $code, ?string $currentUserId = null): ?string
    {
        if (blank($code)) {
            return null;
        }

        $code = trim($code);

        if (Str::isUuid($code)) {
            return ($currentUserId && $code === $currentUserId) ? null : $code;
        }

        $inviterUser = User::query()->where('id', $code)->first();
        if ($inviterUser) {
            return ($currentUserId && (string) $inviterUser->id === $currentUserId) ? null : (string) $inviterUser->id;
        }

        try {
            $referralInfo = app(ReferralService::class)->validateReferralCode($code);
            if ($referralInfo && ! empty($referralInfo['referrer_user_id'])) {
                $refUserId = (string) $referralInfo['referrer_user_id'];

                return ($currentUserId && $refUserId === $currentUserId) ? null : $refUserId;
            }
        } catch (\Throwable $e) {
            Log::warning('event_resolve_inviter_code_failed', ['code' => $code, 'error' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('users')) {
                foreach (['referral_code', 'ref_code', 'invite_code'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $u = DB::table('users')->whereRaw('UPPER('.$col.') = ?', [strtoupper($code)])->first();
                        if ($u && ! empty($u->id)) {
                            return ($currentUserId && (string) $u->id === $currentUserId) ? null : (string) $u->id;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore fallback error
        }

        return null;
    }

    private function resolveInviterUserId(Request $request): ?string
    {
        $code = $this->extractInviterCode($request);
        $user = $request->user();

        return $this->resolveInvitedByUserId($code, $user ? (string) $user->id : null);
    }

    private function extractInviterCode(Request $request): ?string
    {
        $code = $request->input('inviter_code')
            ?? $request->input('invited_by_referral_code')
            ?? $request->input('referral_code')
            ?? $request->input('invited_by')
            ?? $request->input('invited_by_user_id');

        if (! empty($code) && is_string($code)) {
            return trim($code);
        }

        return null;
    }

    private function attachInviterToRegistration(
        EventRegistration $registration,
        ?string $invitedByUserId,
        ?string $invitedByType = null,
        ?string $referralCode = null
    ): void {
        if (! $invitedByUserId && ! $referralCode) {
            return;
        }

        $type = $invitedByType ?: 'circle_member_peer';
        $updates = [];

        $hasInvitedColumn = Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'invited_by_user_id');
        if ($hasInvitedColumn && $invitedByUserId) {
            $updates['invited_by_user_id'] = $invitedByUserId;
            if (Schema::hasColumn('event_registrations', 'invited_by_type')) {
                $updates['invited_by_type'] = $type;
            }
        }

        $metadata = is_array($registration->metadata) ? $registration->metadata : [];
        if ($invitedByUserId) {
            $metadata['invited_by_user_id'] = $invitedByUserId;
            $metadata['inviter_user_id'] = $invitedByUserId;
        }
        if ($referralCode) {
            $metadata['inviter_code'] = $referralCode;
            $metadata['referral_code'] = $referralCode;
            $metadata['invited_by_referral_code'] = $referralCode;
        }
        $metadata['invited_by_type'] = $type;
        $updates['metadata'] = $metadata;

        $registration->forceFill($updates)->save();
    }

    private function attachInviterAttribution(
        EventRegistration $registration,
        ?string $inviterUserId,
        ?string $inviterCode = null
    ): EventRegistration {
        $this->attachInviterToRegistration($registration, $inviterUserId, 'circle_member_peer', $inviterCode);

        return $registration;
    }
}
