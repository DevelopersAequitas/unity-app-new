<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Circles\CircleJoinPaymentService;
use App\Services\Circles\CircleJoinRequestService;
use App\Services\Circles\CirclePriceResolver;
use App\Services\Membership\MembershipZohoInvoiceService;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
use Throwable;

class AdminPlansApiController extends BaseApiController
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly MembershipZohoInvoiceService $membershipZohoInvoiceService,
        private readonly CircleJoinRequestService $joinRequestService,
        private readonly CircleJoinPaymentService $circleJoinPaymentService,
        private readonly CirclePriceResolver $priceResolver,
    ) {}

    /**
     * Get all Membership Plans
     */
    public function membershipPlans(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->membershipOnly()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = $plans->map(function (MembershipPlan $plan): array {
            $amounts = $this->membershipService->calculateAmounts($plan);

            return [
                'id' => (string) $plan->id,
                'name' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'price' => (float) $plan->price,
                'gst_percent' => (float) $plan->gst_percent,
                'gst_amount' => (float) $amounts['gst_amount'],
                'total_amount' => (float) $amounts['total_amount'],
                'duration_days' => (int) ($plan->duration_days ?? 0),
                'duration_months' => $plan->duration_months !== null ? (int) $plan->duration_months : null,
                'is_active' => (bool) $plan->is_active,
                'is_free' => (bool) $plan->is_free,
                'sort_order' => (int) ($plan->sort_order ?? 0),
                'coins' => (int) ($plan->coins ?? 0),
                'created_at' => $plan->created_at?->toIso8601String(),
                'updated_at' => $plan->updated_at?->toIso8601String(),
                'has_subscriptions' => $plan->payments()->exists() || $plan->memberships()->exists(),
            ];
        });

        return $this->success($data);
    }

    /**
     * Create a Membership Plan
     */
    public function createMembershipPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric', 'min:0'],
            'gst_percent' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['is_free'] && (float) $validated['price'] > 0) {
            return $this->error('Free plans must have a base price of 0.', 422);
        }

        $validated['id'] = (string) Str::uuid();
        $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        if (Schema::hasColumn('membership_plans', 'plan_type')) {
            $validated['plan_type'] = 'membership';
        }

        $plan = MembershipPlan::query()->create($validated);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]), 'Membership plan created successfully.', 201);
    }

    /**
     * Get a single Membership Plan
     */
    public function getMembershipPlan(string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]));
    }

    /**
     * Update a Membership Plan
     */
    public function updateMembershipPlan(Request $request, string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);

        if ($plan->isCirclePlan()) {
            return $this->error('Circle plans cannot be edited from Membership Plans.', 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric', 'min:0'],
            'gst_percent' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['is_free'] && (float) $validated['price'] > 0) {
            return $this->error('Free plans must have a base price of 0.', 422);
        }

        if (empty($plan->slug)) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $plan->id);
        }

        $plan->update($validated);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]), 'Membership plan updated successfully.');
    }

    /**
     * Delete a Membership Plan
     */
    public function deleteMembershipPlan(string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);

        if ($plan->isCirclePlan()) {
            return $this->error('Circle plans cannot be deleted from Membership Plans.', 422);
        }

        if ($plan->payments()->exists() || $plan->memberships()->exists()) {
            return $this->error('Cannot delete "'.$plan->name.'" because it has existing payments or member subscriptions. Please deactivate the plan instead.', 422);
        }

        $name = $plan->name;
        $plan->delete();

        return $this->success(null, 'Membership plan "'.$name.'" deleted successfully.');
    }

    /**
     * Get all Circle Plans
     */
    public function circlePlans(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->circleOnly()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = $plans->map(function (MembershipPlan $plan): array {
            $amounts = $this->membershipService->calculateAmounts($plan);

            return [
                'id' => (string) $plan->id,
                'name' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'price' => (float) $plan->price,
                'gst_percent' => (float) $plan->gst_percent,
                'gst_amount' => (float) $amounts['gst_amount'],
                'total_amount' => (float) $amounts['total_amount'],
                'duration_days' => (int) ($plan->duration_days ?? 0),
                'duration_months' => $plan->duration_months !== null ? (int) $plan->duration_months : null,
                'is_active' => (bool) $plan->is_active,
                'is_free' => (bool) $plan->is_free,
                'sort_order' => (int) ($plan->sort_order ?? 0),
                'coins' => (int) ($plan->coins ?? 0),
                'is_circle_fallback' => $plan->slug === 'circle_peer',
                'created_at' => $plan->created_at?->toIso8601String(),
                'updated_at' => $plan->updated_at?->toIso8601String(),
                'has_subscriptions' => $plan->payments()->exists() || $plan->memberships()->exists(),
            ];
        });

        return $this->success($data);
    }

    /**
     * Create a Circle Plan
     */
    public function createCirclePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric', 'min:0'],
            'gst_percent' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['is_free'] && (float) $validated['price'] > 0) {
            return $this->error('Free plans must have a base price of 0.', 422);
        }

        $validated['id'] = (string) Str::uuid();
        $validated['slug'] = $this->generateUniqueCircleSlug($validated['name']);
        if (Schema::hasColumn('membership_plans', 'plan_type')) {
            $validated['plan_type'] = 'circle';
        }

        $plan = MembershipPlan::query()->create($validated);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]), 'Circle plan created successfully.', 201);
    }

    /**
     * Get a single Circle Plan
     */
    public function getCirclePlan(string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]));
    }

    /**
     * Update a Circle Plan
     */
    public function updateCirclePlan(Request $request, string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);

        if (! $plan->isCirclePlan()) {
            return $this->error('Membership plans cannot be edited from Circle Plans.', 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'price' => ['required', 'numeric', 'min:0'],
            'gst_percent' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:0'],
            'duration_months' => ['nullable', 'integer', 'min:0'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'is_free' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validated['is_free'] && (float) $validated['price'] > 0) {
            return $this->error('Free plans must have a base price of 0.', 422);
        }

        // Never clear or overwrite slug for circle plans
        if (empty($plan->slug)) {
            $validated['slug'] = $this->generateUniqueCircleSlug($validated['name'], $plan->id);
        } else {
            unset($validated['slug']);
        }

        $plan->update($validated);
        $amounts = $this->membershipService->calculateAmounts($plan);

        return $this->success(array_merge($plan->toArray(), [
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
        ]), 'Circle plan updated successfully.');
    }

    /**
     * Delete a Circle Plan
     */
    public function deleteCirclePlan(string $id): JsonResponse
    {
        $plan = MembershipPlan::query()->findOrFail($id);

        if (! $plan->isCirclePlan()) {
            return $this->error('Membership plans cannot be deleted from Circle Plans.', 422);
        }

        if ($plan->slug === 'circle_peer') {
            return $this->error('The core Circle Peer plan cannot be deleted as it is required by the Circle payment system.', 422);
        }

        if ($plan->payments()->exists() || $plan->memberships()->exists()) {
            return $this->error('Cannot delete "'.$plan->name.'" because it has existing payments or member subscriptions. Please deactivate the plan instead.', 422);
        }

        $name = $plan->name;
        $plan->delete();

        return $this->success(null, 'Circle plan "'.$name.'" deleted successfully.');
    }

    /**
     * Razorpay Test Checkout: Init data
     */
    public function testCheckoutInit(): JsonResponse
    {
        $plans = MembershipPlan::query()
            ->membershipOnly()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $amounts = $this->membershipService->calculateAmounts($p);
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => (float) $p->price,
                    'gst_percent' => (float) $p->gst_percent,
                    'gst_amount' => $amounts['gst_amount'],
                    'total_amount' => $amounts['total_amount'],
                ];
            });

        $users = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'gst_number',
                'membership_status',
                'zoho_customer_id',
                'zoho_last_invoice_id',
            ])
            ->whereNotNull('email')
            ->orderByRaw("CASE WHEN membership_status IN ('free_trial_peer', 'free_peer') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($u) {
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                return [
                    'id' => $u->id,
                    'name' => $name ?: $u->email,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'gst_number' => $u->gst_number,
                    'membership_status' => $u->membership_status ?? 'no_status',
                    'zoho_customer_id' => $u->zoho_customer_id,
                    'zoho_last_invoice_id' => $u->zoho_last_invoice_id,
                ];
            });

        $recentPayments = Payment::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,membership_status,zoho_customer_id,zoho_last_invoice_id,gst_number',
                'plan:id,name,price',
            ])
            ->where('provider', 'razorpay')
            ->orWhereNotNull('razorpay_order_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'created_at' => $pm->created_at?->format('Y-m-d H:i:s'),
                    'user_id' => $pm->user_id,
                    'user_name' => $pm->user ? ($pm->user->display_name ?: trim(($pm->user->first_name ?? '').' '.($pm->user->last_name ?? ''))) : 'Unknown',
                    'user_email' => $pm->user?->email,
                    'membership_status' => $pm->user?->membership_status,
                    'plan_name' => $pm->plan?->name ?? 'Custom Plan',
                    'total_amount' => (float) $pm->total_amount,
                    'razorpay_order_id' => $pm->razorpay_order_id,
                    'razorpay_payment_id' => $pm->razorpay_payment_id,
                    'status' => $pm->status,
                    'zoho_customer_id' => $pm->user?->zoho_customer_id,
                    'zoho_invoice_id' => $pm->zoho_invoice_id ?: $pm->user?->zoho_last_invoice_id,
                ];
            });

        return $this->success([
            'plans' => $plans,
            'users' => $users,
            'recent_payments' => $recentPayments,
            'razorpay_key_id' => (string) config('razorpay.key_id'),
        ]);
    }

    /**
     * Razorpay Test Checkout: Create Order
     */
    public function testCheckoutCreateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'membership_plan_id' => ['required', 'string', 'exists:membership_plans,id'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $plan = MembershipPlan::query()->findOrFail($validated['membership_plan_id']);

        if ($plan->is_free) {
            return $this->error('Free plans do not require a payment order.', 422);
        }

        $gstNumber = ! empty($validated['gst_number']) ? trim((string) $validated['gst_number']) : null;
        if (! empty($gstNumber)) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        $amounts = $this->membershipService->calculateAmounts($plan);
        $paymentId = (string) Str::uuid();

        $keyId = (string) config('razorpay.key_id');
        $keySecret = (string) config('razorpay.key_secret');

        $orderId = null;
        if (! empty($keyId) && ! empty($keySecret)) {
            try {
                $api = new Api($keyId, $keySecret);
                $notes = [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                ];
                if (! empty($gstNumber)) {
                    $notes['gstin'] = $gstNumber;
                }

                $order = $api->order->create([
                    'amount' => (int) round($amounts['total_amount'] * 100),
                    'currency' => (string) config('razorpay.currency', 'INR'),
                    'receipt' => $paymentId,
                    'notes' => $notes,
                ]);
                $orderId = $order['id'];
            } catch (Throwable $e) {
                Log::warning('Razorpay live order failed, falling back to simulated order', ['error' => $e->getMessage()]);
                $orderId = 'order_test_'.Str::random(14);
            }
        } else {
            $orderId = 'order_test_'.Str::random(14);
        }

        $payment = Payment::query()->create([
            'id' => $paymentId,
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'base_amount' => $amounts['base_amount'],
            'gst_percent' => $amounts['gst_percent'],
            'gst_amount' => $amounts['gst_amount'],
            'total_amount' => $amounts['total_amount'],
            'currency' => (string) config('razorpay.currency', 'INR'),
            'razorpay_order_id' => $orderId,
            'gst_number' => $gstNumber,
            'status' => Payment::STATUS_CREATED,
            'provider' => 'razorpay',
        ]);

        return $this->success([
            'order_id' => $orderId,
            'amount' => (int) round($amounts['total_amount'] * 100),
            'currency' => (string) config('razorpay.currency', 'INR'),
            'key_id' => $keyId ?: 'rzp_test_placeholder',
            'payment_record_id' => $payment->id,
            'gst_number' => $gstNumber,
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => $user->email,
                'phone' => $user->phone,
                'gst_number' => $user->gst_number,
                'current_membership_status' => $user->membership_status,
            ],
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'gst_amount' => $amounts['gst_amount'],
                'total_amount' => $amounts['total_amount'],
            ],
        ]);
    }

    /**
     * Razorpay Test Checkout: Verify Payment
     */
    public function testCheckoutVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['nullable', 'string'],
            'skip_signature_verification' => ['nullable', 'boolean'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ]);

        $orderId = trim($validated['razorpay_order_id']);
        $paymentId = trim($validated['razorpay_payment_id']);
        $signature = trim((string) ($validated['razorpay_signature'] ?? ''));
        $skipSignature = (bool) ($validated['skip_signature_verification'] ?? false);
        $reqGstNumber = ! empty($validated['gst_number']) ? trim((string) $validated['gst_number']) : null;

        $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
        if (! $payment) {
            return $this->error('Payment record with order_id ['.$orderId.'] was not found in the database. Please create the order first.', 404);
        }

        $user = User::query()->find($payment->user_id);
        $plan = MembershipPlan::query()->find($payment->membership_plan_id);

        if (! $user || ! $plan) {
            return $this->error('Associated user or membership plan is missing.', 404);
        }

        $gstNumber = $reqGstNumber ?: ($payment->gst_number ?: $user->gst_number);
        if (! empty($gstNumber) && empty($user->gst_number)) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        if (! $skipSignature && ! str_starts_with($orderId, 'order_test_')) {
            $secret = (string) config('razorpay.key_secret');
            if (! empty($secret)) {
                $expectedSignature = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
                if (! hash_equals($expectedSignature, (string) $signature)) {
                    return $this->error('Invalid payment signature. Razorpay verification failed. You can check "Skip signature check" for test debugging.', 422);
                }
            }
        }

        // 1. Activate Membership inside DB transaction
        $updatedUser = DB::transaction(function () use ($payment, $paymentId, $signature, $user, $plan, $gstNumber): User {
            $payment->update([
                'status' => Payment::STATUS_COMPLETED,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature ?: 'simulated_signature',
                'gst_number' => $gstNumber,
            ]);

            return $this->membershipService->activatePlan($user, $plan, $payment);
        });

        // 2. Trigger Zoho automated invoice creation
        $zohoResult = null;
        $zohoError = null;
        try {
            $zohoResult = $this->membershipZohoInvoiceService->processPaymentInvoice($payment, $user, $plan);
        } catch (Throwable $e) {
            $zohoError = $e->getMessage();
            Log::warning('Zoho test invoice trigger failed gracefully: '.$e->getMessage());
        }

        $freshPayment = $payment->fresh();

        return $this->success([
            'message' => 'Membership upgraded and verified successfully!',
            'user' => [
                'id' => $updatedUser->id,
                'name' => $updatedUser->display_name ?: trim(($updatedUser->first_name ?? '').' '.($updatedUser->last_name ?? '')),
                'email' => $updatedUser->email,
                'phone' => $updatedUser->phone,
                'membership_status' => $updatedUser->membership_status,
                'membership_expires_at' => $updatedUser->membership_expires_at?->format('Y-m-d H:i:s'),
                'gst_number' => $updatedUser->gst_number,
                'zoho_customer_id' => $updatedUser->zoho_customer_id,
                'zoho_last_invoice_id' => $updatedUser->zoho_last_invoice_id,
            ],
            'payment' => [
                'id' => $freshPayment->id,
                'order_id' => $freshPayment->razorpay_order_id,
                'payment_id' => $freshPayment->razorpay_payment_id,
                'status' => $freshPayment->status,
                'total_amount' => (float) $freshPayment->total_amount,
                'zoho_invoice_id' => $freshPayment->zoho_invoice_id,
            ],
            'zoho' => [
                'synced' => ! empty($freshPayment->zoho_invoice_id),
                'invoice_id' => $freshPayment->zoho_invoice_id,
                'invoice_number' => $zohoResult['invoice_number'] ?? null,
                'status' => $zohoResult['status'] ?? ($freshPayment->zoho_invoice_id ? 'paid' : 'pending'),
                'invoice_pdf_url' => $zohoResult['invoice_pdf_url'] ?? null,
                'error' => $zohoError,
            ],
        ]);
    }

    /**
     * Generate Signature for Test Checkout
     */
    public function testCheckoutGenerateSignature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'payment_id' => ['required', 'string'],
        ]);

        $orderId = trim($validated['order_id']);
        $paymentId = trim($validated['payment_id']);
        $secret = (string) config('razorpay.key_secret');

        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret ?: 'test_secret');

        return $this->success([
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'signature' => $signature,
        ]);
    }

    /**
     * Circle Test Checkout: Init data
     */
    public function circleTestCheckoutInit(): JsonResponse
    {
        $joinRequests = CircleJoinRequest::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,membership_status,zoho_customer_id',
                'circle',
                'cdApprovedBy:id,first_name,last_name,display_name',
                'idApprovedBy:id,first_name,last_name,display_name',
            ])
            ->latest('created_at')
            ->limit(50)
            ->get();

        $circles = Circle::query()
            ->select(['id', 'name', 'slug', 'status', 'circle_price_amount', 'circle_price_currency'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->select(['id', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'membership_status', 'zoho_customer_id'])
            ->whereNotNull('email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($u) {
                $name = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                return [
                    'id' => $u->id,
                    'name' => $name ?: $u->email,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'membership_status' => $u->membership_status ?? 'no_status',
                    'zoho_customer_id' => $u->zoho_customer_id,
                ];
            });

        $circlePlans = MembershipPlan::query()
            ->circleOnly()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                $amounts = $this->membershipService->calculateAmounts($p);
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'price' => (float) $p->price,
                    'gst_amount' => $amounts['gst_amount'],
                    'total_amount' => $amounts['total_amount'],
                ];
            });

        $recentPayments = Payment::query()
            ->with(['user:id,first_name,last_name,display_name,email', 'plan'])
            ->where('provider', 'razorpay')
            ->orWhereNotNull('razorpay_order_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'created_at' => $pm->created_at?->format('Y-m-d H:i:s'),
                    'user_id' => $pm->user_id,
                    'user_name' => $pm->user ? ($pm->user->display_name ?: trim(($pm->user->first_name ?? '').' '.($pm->user->last_name ?? ''))) : 'Unknown',
                    'user_email' => $pm->user?->email,
                    'plan_name' => $pm->plan?->name ?? 'Circle Plan',
                    'total_amount' => (float) $pm->total_amount,
                    'razorpay_order_id' => $pm->razorpay_order_id,
                    'razorpay_payment_id' => $pm->razorpay_payment_id,
                    'status' => $pm->status,
                    'zoho_invoice_id' => $pm->zoho_invoice_id,
                ];
            });

        return $this->success([
            'join_requests' => $joinRequests,
            'circles' => $circles,
            'users' => $users,
            'circle_plans' => $circlePlans,
            'recent_payments' => $recentPayments,
            'razorpay_key_id' => (string) config('razorpay.key_id'),
        ]);
    }

    /**
     * Circle Test Checkout: Create Join Request
     */
    public function circleTestCheckoutCreateRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'circle_id' => ['required', 'string', 'exists:circles,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $circle = Circle::query()->findOrFail($validated['circle_id']);

        try {
            $joinRequest = $this->joinRequestService->submitRequest(
                $user,
                $circle,
                $validated['reason'] ?? 'Tester Join Request'
            );

            return $this->success($joinRequest->load(['user', 'circle']), 'Circle join request created successfully.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Circle Test Checkout: CD Approve
     */
    public function circleTestCheckoutApproveCd(Request $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->findOrFail($id);
        $user = $request->user() ?: User::query()->first();

        try {
            $updated = $this->joinRequestService->approveByChapterDirector(
                $joinRequest,
                $user,
                'Tester Chapter Director approval'
            );

            return $this->success($updated->fresh(['user', 'circle']), 'Chapter Director approved successfully.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Circle Test Checkout: ID Approve
     */
    public function circleTestCheckoutApproveId(Request $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->findOrFail($id);
        $user = $request->user() ?: User::query()->first();

        try {
            $updated = $this->joinRequestService->approveByInitiativeDirector(
                $joinRequest,
                $user,
                'Tester Initiative Director approval'
            );

            return $this->success($updated->fresh(['user', 'circle']), 'Initiative Director approved successfully.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Circle Test Checkout: Create Payment Order
     */
    public function circleTestCheckoutCreateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'join_request_id' => ['required', 'string', 'exists:circle_join_requests,id'],
            'membership_plan_id' => ['nullable', 'string', 'exists:membership_plans,id'],
        ]);

        $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])->findOrFail($validated['join_request_id']);

        if (! empty($validated['membership_plan_id'])) {
            $plan = MembershipPlan::query()->findOrFail($validated['membership_plan_id']);
            $joinRequest->circle->payment_plan_id = $plan->id;
        }

        try {
            $orderData = $this->circleJoinPaymentService->createOrder(
                $joinRequest,
                $joinRequest->user
            );

            return $this->success($orderData, 'Circle payment order created successfully.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Circle Test Checkout: Verify
     */
    public function circleTestCheckoutVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['nullable', 'string'],
            'join_request_id' => ['nullable', 'string', 'exists:circle_join_requests,id'],
        ]);

        $orderId = trim((string) $validated['razorpay_order_id']);
        $paymentId = trim((string) $validated['razorpay_payment_id']);
        $signature = trim((string) ($validated['razorpay_signature'] ?? ''));

        $secret = (string) config('razorpay.key_secret');
        if (($signature === '' || $signature === 'test') && $secret !== '') {
            $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
        }

        $joinRequest = null;
        if ($orderId !== '') {
            $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])
                ->where('notes->razorpay_order_id', $orderId)
                ->first();

            if (! $joinRequest) {
                $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();
                if ($payment && $payment->circle_join_request_id) {
                    $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])->find($payment->circle_join_request_id);
                }
            }
        }

        if (! $joinRequest && ! empty($validated['join_request_id'])) {
            $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])->find($validated['join_request_id']);
        }

        if (! $joinRequest) {
            return $this->error("No Circle join request found matching order ID: {$orderId}.", 422);
        }

        $user = $joinRequest->user;
        if (! $user) {
            return $this->error('Associated user for this join request was not found.', 422);
        }

        // Auto-advance approvals in test mode if still pending
        if ($joinRequest->status === CircleJoinRequest::STATUS_PENDING_CD_APPROVAL) {
            $joinRequest->status = CircleJoinRequest::STATUS_PENDING_ID_APPROVAL;
            $joinRequest->cd_approved_at = now();
            $joinRequest->save();
        }
        if ($joinRequest->status === CircleJoinRequest::STATUS_PENDING_ID_APPROVAL) {
            $joinRequest->status = CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE;
            $joinRequest->id_approved_at = now();
            $joinRequest->save();
        }

        $notes = is_array($joinRequest->notes) ? $joinRequest->notes : [];
        if (($notes['razorpay_order_id'] ?? '') !== $orderId) {
            $notes['razorpay_order_id'] = $orderId;
            $notes['payment_gateway'] = 'razorpay';
            $joinRequest->notes = $notes;
            $joinRequest->save();
        }

        try {
            $updated = $this->circleJoinPaymentService->verifyPayment(
                $joinRequest,
                $user,
                $orderId,
                $paymentId,
                $signature
            );

            $payment = Payment::query()->where('razorpay_order_id', $orderId)->first();

            $isMember = CircleMember::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $joinRequest->circle_id)
                ->whereNull('deleted_at')
                ->where('status', 'approved')
                ->exists();

            return $this->success([
                'join_request' => $updated,
                'payment' => $payment,
                'is_circle_member' => $isMember,
                'zoho_invoice_id' => $payment?->zoho_invoice_id,
            ], 'Payment verified, Circle membership finalized, and Zoho invoice processed successfully.');
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    private function generateUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $counter = 1;

        while (MembershipPlan::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function generateUniqueCircleSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'circle-plan';
        }

        if (! str_starts_with($base, 'circle-') && ! str_starts_with($base, 'circle_')) {
            $base = 'circle-'.$base;
        }

        $slug = $base;
        $counter = 1;

        while (MembershipPlan::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
