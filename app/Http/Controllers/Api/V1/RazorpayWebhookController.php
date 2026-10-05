<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\EventRegistration;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Circles\CircleJoinPaymentService;
use App\Services\Circles\CircleJoinRequestPaymentSyncService;
use App\Services\Events\EventRazorpayPaymentFinalizer;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly EventRazorpayPaymentFinalizer $eventPaymentFinalizer,
        private readonly CircleJoinRequestPaymentSyncService $circlePaymentSyncService,
        private readonly CircleJoinPaymentService $circleJoinPaymentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Razorpay-Signature');
        $payload = (string) $request->getContent();
        $secret = (string) config('razorpay.webhook_secret');

        if ($signature === '' || $secret === '') {
            Log::warning('Razorpay webhook signature or secret missing');

            return response()->json(['ok' => true], 403);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            Log::warning('Razorpay webhook signature mismatch');

            return response()->json(['ok' => true], 403);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            Log::warning('Razorpay webhook payload invalid');

            return response()->json(['ok' => true]);
        }
        Log::info('payment_webhook_received', [
            'gateway' => 'razorpay',
            'event' => $data['event'] ?? null,
        ]);

        $event = $data['event'] ?? '';

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $this->handlePaymentCaptured($data);
        }

        if ($event === 'payment.failed') {
            $this->handlePaymentFailed($data);
        }

        return response()->json(['ok' => true]);
    }

    private function filterEventRegistrationColumns(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }

    private function handlePaymentCaptured(array $payload): void
    {
        $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
        $orderId = $paymentEntity['order_id'] ?? ($payload['payload']['order']['entity']['id'] ?? null);

        if (! $orderId) {
            Log::warning('Razorpay webhook missing order id');

            return;
        }

        $eventRegistration = EventRegistration::query()->where('razorpay_order_id', $orderId)->first();
        if ($eventRegistration) {
            if (($eventRegistration->payment_status ?? null) === 'paid') {
                return;
            }

            $this->eventPaymentFinalizer->markPaid($eventRegistration, [
                'razorpay_payment_id' => $paymentEntity['id'] ?? null,
                'razorpay_payment_status' => $paymentEntity['status'] ?? 'captured',
                'razorpay_signature' => $payload['payload']['payment']['entity']['acquirer_data']['auth_code'] ?? null,
            ]);

            return;
        }

        $circleJoinRequest = CircleJoinRequest::query()->where('notes->razorpay_order_id', $orderId)->first();
        if ($circleJoinRequest) {
            $this->circleJoinPaymentService->finalizeFromWebhook(
                $circleJoinRequest,
                $orderId,
                (string) ($paymentEntity['id'] ?? '')
            );

            return;
        }

        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();

        if (! $payment) {
            Log::warning('Payment not found for Razorpay capture webhook', [
                'order_id' => $orderId,
            ]);

            return;
        }

        if ($payment->payment_type === Payment::TYPE_CIRCLE_PACKAGE || ($payment->circle_id && ! $payment->membership_plan_id)) {
            $this->handleCirclePackageCaptured($payment, $paymentEntity);

            return;
        }

        $planToSync = null;
        $lockedPaymentToSync = null;
        $updatedUser = null;

        DB::transaction(function () use ($payment, $paymentEntity, &$planToSync, &$lockedPaymentToSync, &$updatedUser): void {
            $lockedPayment = Payment::query()->where('id', $payment->id)->lockForUpdate()->first();
            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return;
            }

            $lockedPayment->update([
                'razorpay_payment_id' => $paymentEntity['id'] ?? null,
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => now(),
                'provider' => 'razorpay',
            ]);

            $user = User::query()->find($lockedPayment->user_id);
            $plan = MembershipPlan::query()->find($lockedPayment->membership_plan_id);

            if (! $user || ! $plan) {
                Log::error('Membership activation skipped due to missing user or plan', [
                    'payment_id' => $lockedPayment->id,
                    'user_id' => $lockedPayment->user_id,
                    'plan_id' => $lockedPayment->membership_plan_id,
                ]);

                return;
            }

            $planToSync = $plan;
            $lockedPaymentToSync = $lockedPayment;
            $updatedUser = $this->membershipService->activateMembership($user, $plan, $lockedPayment);
        });

        if ($planToSync instanceof MembershipPlan && $lockedPaymentToSync instanceof Payment && $updatedUser instanceof User) {
            // Resolve and sync circle membership if user has a pending fee circle request
            $circleToSync = CircleJoinRequest::query()
                ->where('user_id', $updatedUser->id)
                ->where('status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
                ->latest('created_at')
                ->value('circle_id');

            if ($circleToSync) {
                try {
                    $this->circlePaymentSyncService->markRequestPaid(
                        $updatedUser,
                        (string) $circleToSync,
                        $lockedPaymentToSync->paid_at ?? now()
                    );
                } catch (\Throwable $e) {
                    Log::error('Circle membership sync error on Razorpay webhook', [
                        'payment_id' => $lockedPaymentToSync->id,
                        'user_id' => $updatedUser->id,
                        'circle_id' => $circleToSync,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $this->membershipService->syncZohoInvoice($updatedUser, $planToSync, $lockedPaymentToSync);
            } catch (\Throwable $e) {
                Log::error('Zoho invoice sync error on Razorpay webhook', [
                    'payment_id' => $lockedPaymentToSync->id,
                    'user_id' => $updatedUser->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleCirclePackageCaptured(Payment $payment, array $paymentEntity): void
    {
        $paymentId = (string) ($paymentEntity['id'] ?? '');

        DB::transaction(function () use ($payment, $paymentId): void {
            $lockedPayment = Payment::query()->where('id', $payment->id)->lockForUpdate()->first();
            if (! $lockedPayment || $lockedPayment->status === Payment::STATUS_SUCCESS) {
                return;
            }

            // Must belong to a pending/created payment
            if ($lockedPayment->status !== Payment::STATUS_CREATED && $lockedPayment->status !== 'pending') {
                Log::warning('Razorpay capture webhook ignored: circle package payment is not pending', [
                    'order_id' => $lockedPayment->razorpay_order_id,
                    'status' => $lockedPayment->status,
                ]);

                return;
            }

            // Validate amounts match
            $expectedPaise = (int) round(((float) $lockedPayment->total_amount) * 100);
            $capturedPaise = (int) ($paymentEntity['amount'] ?? 0);
            if ($capturedPaise > 0 && $capturedPaise !== $expectedPaise) {
                Log::warning('Razorpay capture webhook amount mismatch for circle package', [
                    'order_id' => $lockedPayment->razorpay_order_id,
                    'expected' => $expectedPaise,
                    'received' => $capturedPaise,
                ]);

                return;
            }

            $now = now();
            $circleId = $lockedPayment->circle_id;
            $invoiceNumber = 'INV-CP-'.strtoupper(substr((string) $circleId, 0, 4)).'-'.strtoupper(substr(str_replace('-', '', (string) $lockedPayment->id), 0, 8));

            $lockedPayment->update([
                'razorpay_payment_id' => $paymentId,
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => $now,
                'provider' => 'razorpay',
                'zoho_invoice_id' => $lockedPayment->zoho_invoice_id ?: $invoiceNumber,
            ]);

            if ($lockedPayment->user_id && $circleId) {
                $circle = Circle::query()->find($circleId);
                $durationMonths = (int) ($circle?->circle_duration_months ?: 12);
                $expiresAt = $now->copy()->addMonths($durationMonths);

                $subscription = CircleSubscription::query()
                    ->where('user_id', $lockedPayment->user_id)
                    ->where('circle_id', $circleId)
                    ->where('reference_id', $lockedPayment->razorpay_order_id)
                    ->first();

                if (! $subscription) {
                    $subscription = CircleSubscription::query()
                        ->where('user_id', $lockedPayment->user_id)
                        ->where('circle_id', $circleId)
                        ->latest('created_at')
                        ->first();
                }

                if ($subscription) {
                    $subscription->update([
                        'status' => 'active',
                        'paid_amount' => $lockedPayment->total_amount,
                        'paid_currency' => $lockedPayment->currency ?: 'INR',
                        'paid_at' => $now,
                        'started_at' => $now,
                        'expires_at' => $expiresAt,
                        'reference_id' => $lockedPayment->razorpay_order_id,
                        'zoho_invoice_id' => $lockedPayment->zoho_invoice_id,
                    ]);
                } else {
                    CircleSubscription::query()->create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $lockedPayment->user_id,
                        'circle_id' => $circleId,
                        'status' => 'active',
                        'amount' => $lockedPayment->total_amount,
                        'paid_amount' => $lockedPayment->total_amount,
                        'currency_code' => $lockedPayment->currency ?: 'INR',
                        'paid_currency' => $lockedPayment->currency ?: 'INR',
                        'paid_at' => $now,
                        'started_at' => $now,
                        'expires_at' => $expiresAt,
                        'reference_id' => $lockedPayment->razorpay_order_id,
                        'zoho_invoice_id' => $lockedPayment->zoho_invoice_id,
                    ]);
                }

                $circleMember = CircleMember::query()
                    ->where('user_id', $lockedPayment->user_id)
                    ->where('circle_id', $circleId)
                    ->first();

                if (! $circleMember) {
                    CircleMember::query()->create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $lockedPayment->user_id,
                        'circle_id' => $circleId,
                        'status' => 'approved',
                        'joined_at' => $now,
                    ]);
                } else {
                    $circleMember->update([
                        'status' => 'approved',
                        'deleted_at' => null,
                        'left_at' => null,
                    ]);
                }

                $user = User::query()->find($lockedPayment->user_id);
                if ($user && empty($user->active_circle_id)) {
                    $user->update(['active_circle_id' => $circleId]);
                }
            }
        });
    }

    private function handlePaymentFailed(array $payload): void
    {
        $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
        $orderId = $paymentEntity['order_id'] ?? null;

        if (! $orderId) {
            Log::warning('Razorpay webhook missing order id for failed payment');

            return;
        }

        $eventRegistration = EventRegistration::query()->where('razorpay_order_id', $orderId)->first();
        if ($eventRegistration) {
            $eventRegistration->forceFill($this->filterEventRegistrationColumns([
                'razorpay_payment_id' => $paymentEntity['id'] ?? null,
                'razorpay_payment_status' => $paymentEntity['status'] ?? 'failed',
                'payment_status' => 'failed',
                'status' => 'payment_failed',
                'payment_failed_reason' => $paymentEntity['error_description'] ?? $paymentEntity['error_reason'] ?? 'Payment failed',
                'webhook_payload' => $payload,
            ]))->save();

            return;
        }

        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
        if ($payment) {
            $payment->update([
                'razorpay_payment_id' => $paymentEntity['id'] ?? null,
                'status' => Payment::STATUS_FAILED,
            ]);

            if ($payment->payment_type === Payment::TYPE_CIRCLE_PACKAGE || $payment->circle_id) {
                CircleSubscription::query()
                    ->where('user_id', $payment->user_id)
                    ->where('circle_id', $payment->circle_id)
                    ->where('reference_id', $orderId)
                    ->update(['status' => 'failed']);
            }
        }

        $circleJoinRequest = CircleJoinRequest::query()->where('notes->razorpay_order_id', $orderId)->first();
        if ($circleJoinRequest) {
            $notes = is_array($circleJoinRequest->notes) ? $circleJoinRequest->notes : [];
            $notes['payment_failed_at'] = now()->toIso8601String();
            $notes['payment_failed_reason'] = $paymentEntity['error_description'] ?? 'Payment failed';
            $circleJoinRequest->update(['notes' => $notes]);
        }
    }
}
