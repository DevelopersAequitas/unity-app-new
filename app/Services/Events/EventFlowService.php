<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\CircleMember;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\EventRegistrationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventFlowService
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventRegistrationService $registrations,
        private readonly EventRegistrationQrService $registrationQr,
        private readonly EventQrService $qr,
        private readonly EventRazorpayPaymentService $razorpay,
        private readonly EventRazorpayPaymentFinalizer $paymentFinalizer,
        private readonly EventPaymentService $payments,
    ) {}

    /**
     * Build the full dynamic flow payload for an event and user context.
     *
     * @return array{
     *     pricing: array{is_free: bool, member_price: float, guest_price: float, currency: string, applicable_price: float},
     *     user_context: array{membership_type: string, status: string, is_attending: bool, is_payment_completed: bool, ticket: ?array},
     *     action_button: array{action_type: string, label: string, is_enabled: bool, helper_text: string},
     *     venue: array{name: string, address: string, latitude: ?float, longitude: ?float},
     *     circle_name: ?string
     * }
     */
    public function getUserContextAndAction(Event $event, ?User $user): array
    {
        $metadata = $this->normalizedMetadata($event->metadata);
        $membershipType = $this->resolveMembershipType($event, $user);
        $pricing = $this->getPricingPayload($event, $membershipType, $metadata);
        $userContext = $this->getUserContextPayload($event, $user, $membershipType, $pricing);
        $actionButton = $this->getActionButtonPayload($event, $userContext, $pricing);
        $venue = $this->getVenuePayload($event, $metadata);

        return [
            'pricing' => $pricing,
            'user_context' => $userContext,
            'action_button' => $actionButton,
            'venue' => $venue,
            'circle_name' => $event->circle?->name,
        ];
    }

    /**
     * Resolves the user membership type relative to the event:
     * - OWN_CIRCLE: Member of the event's circle(s)
     * - CROSS_CIRCLE: Member of another circle
     * - GUEST: Not in any circle
     */
    public function resolveMembershipType(Event $event, ?User $user): string
    {
        if (! $user) {
            return 'GUEST';
        }

        $eventCircleIds = array_values(array_filter(array_unique(array_merge(
            [$event->circle_id],
            $event->relationLoaded('circles') ? $event->circles->pluck('id')->all() : $event->circles()->pluck('circles.id')->all()
        ))));

        $activeStatuses = array_unique(array_merge(CircleMember::activeStatuses(), ['approved', 'active']));

        if (! empty($eventCircleIds)) {
            $isOwnCircle = CircleMember::query()
                ->whereIn('circle_id', $eventCircleIds)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->whereIn('status', $activeStatuses)
                ->exists();

            if ($isOwnCircle) {
                return 'OWN_CIRCLE';
            }
        }

        $isAnyCircleMember = CircleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereIn('status', $activeStatuses)
            ->exists();

        return $isAnyCircleMember ? 'CROSS_CIRCLE' : 'GUEST';
    }

    /**
     * Calculate pricing payload according to event settings and user membership.
     *
     * @return array{is_free: bool, member_price: float, guest_price: float, currency: string, applicable_price: float}
     */
    public function getPricingPayload(Event $event, string $membershipType, ?array $metadata = null): array
    {
        $metadata ??= $this->normalizedMetadata($event->metadata);
        $isPaid = (bool) ($event->is_paid ?? false) || (float) ($event->ticket_price ?? 0) > 0;

        $ticketPrice = round(max((float) ($event->ticket_price ?? 0), 0), 2);
        $guestPrice = isset($metadata['guest_price']) && is_numeric($metadata['guest_price'])
            ? round((float) $metadata['guest_price'], 2)
            : $ticketPrice;

        $memberPrice = isset($metadata['member_price']) && is_numeric($metadata['member_price'])
            ? round((float) $metadata['member_price'], 2)
            : ($isPaid && (bool) data_get($metadata, 'free_for_members', true) ? 0.0 : $ticketPrice);

        $isFree = ! $isPaid || ($guestPrice <= 0 && $memberPrice <= 0);
        $currency = strtoupper((string) (data_get($metadata, 'currency') ?: 'INR'));

        $applicablePrice = match ($membershipType) {
            'OWN_CIRCLE' => $isFree ? 0.0 : $memberPrice,
            default => $isFree ? 0.0 : $guestPrice,
        };

        return [
            'is_free' => $isFree,
            'member_price' => $memberPrice,
            'guest_price' => $guestPrice,
            'currency' => $currency,
            'applicable_price' => $applicablePrice,
        ];
    }

    /**
     * Compute contextual user registration and attendance status.
     *
     * @return array{membership_type: string, status: string, is_attending: bool, is_payment_completed: bool, ticket: ?array}
     */
    public function getUserContextPayload(Event $event, ?User $user, string $membershipType, array $pricing): array
    {
        if (! $user) {
            return [
                'membership_type' => 'GUEST',
                'status' => 'NONE',
                'is_attending' => false,
                'is_payment_completed' => false,
                'ticket' => null,
            ];
        }

        $registration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        $joinRequest = EventRegistrationRequest::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        $isPaymentCompleted = false;
        $isAttending = false;

        if ($registration) {
            $isPaidStatus = in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true);
            $paymentRequired = (bool) ($registration->payment_required ?? false);

            if ($isPaidStatus || (! $paymentRequired && $registration->status === 'registered')) {
                $isPaymentCompleted = true;
                $isAttending = true;
            }
        }

        $status = 'NONE';
        if ($isAttending) {
            $status = 'CONFIRMED';
        } elseif ($registration && $registration->status === 'pending_payment') {
            $status = 'APPROVED';
        } elseif ($joinRequest) {
            $status = match (strtolower((string) $joinRequest->status)) {
                'approved' => 'APPROVED',
                'pending' => 'PENDING',
                'rejected' => 'REJECTED',
                default => 'NONE',
            };
        }

        $ticket = null;
        if ($isAttending && $registration) {
            $ticket = $this->formatTicketPayload($event, $registration);
        }

        return [
            'membership_type' => $membershipType,
            'status' => $status,
            'is_attending' => $isAttending,
            'is_payment_completed' => $isPaymentCompleted,
            'ticket' => $ticket,
        ];
    }

    /**
     * Compute action button properties according to the exact specification matrix.
     *
     * @return array{action_type: string, label: string, is_enabled: bool, helper_text: string}
     */
    public function getActionButtonPayload(Event $event, array $userContext, array $pricing): array
    {
        // 1. Confirmed / Paid -> Directly opens QR ticket
        if ($userContext['is_attending']) {
            return [
                'action_type' => 'VIEW_QR',
                'label' => 'View QR Ticket',
                'is_enabled' => true,
                'helper_text' => 'Show this QR at the venue entrance',
            ];
        }

        // 2. Check if event is closed / expired / sold out
        $isEventClosed = false;
        $closedReason = '';

        $statusLower = strtolower((string) ($event->status ?? 'scheduled'));
        if (in_array($statusLower, ['cancelled', 'completed', 'canceled'], true)) {
            $isEventClosed = true;
            $closedReason = 'This event is no longer active.';
        }

        $compareDate = $event->end_at ?? $event->start_at;
        if ($compareDate && Carbon::parse($compareDate)->isPast()) {
            $isEventClosed = true;
            $closedReason = 'This event has already ended.';
        }

        $registrationLimit = $event->registration_limit;
        if ($registrationLimit && $registrationLimit > 0) {
            $registeredCount = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->whereNull('deleted_at')
                ->count();
            if ($registeredCount >= $registrationLimit) {
                $isEventClosed = true;
                $closedReason = 'This event is sold out.';
            }
        }

        if ($isEventClosed) {
            return [
                'action_type' => 'EVENT_CLOSED',
                'label' => 'Event Closed',
                'is_enabled' => false,
                'helper_text' => $closedReason ?: 'Registration for this event is closed.',
            ];
        }

        // 3. Own Circle Member (Not yet attending)
        if ($userContext['membership_type'] === 'OWN_CIRCLE') {
            return [
                'action_type' => 'I_AM_ATTENDING',
                'label' => 'I am Attending',
                'is_enabled' => true,
                'helper_text' => 'Free entry for members of this circle',
            ];
        }

        // 4. Guest or Cross-Circle Member
        return match ($userContext['status']) {
            'PENDING' => [
                'action_type' => 'REQUEST_PENDING',
                'label' => 'Approval Pending',
                'is_enabled' => false,
                'helper_text' => 'We will notify you once the host approves your request',
            ],
            'APPROVED' => $pricing['applicable_price'] > 0 ? [
                'action_type' => 'PAY_NOW',
                'label' => 'Pay Now (₹'.number_format($pricing['applicable_price'], 0).')',
                'is_enabled' => true,
                'helper_text' => 'Your request has been approved! Complete payment to get your QR ticket.',
            ] : [
                'action_type' => 'I_AM_ATTENDING',
                'label' => 'Confirm Attendance',
                'is_enabled' => true,
                'helper_text' => 'Your request has been approved! Confirm your attendance to get your QR ticket.',
            ],
            'REJECTED' => [
                'action_type' => 'EVENT_CLOSED',
                'label' => 'Request Declined',
                'is_enabled' => false,
                'helper_text' => 'Your request to join this event was declined by the circle admin.',
            ],
            default => [
                'action_type' => 'SEND_REQUEST',
                'label' => 'Send Request',
                'is_enabled' => true,
                'helper_text' => 'Approval required by circle admin',
            ],
        };
    }

    /**
     * Compute venue payload.
     *
     * @return array{name: string, address: string, latitude: ?float, longitude: ?float}
     */
    public function getVenuePayload(Event $event, array $metadata): array
    {
        $venueName = data_get($metadata, 'venue_name') ?: ($event->location_text ?: 'Venue TBA');
        $address = data_get($metadata, 'address_line') ?: ($event->location_text ?: '');

        $latitude = isset($metadata['latitude']) && is_numeric($metadata['latitude']) ? (float) $metadata['latitude'] : null;
        $longitude = isset($metadata['longitude']) && is_numeric($metadata['longitude']) ? (float) $metadata['longitude'] : null;

        return [
            'name' => (string) $venueName,
            'address' => (string) $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Instant attendance confirmation for own-circle members.
     *
     * @return array{ticket_id: string, qr_code_data: string, qr_image_url: ?string}
     */
    public function attend(Event $event, User $user): array
    {
        $membershipType = $this->resolveMembershipType($event, $user);

        if ($membershipType !== 'OWN_CIRCLE') {
            throw ValidationException::withMessages([
                'membership' => 'Direct attendance confirmation is only available for members of this circle. Please send a join request.',
            ]);
        }

        $occurrence = $this->resolveActiveOccurrence($event);

        $registration = $this->registrations->registerMemberDirectNoPayment($event, $occurrence, $user, 'app');
        $registration = $this->registrationQr->ensureQrGenerated($registration);

        return $this->formatTicketPayload($event, $registration);
    }

    /**
     * Send join request for non-members & cross-circle members.
     *
     * @return array{status: string}
     */
    public function joinRequest(Event $event, User $user, ?string $note = null, ?string $referralCode = null): array
    {
        $occurrence = $this->resolveActiveOccurrence($event);

        $existingRegistration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->first();

        if ($existingRegistration && in_array(strtolower((string) ($existingRegistration->payment_status ?? '')), ['paid', 'success', 'completed'], true)) {
            throw ValidationException::withMessages([
                'event_id' => 'You are already registered and attending this event.',
            ]);
        }

        $existingRequest = EventRegistrationRequest::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        if ($existingRequest) {
            $statusUpper = strtoupper((string) $existingRequest->status);
            if (in_array($statusUpper, ['PENDING', 'APPROVED'], true)) {
                return [
                    'status' => $statusUpper,
                ];
            }
        }

        $inviterUserId = ! empty($referralCode) ? $this->resolveInviterUserId($referralCode, (string) $user->id) : null;

        $metadata = array_filter([
            'note' => $note,
            'source' => 'app',
            'referral_code' => $referralCode,
            'inviter_code' => $referralCode,
            'invited_by_referral_code' => $referralCode,
            'invited_by_user_id' => $inviterUserId,
        ], fn ($v) => $v !== null && $v !== '');

        $createData = [
            'event_id' => $event->id,
            'occurrence_id' => $occurrence?->id,
            'user_id' => $user->id,
            'event_circle_id' => $event->circle_id,
            'status' => 'pending',
            'request_reason' => $note,
            'metadata' => $metadata,
        ];

        if ($inviterUserId && Schema::hasTable('event_registration_requests') && Schema::hasColumn('event_registration_requests', 'invited_by_user_id')) {
            $createData['invited_by_user_id'] = $inviterUserId;
        }
        if (Schema::hasTable('event_registration_requests') && Schema::hasColumn('event_registration_requests', 'invited_by_type')) {
            $createData['invited_by_type'] = 'circle_member_peer';
        }

        $request = EventRegistrationRequest::query()->create($createData);

        Log::info('event_join_request_created', [
            'event_id' => (string) $event->id,
            'user_id' => (string) $user->id,
            'request_id' => (string) $request->id,
            'referral_code' => $referralCode,
            'invited_by_user_id' => $inviterUserId,
        ]);

        return [
            'status' => 'PENDING',
        ];
    }

    public function resolveInviterUserId(?string $code, ?string $currentUserId = null): ?string
    {
        if (blank($code)) {
            return null;
        }

        $code = trim($code);

        if (Str::isUuid($code)) {
            if ($currentUserId && $code === $currentUserId) {
                return null;
            }

            return User::query()->where('id', $code)->exists() ? $code : null;
        }

        $normalized = strtoupper($code);

        // 1. Resolve via ReferralService / referral_links
        try {
            $referralInfo = app(\App\Services\Referrals\ReferralService::class)->validateReferralCode($code);
            if ($referralInfo && ! empty($referralInfo['referrer_user_id'])) {
                $refUserId = (string) $referralInfo['referrer_user_id'];

                return ($currentUserId && $refUserId === $currentUserId) ? null : $refUserId;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2. Resolve via referraldata table
        try {
            if (Schema::hasTable('referraldata')) {
                $row = DB::table('referraldata')
                    ->whereRaw('UPPER(referral_code) = ?', [$normalized])
                    ->first(['referrer_user_id']);
                if ($row && ! empty($row->referrer_user_id)) {
                    $refUserId = (string) $row->referrer_user_id;

                    return ($currentUserId && $refUserId === $currentUserId) ? null : $refUserId;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 3. Resolve via users peer_id or referral columns
        try {
            if (Schema::hasTable('users')) {
                $candidateCols = ['peer_id', 'referral_code', 'ref_code', 'invite_code'];
                foreach ($candidateCols as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $u = DB::table('users')->whereRaw('UPPER('.$col.') = ?', [$normalized])->first(['id']);
                        if ($u && ! empty($u->id)) {
                            return ($currentUserId && (string) $u->id === $currentUserId) ? null : (string) $u->id;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Generate Razorpay order once request is approved.
     *
     * @return array{order_id: string, amount: int, currency: string, key_id: string}
     */
    public function createPaymentOrder(Event $event, User $user): array
    {
        $membershipType = $this->resolveMembershipType($event, $user);
        $pricing = $this->getPricingPayload($event, $membershipType);

        if ($pricing['applicable_price'] <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'Payment is not required for this event.',
            ]);
        }

        $occurrence = $this->resolveActiveOccurrence($event);

        $joinRequest = EventRegistrationRequest::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        $registration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->first();

        if ($registration && in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Payment has already been completed for this event.',
            ]);
        }

        // For cross-circle and guests, join request must be approved
        if ($membershipType !== 'OWN_CIRCLE' && (! $joinRequest || strtolower((string) $joinRequest->status) !== 'approved')) {
            throw ValidationException::withMessages([
                'status' => 'Payment order can only be created after your join request is approved by the host.',
            ]);
        }

        if (! $registration) {
            $registration = DB::transaction(function () use ($event, $occurrence, $user, $pricing, $membershipType, $joinRequest): EventRegistration {
                $reg = EventRegistration::query()->create([
                    'event_id' => $event->id,
                    'occurrence_id' => $occurrence?->id,
                    'user_id' => $user->id,
                    'source' => 'app',
                    'registration_type' => $membershipType === 'CROSS_CIRCLE' ? 'cross_circle_member' : 'visitor',
                    'registration_request_id' => $joinRequest ? (string) $joinRequest->id : null,
                    'status' => 'pending_payment',
                    'payment_status' => 'pending',
                    'payment_required' => true,
                    'payment_gateway' => 'razorpay',
                    'amount' => $pricing['applicable_price'],
                    'currency' => $pricing['currency'],
                ]);

                if ($joinRequest) {
                    $joinRequest->forceFill(['registration_id' => $reg->id])->save();
                }

                return $reg;
            });
        } else {
            $updates = [];
            if (! (bool) ($registration->payment_required ?? false)) {
                $updates['payment_required'] = true;
            }
            if (($registration->payment_gateway ?? '') !== 'razorpay') {
                $updates['payment_gateway'] = 'razorpay';
            }
            if ((float) ($registration->amount ?? 0) <= 0) {
                $updates['amount'] = $pricing['applicable_price'];
            }
            if (! empty($updates)) {
                $registration->forceFill($updates)->save();
            }
        }

        $registration = $this->razorpay->createOrder($registration);

        return [
            'order_id' => (string) $registration->razorpay_order_id,
            'amount' => (int) round(((float) $registration->amount) * 100),
            'currency' => (string) ($registration->currency ?: 'INR'),
            'key_id' => (string) config('razorpay.key_id'),
        ];
    }

    /**
     * Verify Razorpay payment and issue ticket.
     *
     * @param  array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string}  $paymentData
     * @return array{ticket_id: string, qr_code_data: string, qr_image_url: ?string}
     */
    public function verifyPayment(Event $event, User $user, array $paymentData): array
    {
        $orderId = (string) $paymentData['razorpay_order_id'];
        $paymentId = (string) $paymentData['razorpay_payment_id'];
        $signature = (string) $paymentData['razorpay_signature'];

        $registration = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('razorpay_order_id', $orderId)
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->first();

        if (! $registration) {
            $registration = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->whereNull('deleted_at')
                ->first();
        }

        if (! $registration) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => 'Event registration matching this payment order was not found.',
            ]);
        }

        // Idempotency check: if payment is already finalized
        if (in_array(strtolower((string) ($registration->payment_status ?? '')), ['paid', 'success', 'completed'], true)) {
            $registration = $this->registrationQr->ensureQrGenerated($registration);

            return $this->formatTicketPayload($event, $registration);
        }

        // Verify cryptographic HMAC signature
        if (! $this->razorpay->verifySignature($orderId, $paymentId, $signature)) {
            Log::warning('event_flow_payment_signature_failed', [
                'event_id' => (string) $event->id,
                'registration_id' => (string) $registration->id,
                'order_id' => $orderId,
            ]);

            throw ValidationException::withMessages([
                'razorpay_signature' => 'Invalid payment signature.',
            ]);
        }

        // Validate payment details via Razorpay API
        $expectedAmount = (float) ($registration->amount ?? 0);
        $expectedCurrency = (string) ($registration->currency ?? 'INR');
        $apiValidation = $this->razorpay->validatePaymentDetails($orderId, $paymentId, $expectedAmount, $expectedCurrency);

        if (! ($apiValidation['verified'] ?? true)) {
            throw ValidationException::withMessages([
                'payment' => $apiValidation['error'] ?? 'Payment validation failed.',
            ]);
        }

        // Mark registration as paid
        $registration = $this->paymentFinalizer->markPaid($registration, [
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'razorpay_payment_status' => 'captured',
        ]);

        $registration = $this->registrationQr->ensureQrGenerated($registration);

        return $this->formatTicketPayload($event, $registration);
    }

    /**
     * Format formatted ticket ID (e.g. TCK-88219-X).
     */
    public function formatTicketId(EventRegistration $registration): string
    {
        $cleanId = strtoupper(str_replace('-', '', (string) $registration->id));
        $part1 = substr($cleanId, 0, 5);
        $part2 = substr($cleanId, 5, 2);

        return "TCK-{$part1}-{$part2}";
    }

    /**
     * Format standard ticket data payload.
     *
     * @return array{ticket_id: string, qr_code_data: string, qr_image_url: ?string}
     */
    public function formatTicketPayload(Event $event, EventRegistration $registration): array
    {
        $ticketId = $this->formatTicketId($registration);
        $qrImageUrl = $registration->qr_code_url
            ?: $this->registrationQr->qrCodeUrl($registration)
            ?: $this->qr->url('event-qrcodes/'.$event->id.'/'.$registration->id.'.png');

        return [
            'ticket_id' => $ticketId,
            'qr_code_data' => "peers://ticket/{$event->id}/{$ticketId}",
            'qr_image_url' => $qrImageUrl,
        ];
    }

    private function resolveActiveOccurrence(Event $event): ?EventOccurrence
    {
        return $event->occurrences()
            ->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereNotIn('status', ['cancelled', 'deleted']);
            })
            ->orderBy('start_at')
            ->first() ?? $event->occurrences()->first();
    }

    private function normalizedMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($metadata)) {
            $metadata = (array) $metadata;
        }

        return is_array($metadata) ? $metadata : [];
    }
}
