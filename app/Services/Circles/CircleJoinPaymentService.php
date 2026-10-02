<?php

declare(strict_types=1);

namespace App\Services\Circles;

use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Membership\MembershipZohoInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    ) {}

    /**
     * Create a Razorpay order for an approved Circle Join Request.
     *
     * @return array{order_id: string, amount: int, currency: string, join_request_id: string, key_id: string}
     */
    public function createOrder(CircleJoinRequest $joinRequest, User $user): array
    {
        $this->ensureAuthorized($joinRequest, $user);

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

        // Idempotency: reuse existing pending order if valid and amounts match
        $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
        $existingOrderId = trim((string) ($notes['razorpay_order_id'] ?? ''));
        $existingAmount = (float) ($notes['payment_amount'] ?? 0);
        $existingCurrency = strtoupper((string) ($notes['payment_currency'] ?? ''));

        if ($existingOrderId !== '' && $existingAmount === $price && $existingCurrency === $currency) {
            $payment = Payment::query()->where('razorpay_order_id', $existingOrderId)->first();
            if ($payment && $payment->status === Payment::STATUS_CREATED) {
                return [
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
        DB::transaction(function () use ($joinRequest, $user, $orderId, $price, $currency, $pricingSource, $resolvedPlan): void {
            $locked = CircleJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);
            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['razorpay_order_id'] = $orderId;
            $currentNotes['payment_amount'] = $price;
            $currentNotes['payment_currency'] = $currency;
            $currentNotes['pricing_source'] = $pricingSource;
            if ($resolvedPlan) {
                $currentNotes['membership_plan_id'] = $resolvedPlan->id;
                $currentNotes['membership_plan_name'] = $resolvedPlan->name;
            }
            $currentNotes['order_created_at'] = now()->toIso8601String();
            $locked->notes = $currentNotes;
            $locked->save();

            Payment::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'amount' => $price,
                'total_amount' => $price,
                'currency' => $currency,
                'razorpay_order_id' => $orderId,
                'status' => Payment::STATUS_CREATED,
                'provider' => 'razorpay',
            ]);
        });

        return [
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
    ): CircleJoinRequest {
        $this->ensureAuthorized($joinRequest, $user);

        // Verify Razorpay signature
        $secret = (string) config('razorpay.key_secret');
        if ($secret === '') {
            Log::error('Razorpay key_secret missing for signature verification');
            throw ValidationException::withMessages(['payment' => 'Payment gateway secret is not configured.']);
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
        if (! hash_equals($expected, $signature)) {
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

        return DB::transaction(function () use ($joinRequest, $user, $orderId, $paymentId, $signature): CircleJoinRequest {
            $locked = CircleJoinRequest::query()->lockForUpdate()->findOrFail($joinRequest->id);

            // Idempotency: If already finalized, return fresh instance
            if (in_array((string) $locked->status, [CircleJoinRequest::STATUS_PAID, CircleJoinRequest::STATUS_CIRCLE_MEMBER], true)) {
                return $locked->fresh(['user', 'circle']);
            }

            Payment::query()
                ->where('razorpay_order_id', $orderId)
                ->where('user_id', $user->id)
                ->update([
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature' => $signature,
                    'status' => Payment::STATUS_SUCCESS,
                    'paid_at' => now(),
                    'provider' => 'razorpay',
                ]);

            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['razorpay_payment_id'] = $paymentId;
            $currentNotes['razorpay_signature'] = $signature;
            $currentNotes['fee_paid_at'] = now()->toIso8601String();
            $locked->notes = $currentNotes;
            $locked->save();

            $finalized = $this->paymentSyncService->finalizeJoinRequest($locked);

            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
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
                    if ($circle) {
                        $this->zohoInvoiceService->createPaidInvoiceForCircle($user, $circle, $plan, $payment);
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
                ]);

            $currentNotes = is_array($locked->notes) ? $locked->notes : [];
            $currentNotes['razorpay_payment_id'] = $paymentId;
            $currentNotes['fee_paid_at'] = now()->toIso8601String();
            $locked->notes = $currentNotes;
            $locked->save();

            $finalized = $this->paymentSyncService->finalizeJoinRequest($locked);

            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
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
