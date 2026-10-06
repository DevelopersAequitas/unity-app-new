<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrderRequest;
use App\Http\Requests\Api\V1\VerifyPaymentRequest;
use App\Models\CircleJoinRequest;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\Circles\CircleJoinRequestPaymentSyncService;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly CircleJoinRequestPaymentSyncService $circlePaymentSyncService,
    ) {}

    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $planId = (string) $request->validated('membership_plan_id');

        $plan = MembershipPlan::query()
            ->where('id', $planId)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Membership plan not found.'], 404);
        }

        if ($plan->is_free) {
            return response()->json(['message' => 'Free plans do not require payment.'], 422);
        }

        // Check if user has an approved Circle request pending payment
        $pendingCircleRequest = CircleJoinRequest::query()
            ->where('user_id', $user->id)
            ->where('status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
            ->latest('created_at')
            ->first();

        if ($pendingCircleRequest) {
            $notes = is_array($pendingCircleRequest->notes) ? $pendingCircleRequest->notes : [];
            $approvedPlanId = (string) ($notes['membership_plan_id'] ?? '');

            if ($approvedPlanId !== '' && $approvedPlanId !== $planId) {
                return response()->json([
                    'message' => 'The selected membership plan does not match the Admin-approved package for your Circle request.',
                ], 422);
            }
        }

        $amounts = $this->membershipService->calculateAmounts($plan);
        $paymentId = (string) Str::uuid();
        $gstNumber = trim((string) ($request->validated('gst_number') ?: $user->gst_number ?: ''));

        if ($request->filled('gst_number') && $gstNumber !== '') {
            $user->update(['gst_number' => $gstNumber]);
        }

        $orderPayload = [
            'amount' => (int) round($amounts['total_amount'] * 100),
            'currency' => config('razorpay.currency', 'INR'),
            'receipt' => $paymentId,
        ];

        if ($gstNumber !== '') {
            $orderPayload['notes'] = [
                'gstin' => $gstNumber,
            ];
        }

        try {
            $api = new Api(config('razorpay.key_id'), config('razorpay.key_secret'));
            $order = $api->order->create($orderPayload);
        } catch (\Throwable $exception) {
            Log::error('Razorpay order creation failed', [
                'user_id' => $user?->id,
                'plan_id' => $planId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to create payment order.'], 500);
        }

        Payment::query()->create([
            'id' => $paymentId,
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'amount' => $amounts['total_amount'],
            'base_amount' => $amounts['base_amount'],
            'gst_percent' => $amounts['gst_percent'],
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
            'currency' => config('razorpay.currency', 'INR'),
            'provider' => 'razorpay',
            'razorpay_order_id' => $order['id'],
            'status' => Payment::STATUS_CREATED,
            'payment_type' => Payment::TYPE_MEMBERSHIP,
        ]);

        return response()->json([
            'order_id' => $order['id'],
            'amount' => (int) $order['amount'],
            'currency' => $order['currency'],
            'key_id' => config('razorpay.key_id'),
            'circle_join_request_id' => $pendingCircleRequest?->id,
            'circle_id' => $pendingCircleRequest?->circle_id,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'gst_percent' => (float) $plan->gst_percent,
                'gst_amount' => $amounts['gst_amount'],
                'total_amount' => $amounts['total_amount'],
                'duration_days' => (int) $plan->duration_days,
                'duration_months' => $plan->duration_months ? (int) $plan->duration_months : null,
                'is_free' => (bool) $plan->is_free,
            ],
        ]);
    }

    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();

        $payment = Payment::query()
            ->where('razorpay_order_id', $payload['razorpay_order_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment order not found.'], 404);
        }

        $expectedSignature = hash_hmac('sha256', $payload['razorpay_order_id'].'|'.$payload['razorpay_payment_id'], (string) config('razorpay.key_secret'));
        if (! hash_equals($expectedSignature, (string) $payload['razorpay_signature'])) {
            Log::warning('Razorpay signature verification failed', [
                'user_id' => $user?->id,
                'order_id' => $payload['razorpay_order_id'],
            ]);

            return response()->json(['message' => 'Invalid payment signature.'], 422);
        }

        $gstNumber = trim((string) (($payload['gst_number'] ?? '') ?: $payment->gst_number ?: $user->gst_number ?: ''));
        if ($gstNumber !== '' && $user->gst_number !== $gstNumber) {
            $user->update(['gst_number' => $gstNumber]);
        }

        $planToSync = null;
        $lockedPaymentToSync = null;
        $circleToSync = null;

        $updatedUser = DB::transaction(function () use ($payment, $payload, $user, &$planToSync, &$lockedPaymentToSync, &$circleToSync) {
            $lockedPayment = Payment::query()->where('id', $payment->id)->lockForUpdate()->first();
            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return $user->fresh();
            }

            $lockedPayment->update([
                'razorpay_payment_id' => $payload['razorpay_payment_id'],
                'razorpay_signature' => $payload['razorpay_signature'],
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => now(),
                'provider' => 'razorpay',
            ]);

            $plan = MembershipPlan::query()->where('id', $lockedPayment->membership_plan_id)->first();
            if (! $plan) {
                Log::error('Membership plan missing for payment', [
                    'payment_id' => $lockedPayment->id,
                ]);

                return $user->fresh();
            }

            $planToSync = $plan;
            $lockedPaymentToSync = $lockedPayment;

            // Resolve circle associated with this user's pending fee request
            $circleId = CircleJoinRequest::query()
                ->where('user_id', $user->id)
                ->where('status', CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
                ->latest('created_at')
                ->value('circle_id');

            if ($circleId) {
                $circleToSync = (string) $circleId;
            }

            // Existing membership date & status calculation/upgrade logic
            return $this->membershipService->activateMembership($user, $plan, $lockedPayment);
        });

        // 1. Existing Circle membership processing
        if ($circleToSync) {
            try {
                $this->circlePaymentSyncService->markRequestPaid(
                    $updatedUser,
                    $circleToSync,
                    $lockedPaymentToSync?->paid_at ?? now()
                );
            } catch (\Throwable $e) {
                Log::error('Circle membership sync error on verify', [
                    'payment_id' => $lockedPaymentToSync?->id,
                    'user_id' => $updatedUser->id,
                    'circle_id' => $circleToSync,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2. Existing Zoho invoice generation
        if ($planToSync instanceof MembershipPlan && $lockedPaymentToSync instanceof Payment) {
            try {
                $this->membershipService->syncZohoInvoice($updatedUser, $planToSync, $lockedPaymentToSync);
            } catch (\Throwable $e) {
                Log::error('Zoho invoice sync error on verify', [
                    'payment_id' => $lockedPaymentToSync->id,
                    'user_id' => $updatedUser->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $freshUser = $updatedUser->fresh() ?? $updatedUser;

        return response()->json([
            'success' => true,
            'membership_status' => $freshUser->membership_status,
            'membership_expiry' => $freshUser->membership_ends_at,
            'zoho_invoice_id' => $freshUser->zoho_last_invoice_id,
            'gst_number' => $freshUser->gst_number,
            'circle_joined' => (bool) $circleToSync,
            'circle_id' => $circleToSync,
        ]);
    }
}
