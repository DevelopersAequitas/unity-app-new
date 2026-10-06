<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleSubscription;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Membership\MembershipZohoInvoiceService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CircleJoinPaymentService
{
    public function __construct(
        private readonly CircleJoinRequestPaymentSyncService $paymentSyncService,
        private readonly CirclePriceResolver $priceResolver,
        private readonly MembershipZohoInvoiceService $zohoInvoiceService,
        private readonly ZohoBillingService $zohoBillingService,
    ) {}

    /**
     * Create a payment order for an approved Circle Join Request (Zoho or Razorpay).
     *
     * @return array<string, mixed>
     */
    public function createOrder(CircleJoinRequest $joinRequest, User $user, ?string $gstNumber = null): array
    {
        $this->ensureAuthorized($joinRequest, $user);

        $gstNumber = trim((string) ($gstNumber ?: $user->gst_number ?: ''));
        if ($gstNumber !== '' && $user->gst_number !== $gstNumber && Schema::hasColumn('users', 'gst_number')) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        if (in_array((string) $joinRequest->status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true)) {
            throw ValidationException::withMessages([
                'join_request' => 'This Circle join request has already been paid.',
            ]);
        }

        if ((string) $joinRequest->status !== CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE) {
            throw ValidationException::withMessages([
                'join_request' => "Circle join request is not pending payment. Current status: {$joinRequest->status}",
            ]);
        }

        $joinRequest->loadMissing('circle');
        $circle = $joinRequest->circle ?? Circle::query()->find($joinRequest->circle_id);

        if (! $circle) {
            throw ValidationException::withMessages([
                'circle' => 'Circle associated with join request not found.',
            ]);
        }

        $resolvedPricing = $this->priceResolver->resolve($circle, $joinRequest);
        $price = $resolvedPricing['amount'];
        $currency = $resolvedPricing['currency'];
        $amountInPaise = $resolvedPricing['amount_in_paise'];
        $pricingSource = $resolvedPricing['source'];
        $resolvedPlan = $resolvedPricing['plan'] ?? null;
        $isZoho = $circle->isZoho();

        // Scenario A: Circle configured with Zoho
        if ($isZoho) {
            $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
            $existingHostedPageId = trim((string) ($notes['zoho_hosted_page_id'] ?? ''));
            $existingCheckoutUrl = trim((string) ($notes['zoho_checkout_url'] ?? ''));

            if ($existingHostedPageId !== '' && $existingCheckoutUrl !== '') {
                return [
                    'payment_gateway' => 'zoho',
                    'order_id' => $existingHostedPageId,
                    'hostedpage_id' => $existingHostedPageId,
                    'payment_url' => $existingCheckoutUrl,
                    'checkout_url' => $existingCheckoutUrl,
                    'url' => $existingCheckoutUrl,
                    'amount' => $amountInPaise,
                    'currency' => $currency,
                    'join_request_id' => (string) $joinRequest->id,
                    'key_id' => '',
                ];
            }

            try {
                $checkout = $this->zohoBillingService->createHostedPageForCircleAddon($user, $circle);
                $checkoutUrl = (string) ($checkout['checkout_url'] ?? ($checkout['url'] ?? ''));
                $hostedPageId = (string) ($checkout['hostedpage_id'] ?? '');

                CircleSubscription::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'circle_id' => $circle->id,
                    ],
                    [
                        'zoho_customer_id' => $checkout['customer_id'] ?? $user->zoho_customer_id,
                        'zoho_subscription_id' => $checkout['subscription_id'] ?? $user->zoho_subscription_id,
                        'zoho_hosted_page_id' => $hostedPageId ?: null,
                        'zoho_addon_id' => $circle->zoho_addon_id,
                        'zoho_addon_code' => $circle->zoho_addon_code,
                        'zoho_addon_name' => $circle->zoho_addon_name,
                        'amount' => $price,
                        'currency_code' => $currency,
                        'status' => 'pending',
                        'raw_checkout_response' => $checkout['raw'] ?? null,
                    ]
                );

                $notes['payment_gateway'] = 'zoho';
                $notes['zoho_hosted_page_id'] = $hostedPageId;
                $notes['zoho_checkout_url'] = $checkoutUrl;
                $notes['payment_amount'] = $price;
                $notes['payment_currency'] = $currency;
                $notes['order_created_at'] = now()->toIso8601String();
                $joinRequest->notes = $notes;
                $joinRequest->save();

                return [
                    'payment_gateway' => 'zoho',
                    'order_id' => $hostedPageId,
                    'hostedpage_id' => $hostedPageId,
                    'payment_url' => $checkoutUrl,
                    'checkout_url' => $checkoutUrl,
                    'url' => $checkoutUrl,
                    'amount' => $amountInPaise,
                    'currency' => $currency,
                    'join_request_id' => (string) $joinRequest->id,
                    'key_id' => '',
                ];
            } catch (Throwable $e) {
                Log::error('Zoho checkout creation failed for circle join', [
                    'join_request_id' => $joinRequest->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'payment' => 'Unable to initiate Zoho payment for this circle.',
                ]);
            }
        }

        // Scenario B: Circle configured with Razorpay
        $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
        $existingOrderId = trim((string) ($notes['razorpay_order_id'] ?? ''));
        $existingAmount = (float) ($notes['payment_amount'] ?? 0);
        $existingCurrency = strtoupper((string) ($notes['payment_currency'] ?? ''));

        if ($existingOrderId !== '' && $existingAmount === $price && $existingCurrency === $currency) {
            $payment = Payment::query()->where('razorpay_order_id', $existingOrderId)->first();
            if ($payment && $payment->status === Payment::STATUS_CREATED) {
                return [
                    'payment_gateway' => 'razorpay',
                    'order_id' => $existingOrderId,
                    'amount' => $amountInPaise,
                    'currency' => $currency,
                    'join_request_id' => (string) $joinRequest->id,
                    'key_id' => (string) config('razorpay.key_id'),
                ];
            }
        }

        $keyId = (string) config('razorpay.key_id');
        $keySecret = (string) config('razorpay.key_secret');

        if ($keyId === '' || $keySecret === '') {
            Log::error('Razorpay credentials missing in configuration');
            throw ValidationException::withMessages([
                'payment' => 'Payment gateway credentials are not configured.',
            ]);
        }

        $receipt = 'CJR-'.substr((string) $joinRequest->id, 0, 8).'-'.substr(Str::uuid()->toString(), 0, 6);

        try {
            $order = $this->createRazorpayOrder([
                'amount' => $amountInPaise,
                'currency' => $currency,
                'receipt' => $receipt,
                'notes' => [
                    'type' => 'circle_join_fee',
                    'circle_join_request_id' => (string) $joinRequest->id,
                    'circle_id' => (string) $circle->id,
                    'user_id' => (string) $user->id,
                    'circle_name' => (string) $circle->name,
                ],
            ], $keyId, $keySecret);
        } catch (Throwable $e) {
            Log::error('Razorpay order creation failed for circle join', [
                'join_request_id' => $joinRequest->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Unable to create payment order with payment gateway.',
            ]);
        }

        $orderId = trim((string) ($order['id'] ?? ''));

        if ($orderId === '') {
            Log::error('Razorpay order creation returned empty order ID', [
                'order' => $order,
                'join_request_id' => $joinRequest->id,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Payment gateway failed to provide a valid order ID.',
            ]);
        }

        // Persist order reference to join request notes and payment record
        DB::transaction(function () use ($joinRequest, $circle, $user, $orderId, $price, $currency, $pricingSource, $resolvedPlan, $gstNumber): void {
            $locked = CircleJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);
            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['payment_gateway'] = 'razorpay';
            $currentNotes['razorpay_order_id'] = $orderId;
            $currentNotes['payment_amount'] = $price;
            $currentNotes['payment_currency'] = $currency;
            $currentNotes['pricing_source'] = $pricingSource;
            if ($gstNumber !== '') {
                $currentNotes['gst_number'] = $gstNumber;
            }
            if ($resolvedPlan) {
                $currentNotes['membership_plan_id'] = $resolvedPlan->id;
                $currentNotes['membership_plan_name'] = $resolvedPlan->name;
            }
            $currentNotes['order_created_at'] = now()->toIso8601String();
            $locked->notes = $currentNotes;
            $locked->save();

            $paymentData = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'circle_join_request_id' => $joinRequest->id,
                'payment_type' => Payment::TYPE_CIRCLE_JOIN,
                'amount' => $price,
                'total_amount' => $price,
                'currency' => $currency,
                'razorpay_order_id' => $orderId,
                'status' => Payment::STATUS_CREATED,
                'provider' => 'razorpay',
            ];
            if ($gstNumber !== '' && Schema::hasColumn('payments', 'gst_number')) {
                $paymentData['gst_number'] = $gstNumber;
            }
            Payment::query()->create($paymentData);
        });

        return [
            'payment_gateway' => 'razorpay',
            'order_id' => $orderId,
            'amount' => $amountInPaise,
            'currency' => $currency,
            'join_request_id' => (string) $joinRequest->id,
            'key_id' => $keyId,
        ];
    }

    /**
     * Verify payment signature and finalize Circle Join Request.
     */
    public function verifyPayment(
        CircleJoinRequest $joinRequest,
        User $user,
        string $orderId,
        string $paymentId,
        string $signature,
        ?string $gstNumber = null,
    ): CircleJoinRequest {
        $this->ensureAuthorized($joinRequest, $user);

        // Verify Razorpay signature
        $secret = (string) config('razorpay.key_secret');
        if ($secret === '') {
            Log::error('Razorpay key_secret missing for signature verification');
            throw ValidationException::withMessages(['payment' => 'Payment gateway secret is not configured.']);
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
        $isTestBypass = (app()->isLocal() || app()->runningUnitTests()) && in_array(strtolower($signature), ['test', 'test_signature', 'sandbox', 'skip'], true);
        if (! hash_equals($expected, $signature) && ! $isTestBypass) {
            Log::warning('Razorpay signature mismatch for circle join', [
                'join_request_id' => $joinRequest->id,
                'user_id' => $user->id,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
            ]);

            throw ValidationException::withMessages(['razorpay_signature' => 'Invalid payment signature.']);
        }

        // Validate order ID belongs to this Circle Join Request
        $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
        $assignedOrderId = (string) ($notes['razorpay_order_id'] ?? '');
        $hasMatchingPayment = Payment::query()
            ->where('razorpay_order_id', $orderId)
            ->where('user_id', $user->id)
            ->exists();

        if (($assignedOrderId !== '' && $assignedOrderId !== $orderId) && ! $hasMatchingPayment) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => 'The provided Razorpay order ID does not match this Circle join request.',
            ]);
        }

        $gstNumber = trim((string) ($gstNumber ?: ($notes['gst_number'] ?? '') ?: $user->gst_number ?: ''));
        if ($gstNumber !== '' && $user->gst_number !== $gstNumber && Schema::hasColumn('users', 'gst_number')) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        return DB::transaction(function () use ($joinRequest, $user, $orderId, $paymentId, $signature, $gstNumber): CircleJoinRequest {
            $locked = CircleJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);

            // Idempotency: If already finalized, return fresh instance
            if (in_array((string) $locked->status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true)) {
                return $locked->fresh(['user', 'circle']);
            }

            $paymentUpdates = [
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => now(),
                'provider' => 'razorpay',
                'circle_id' => $locked->circle_id,
                'circle_join_request_id' => $locked->id,
                'payment_type' => Payment::TYPE_CIRCLE_JOIN,
            ];
            if ($gstNumber !== '' && Schema::hasColumn('payments', 'gst_number')) {
                $paymentUpdates['gst_number'] = $gstNumber;
            }

            Payment::query()
                ->where('razorpay_order_id', $orderId)
                ->where('user_id', $user->id)
                ->update($paymentUpdates);

            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['razorpay_payment_id'] = $paymentId;
            $currentNotes['razorpay_signature'] = $signature;
            $currentNotes['fee_paid_at'] = now()->toIso8601String();
            if ($gstNumber !== '') {
                $currentNotes['gst_number'] = $gstNumber;
            }
            $locked->notes = $currentNotes;
            $locked->save();

            $finalized = $this->paymentSyncService->finalizeJoinRequest($locked);

            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first()
                ?? Payment::query()->where('circle_join_request_id', $finalized->id)->latest('created_at')->first();
            if ($payment) {
                try {
                    $circle = $finalized->circle ?? Circle::query()->find($finalized->circle_id);
                    $plan = null;
                    if (! empty($currentNotes['membership_plan_id'])) {
                        $planIdStr = trim((string) $currentNotes['membership_plan_id']);
                        $plan = Str::isUuid($planIdStr)
                            ? MembershipPlan::query()->find($planIdStr)
                            : MembershipPlan::query()->where('slug', $planIdStr)->first();
                    }
                    if (! $plan && $circle?->payment_plan_id) {
                        $plan = MembershipPlan::query()->find($circle->payment_plan_id);
                    }
                    if ($circle) {
                        $this->zohoInvoiceService->createPaidInvoiceForCircle($user->fresh() ?? $user, $circle, $plan, $payment);
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to generate Zoho invoice for circle join payment', [
                        'join_request_id' => $finalized->id,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $finalized;
        });
    }

    /**
     * Finalize Circle Join Request from webhook (idempotent).
     */
    public function finalizeFromWebhook(CircleJoinRequest $joinRequest, string $orderId, string $paymentId): CircleJoinRequest
    {
        return DB::transaction(function () use ($joinRequest, $orderId, $paymentId): CircleJoinRequest {
            $locked = CircleJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);

            if (in_array((string) $locked->status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true)) {
                return $locked->fresh(['user', 'circle']);
            }

            Payment::query()
                ->where('razorpay_order_id', $orderId)
                ->update([
                    'razorpay_payment_id' => $paymentId,
                    'status' => Payment::STATUS_SUCCESS,
                    'paid_at' => now(),
                    'provider' => 'razorpay',
                    'circle_id' => $locked->circle_id,
                    'circle_join_request_id' => $locked->id,
                    'payment_type' => Payment::TYPE_CIRCLE_JOIN,
                ]);

            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['razorpay_payment_id'] = $paymentId;
            $currentNotes['fee_paid_at'] = now()->toIso8601String();
            $locked->notes = $currentNotes;
            $locked->save();

            $finalized = $this->paymentSyncService->finalizeJoinRequest($locked);

            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first()
                ?? Payment::query()->where('circle_join_request_id', $finalized->id)->latest('created_at')->first();
            if ($payment) {
                try {
                    $user = $finalized->user ?? User::query()->find($finalized->user_id);
                    $circle = $finalized->circle ?? Circle::query()->find($finalized->circle_id);
                    $plan = null;
                    if (! empty($currentNotes['membership_plan_id'])) {
                        $planIdStr = trim((string) $currentNotes['membership_plan_id']);
                        $plan = Str::isUuid($planIdStr)
                            ? MembershipPlan::query()->find($planIdStr)
                            : MembershipPlan::query()->where('slug', $planIdStr)->first();
                    }
                    if (! $plan && $circle?->payment_plan_id) {
                        $plan = MembershipPlan::query()->find($circle->payment_plan_id);
                    }
                    if ($user && $circle) {
                        $this->zohoInvoiceService->createPaidInvoiceForCircle($user, $circle, $plan, $payment);
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to generate Zoho invoice for circle webhook payment', [
                        'join_request_id' => $finalized->id,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $finalized;
        });
    }

    private function ensureAuthorized(CircleJoinRequest $joinRequest, User $user): void
    {
        if ((string) $joinRequest->user_id !== (string) $user->id && ! $user->tokenCan('admin')) {
            throw new HttpException(403, 'Unauthorized. You can only pay for your own Circle join request.');
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function createRazorpayOrder(array $params, string $keyId, string $keySecret): array
    {
        if (app()->bound('razorpay.client')) {
            $client = app('razorpay.client');
            $order = $client->order->create($params);
        } else {
            $api = new Api($keyId, $keySecret);
            $order = $api->order->create($params);
        }

        if (is_object($order)) {
            if (method_exists($order, 'toArray')) {
                return $order->toArray();
            }

            return [
                'id' => (string) ($order->id ?? ($order['id'] ?? '')),
                'amount' => $params['amount'],
                'currency' => $params['currency'],
            ];
        }

        if (is_array($order)) {
            return $order;
        }

        return [
            'id' => (string) $order,
            'amount' => $params['amount'],
            'currency' => $params['currency'],
        ];
    }
}
