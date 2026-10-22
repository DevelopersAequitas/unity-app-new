<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Advertiser;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserPaymentApiController extends BaseApiController
{
    public function __construct(
        protected AdvertiserPaymentService $paymentService
    ) {}

    /**
     * API 16: Create Razorpay payment order
     * POST /api/v1/advertiser/subscriptions/{id}/payment-order
     * Also supports passing application UUID as ID for convenience.
     */
    public function createOrder(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $idempotencyKey = (string) $request->input('idempotency_key', '');

        // Resolve subscription or application
        $subscription = AdvertiserSubscription::with('application')->find($id);

        if (!$subscription) {
            // Check if ID is an approved application ID
            $application = AdvertiserApplication::where('id', $id)->first();
            if ($application) {
                if ($application->status !== AdvertiserApplication::STATUS_APPROVED) {
                    return $this->error('Payment cannot be initiated until the application is approved.', 422);
                }

                // Check or create subscription for this application
                $subscription = AdvertiserSubscription::where('application_id', $application->id)->first();
                if (!$subscription) {
                    $subscription = AdvertiserSubscription::create([
                        'application_id' => $application->id,
                        'user_id' => $user->id,
                        'brand_partner_id' => $application->brand_partner_id,
                        'package_id' => $application->package_id,
                        'package_name_snapshot' => $application->requested_package_name ?? 'Standard Package',
                        'duration_months' => $application->requested_duration_months ?: 1,
                        'amount' => $application->requested_package_price ?? 0.0,
                        'currency' => $application->requested_currency ?? 'INR',
                        'status' => AdvertiserSubscription::STATUS_PENDING_PAYMENT,
                    ]);
                }
            }
        }

        if (!$subscription) {
            return $this->error('Subscription or approved application not found.', 404);
        }

        // Ownership verification
        if ($subscription->user_id !== $user->id && $subscription->application?->user_id !== $user->id) {
            // Link if unlinked website application
            if ($subscription->application && empty($subscription->application->user_id)) {
                $subscription->application->update(['user_id' => $user->id]);
                $subscription->update(['user_id' => $user->id]);
            } else {
                return $this->error('Unauthorized access to this payment order.', 403);
            }
        }

        $orderPayload = $this->paymentService->createPaymentOrder(
            subscription: $subscription,
            idempotencyKey: $idempotencyKey ?: null
        );

        return $this->success($orderPayload, 'Payment order created successfully.');
    }

    /**
     * API 17: Verify payment result
     * POST /api/v1/advertiser/payments/verify
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            DB::purge('pgsql');
            DB::reconnect('pgsql');
        } catch (\Throwable $e) {
        }

        $rawPaymentId = (string) $request->input('payment_id', '');
        $cleanPaymentId = str_replace(' ', '-', trim($rawPaymentId));
        $request->merge(['payment_id' => $cleanPaymentId ?: null]);

        $validated = $request->validate([
            'payment_id' => ['nullable', 'string'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $payment = $this->paymentService->verifyPaymentResult(
            paymentId: (string) ($validated['payment_id'] ?? ''),
            razorpayOrderId: (string) $validated['razorpay_order_id'],
            razorpayPaymentId: (string) $validated['razorpay_payment_id'],
            razorpaySignature: (string) $validated['razorpay_signature']
        );

        $subscription = $payment->subscription;

        return $this->success([
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'subscription_id' => $subscription?->id,
            'subscription_status' => $subscription?->status ?? 'active',
            'publication_status' => ($subscription?->brandPartner?->is_active ?? false) ? 'published' : 'pending_activation',
            'next_action' => 'view_dashboard',
        ], 'Payment verified successfully.');
    }

    /**
     * API 18: Razorpay Webhook receiver
     * POST /api/v1/webhooks/razorpay/advertiser
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $rawBody = (string) $request->getContent();
        $payload = $request->all();

        $result = $this->paymentService->handleWebhook(
            payload: $payload,
            signature: $signature,
            rawBody: $rawBody
        );

        return response()->json($result);
    }

    /**
     * API 19: Get payment status
     * GET /api/v1/advertiser/payments/{id}
     */
    public function paymentStatus(Request $request, string $id): JsonResponse
    {
        $payment = AdvertiserPayment::find($id);

        if (!$payment) {
            return $this->error('Payment record not found.', 404);
        }

        return $this->success([
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'subscription_id' => $payment->subscription_id,
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'next_action' => match ($payment->status) {
                AdvertiserPayment::STATUS_CAPTURED => 'view_dashboard',
                AdvertiserPayment::STATUS_FAILED => 'retry_payment',
                default => 'check_again',
            },
        ], 'Payment status retrieved.');
    }
}
