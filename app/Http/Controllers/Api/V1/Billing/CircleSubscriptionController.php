<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\VerifyCirclePaymentRequest;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\CircleSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Services\Membership\MembershipZohoInvoiceService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use Throwable;

class CircleSubscriptionController extends BaseApiController
{
    public function __construct(
        private readonly ZohoBillingService $zohoBillingService,
        private readonly MembershipZohoInvoiceService $zohoInvoiceService,
    ) {}

    /**
     * Get Circle Package configuration details.
     *
     * GET /api/v1/circles/{circle}/package
     */
    public function package(Circle $circle): JsonResponse
    {
        $addonCode = trim((string) ($circle->zoho_addon_code ?? ''));
        $amount = $circle->circle_price_amount !== null ? (float) $circle->circle_price_amount : null;
        $currency = $circle->circle_price_currency;

        if ($addonCode !== '' && ($amount === null || $currency === null || $currency === '')) {
            try {
                $addon = $this->zohoBillingService->findCirclePackageAddonByCodeOrId($addonCode, false);

                if (is_array($addon)) {
                    $amount = $amount
                        ?? data_get($addon, 'raw.price_brackets.0.price')
                        ?? data_get($addon, 'price_brackets.0.price')
                        ?? ($addon['price'] ?? null)
                        ?? ($addon['amount'] ?? null)
                        ?? ($addon['rate'] ?? null);

                    $currency = $currency
                        ?: ($circle->circle_price_currency
                            ?: ($addon['currency_code'] ?? null)
                            ?: ($addon['currency'] ?? null)
                            ?: data_get($addon, 'raw.currency_code')
                            ?: data_get($addon, 'raw.currency')
                            ?: 'INR');
                }
            } catch (Throwable $e) {
                Log::info('Zoho addon lookup skipped for circle package', ['circle_id' => $circle->id]);
            }
        }

        $price = (float) ($amount ?? 0);
        $currency = strtoupper((string) ($currency ?: config('razorpay.currency', 'INR')));
        $gstPercent = (float) ($circle->circle_gst_percent ?? 18.00);
        $gstAmount = round($price * ($gstPercent / 100), 2);
        $totalAmount = round($price + $gstAmount, 2);
        $isActive = (bool) ($circle->is_package_active ?? true);
        $packageName = (string) ($circle->zoho_addon_name ?: ($circle->name ? $circle->name.' Package' : 'Circle Package'));
        $packageSlug = (string) ($circle->zoho_addon_code ?: ($circle->slug ? $circle->slug.'-package' : 'circle-package'));
        $durationMonths = (int) ($circle->circle_duration_months ?: 12);

        /** @var User|null $authUser */
        $authUser = request()->user();
        $hasActiveSubscription = false;
        if ($authUser) {
            $hasActiveSubscription = CircleSubscription::query()
                ->where('user_id', $authUser->id)
                ->where('circle_id', $circle->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();
        }

        $joinable = $isActive && $price > 0 && ! $hasActiveSubscription;

        return $this->success([
            'id' => (string) $circle->id,
            'name' => $packageName,
            'slug' => $packageSlug,
            'price' => $price,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'coins' => 0,
            'duration' => $durationMonths,
            'duration_months' => $durationMonths,
            'is_package_active' => $isActive,
            'is_active' => $isActive,
            'is_subscribed' => $hasActiveSubscription,
            'has_active_subscription' => $hasActiveSubscription,
            'subscription_status' => $hasActiveSubscription ? 'active' : 'inactive',
            'circle_id' => (string) $circle->id,
            'circle_name' => (string) $circle->name,
            'addon_code' => $circle->zoho_addon_code,
            'addon_name' => $circle->zoho_addon_name,
            'amount' => $price,
            'currency' => $currency,
            'joinable' => $joinable,
            'gateway' => 'razorpay',
            'key_id' => (string) config('razorpay.key_id'),
        ]);
    }

    /**
     * Start Razorpay checkout for a Circle Package.
     *
     * POST /api/v1/billing/circle-checkout/{circle}
     * or POST /api/v1/circles/{circle}/package/checkout
     */
    public function checkout(Request $request, Circle $circle): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        // Validate active package
        if (! ($circle->is_package_active ?? true)) {
            return $this->error('This circle package is currently inactive and cannot be purchased.', 422);
        }

        $price = (float) ($circle->circle_price_amount ?? 0);
        if ($price <= 0) {
            return $this->error('This circle does not have a valid package price configured.', 422);
        }

        // Check if user already has an active subscription for this circle
        $existing = CircleSubscription::query()
            ->where('user_id', $user->id)
            ->where('circle_id', $circle->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $this->error('You already have an active subscription for this circle.', 422);
        }

        $gstPercent = (float) ($circle->circle_gst_percent ?? 18.00);
        $gstAmount = round($price * ($gstPercent / 100), 2);
        $totalAmount = round($price + $gstAmount, 2);
        $amountInPaise = (int) round($totalAmount * 100);
        $currency = strtoupper((string) ($circle->circle_price_currency ?: config('razorpay.currency', 'INR')));

        // Check for existing pending order to ensure idempotency
        $pendingPayment = Payment::query()
            ->where('user_id', $user->id)
            ->where('circle_id', $circle->id)
            ->where('payment_type', Payment::TYPE_CIRCLE_PACKAGE)
            ->where('status', Payment::STATUS_CREATED)
            ->where('total_amount', $totalAmount)
            ->latest('created_at')
            ->first();

        if ($pendingPayment && ! empty($pendingPayment->razorpay_order_id)) {
            return $this->success([
                'order_id' => $pendingPayment->razorpay_order_id,
                'amount' => $amountInPaise,
                'currency' => $currency,
                'key_id' => (string) config('razorpay.key_id'),
                'circle_id' => (string) $circle->id,
                'payment_id' => (string) $pendingPayment->id,
                'payment_status' => Payment::STATUS_CREATED,
                'status' => Payment::STATUS_CREATED,
                'is_paid' => false,
                'package' => [
                    'name' => $circle->zoho_addon_name ?: ($circle->name.' Package'),
                    'price' => $price,
                    'gst_percent' => $gstPercent,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                ],
            ], 'Existing pending Razorpay order retrieved.');
        }

        $keyId = (string) config('razorpay.key_id');
        $keySecret = (string) config('razorpay.key_secret');

        if ($keyId === '' || $keySecret === '') {
            Log::error('Razorpay credentials missing in configuration');

            return $this->error('Payment gateway credentials are not configured.', 500);
        }

        $paymentId = (string) Str::uuid();
        $receipt = 'CP-'.substr((string) $circle->id, 0, 8).'-'.substr(Str::uuid()->toString(), 0, 6);

        try {
            if (app()->bound('razorpay.client')) {
                $client = app('razorpay.client');
                $order = $client->order->create([
                    'amount' => $amountInPaise,
                    'currency' => $currency,
                    'receipt' => $receipt,
                    'notes' => [
                        'type' => Payment::TYPE_CIRCLE_PACKAGE,
                        'payment_type' => Payment::TYPE_CIRCLE_PACKAGE,
                        'circle_id' => (string) $circle->id,
                        'user_id' => (string) $user->id,
                        'circle_name' => (string) $circle->name,
                    ],
                ]);
            } else {
                $api = app()->bound(Api::class) ? app(Api::class) : new Api($keyId, $keySecret);
                $order = $api->order->create([
                    'amount' => $amountInPaise,
                    'currency' => $currency,
                    'receipt' => $receipt,
                    'notes' => [
                        'type' => Payment::TYPE_CIRCLE_PACKAGE,
                        'payment_type' => Payment::TYPE_CIRCLE_PACKAGE,
                        'circle_id' => (string) $circle->id,
                        'user_id' => (string) $user->id,
                        'circle_name' => (string) $circle->name,
                    ],
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Razorpay order creation failed for circle package', [
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Unable to create payment order with payment gateway.', 500);
        }

        $orderId = trim(is_object($order) ? (string) ($order->id ?? ($order['id'] ?? '')) : (is_array($order) ? (string) ($order['id'] ?? '') : ''));
        if ($orderId === '') {
            return $this->error('Payment gateway failed to provide a valid order ID.', 500);
        }

        DB::transaction(function () use ($paymentId, $user, $circle, $price, $gstPercent, $gstAmount, $totalAmount, $currency, $orderId, $order): void {
            Payment::query()->create([
                'id' => $paymentId,
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'payment_type' => Payment::TYPE_CIRCLE_PACKAGE,
                'amount' => $price,
                'base_amount' => $price,
                'gst_percent' => $gstPercent,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'razorpay_order_id' => $orderId,
                'status' => Payment::STATUS_CREATED,
                'provider' => 'razorpay',
                'gst_number' => $user->gst_number,
            ]);

            CircleSubscription::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'status' => 'pending',
                'amount' => $totalAmount,
                'paid_amount' => 0,
                'currency_code' => $currency,
                'reference_id' => $orderId,
                'zoho_addon_code' => $circle->zoho_addon_code,
                'zoho_addon_name' => $circle->zoho_addon_name ?: ($circle->name.' Package'),
                'raw_checkout_response' => is_array($order) ? $order : (method_exists($order, 'toArray') ? $order->toArray() : null),
            ]);
        });

        return $this->success([
            'order_id' => $orderId,
            'amount' => $amountInPaise,
            'currency' => $currency,
            'key_id' => $keyId,
            'circle_id' => (string) $circle->id,
            'payment_id' => $paymentId,
            'payment_status' => Payment::STATUS_CREATED,
            'status' => Payment::STATUS_CREATED,
            'is_paid' => false,
            'package' => [
                'name' => $circle->zoho_addon_name ?: ($circle->name.' Package'),
                'price' => $price,
                'gst_percent' => $gstPercent,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
            ],
        ], 'Circle package order created successfully.');
    }

    /**
     * Cancel or dismiss pending Razorpay checkout for a Circle Package.
     *
     * POST /api/v1/billing/circle-checkout/{circle}/cancel
     * or POST /api/v1/circles/{circle}/package/cancel
     */
    public function cancel(Request $request, Circle $circle): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        $orderId = trim((string) ($request->input('razorpay_order_id') ?: $request->input('order_id') ?: ''));

        $paymentQuery = Payment::query()
            ->where('user_id', $user->id)
            ->where('circle_id', $circle->id)
            ->where('payment_type', Payment::TYPE_CIRCLE_PACKAGE);

        if ($orderId !== '') {
            $paymentQuery->where('razorpay_order_id', $orderId);
        } else {
            $paymentQuery->where('status', Payment::STATUS_CREATED)->latest('created_at');
        }

        $payment = $paymentQuery->first();

        if (! $payment) {
            return $this->success([
                'status' => Payment::STATUS_CANCELLED,
                'payment_status' => Payment::STATUS_CANCELLED,
                'is_active' => false,
            ], 'No pending payment found to cancel.');
        }

        // If already successful, do not cancel
        if ($payment->status === Payment::STATUS_SUCCESS) {
            return $this->error('Payment has already been completed and cannot be cancelled.', 422);
        }

        DB::transaction(function () use ($payment, $user, $circle): void {
            $payment->update([
                'status' => Payment::STATUS_CANCELLED,
            ]);

            if ($payment->razorpay_order_id) {
                CircleSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('circle_id', $circle->id)
                    ->where('reference_id', $payment->razorpay_order_id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'cancelled',
                    ]);
            }
        });

        return $this->success([
            'payment_id' => (string) $payment->id,
            'order_id' => $payment->razorpay_order_id,
            'status' => Payment::STATUS_CANCELLED,
            'payment_status' => Payment::STATUS_CANCELLED,
            'is_active' => false,
        ], 'Circle package checkout cancelled.');
    }

    /**
     * Verify Razorpay payment and activate Circle Package subscription.
     *
     * POST /api/v1/billing/circle-checkout/{circle}/verify
     * or POST /api/v1/circles/{circle}/package/verify
     */
    public function verify(VerifyCirclePaymentRequest $request, Circle $circle): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        $orderId = (string) $request->validated('razorpay_order_id');
        $paymentId = (string) $request->validated('razorpay_payment_id');
        $signature = (string) $request->validated('razorpay_signature');

        // Verify Razorpay HMAC signature
        $secret = (string) config('razorpay.key_secret');
        if ($secret === '') {
            return $this->error('Payment gateway secret is not configured.', 500);
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
        if (! hash_equals($expected, $signature)) {
            Log::warning('Razorpay signature mismatch for circle package checkout', [
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
            ]);

            return $this->error('Invalid payment signature.', 422);
        }

        $payment = Payment::query()
            ->where('razorpay_order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $payment) {
            return $this->error('Payment order not found for this user.', 404);
        }

        if ((string) $payment->circle_id !== (string) $circle->id || $payment->payment_type !== Payment::TYPE_CIRCLE_PACKAGE) {
            return $this->error('Payment order does not belong to this circle package.', 422);
        }

        if (in_array($payment->status, [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED], true)) {
            return $this->error('This payment order has failed or been cancelled. Please initiate a new checkout.', 422);
        }

        $result = DB::transaction(function () use ($payment, $user, $circle, $orderId, $paymentId, $signature): array {
            $lockedPayment = Payment::query()->lockForUpdate()->find($payment->id);

            // Idempotent return if already successful
            if ($lockedPayment->status === Payment::STATUS_SUCCESS) {
                return [
                    'payment' => $lockedPayment,
                    'already_paid' => true,
                ];
            }

            $now = now();
            $invoiceNumber = 'INV-CP-'.strtoupper(substr((string) $circle->id, 0, 4)).'-'.strtoupper(substr(str_replace('-', '', (string) $lockedPayment->id), 0, 8));

            $lockedPayment->update([
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => $now,
                'provider' => 'razorpay',
                'zoho_invoice_id' => $lockedPayment->zoho_invoice_id ?: $invoiceNumber,
            ]);

            // Update or create CircleSubscription
            $subscription = CircleSubscription::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circle->id)
                ->where('reference_id', $orderId)
                ->first();

            if (! $subscription) {
                $subscription = CircleSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('circle_id', $circle->id)
                    ->latest('created_at')
                    ->first();
            }

            $durationMonths = (int) ($circle->circle_duration_months ?: 12);
            $expiresAt = $now->copy()->addMonths($durationMonths);

            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'paid_amount' => $lockedPayment->total_amount,
                    'paid_currency' => $lockedPayment->currency ?: 'INR',
                    'paid_at' => $now,
                    'started_at' => $now,
                    'expires_at' => $expiresAt,
                    'reference_id' => $orderId,
                    'zoho_invoice_id' => $lockedPayment->zoho_invoice_id,
                ]);
            } else {
                $subscription = CircleSubscription::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'circle_id' => $circle->id,
                    'status' => 'active',
                    'amount' => $lockedPayment->total_amount,
                    'paid_amount' => $lockedPayment->total_amount,
                    'currency_code' => $lockedPayment->currency ?: 'INR',
                    'paid_currency' => $lockedPayment->currency ?: 'INR',
                    'paid_at' => $now,
                    'started_at' => $now,
                    'expires_at' => $expiresAt,
                    'reference_id' => $orderId,
                    'zoho_invoice_id' => $lockedPayment->zoho_invoice_id,
                ]);
            }

            // Assign Circle Member
            $circleMember = CircleMember::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circle->id)
                ->first();

            if (! $circleMember) {
                CircleMember::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'circle_id' => $circle->id,
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

            if (empty($user->active_circle_id)) {
                $user->update(['active_circle_id' => $circle->id]);
            }

            return [
                'payment' => $lockedPayment->fresh(),
                'subscription' => $subscription->fresh(),
                'already_paid' => false,
            ];
        });

        $finalPayment = $result['payment'];

        // Optionally attempt Zoho invoice creation if available
        try {
            $this->zohoInvoiceService->createPaidInvoiceForCircle($user, $circle, null, $finalPayment);
        } catch (Throwable $e) {
            Log::info('Zoho invoice sync notice for circle package payment', ['error' => $e->getMessage()]);
        }

        return $this->success([
            'payment_id' => (string) $finalPayment->id,
            'order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'circle_id' => (string) $circle->id,
            'circle_name' => (string) $circle->name,
            'status' => 'active',
            'payment_status' => Payment::STATUS_SUCCESS,
            'amount' => (float) $finalPayment->total_amount,
            'currency' => (string) $finalPayment->currency,
            'paid_at' => $finalPayment->paid_at?->toIso8601String(),
            'invoice_number' => $finalPayment->zoho_invoice_id,
        ], 'Payment verified and Circle Package activated successfully.');
    }
}
