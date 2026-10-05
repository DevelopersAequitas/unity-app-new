<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Membership\MembershipZohoInvoiceService;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Razorpay\Api\Api;
use Throwable;

class RazorpayTestCheckoutController extends Controller
{
    public function __construct(
        private readonly MembershipService $membershipService,
        private readonly MembershipZohoInvoiceService $membershipZohoInvoiceService,
    ) {}

    public function index(): View
    {
        $plans = MembershipPlan::query()
            ->membershipOnly()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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
            ->get();

        $recentPayments = Payment::query()
            ->with(['user:id,first_name,last_name,display_name,email,membership_status,zoho_customer_id,zoho_last_invoice_id,gst_number', 'plan:id,name,price'])
            ->where('provider', 'razorpay')
            ->orWhereNotNull('razorpay_order_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.unity-peers-plans.test-checkout', [
            'plans' => $plans,
            'users' => $users,
            'recentPayments' => $recentPayments,
            'razorpayKeyId' => (string) config('razorpay.key_id'),
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'membership_plan_id' => ['required', 'string', 'exists:membership_plans,id'],
            'gst_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $plan = MembershipPlan::query()->findOrFail($validated['membership_plan_id']);

        if ($plan->is_free) {
            return response()->json(['success' => false, 'message' => 'Free plans do not require a payment order.'], 422);
        }

        $gstNumber = ! empty($validated['gst_number']) ? trim((string) $validated['gst_number']) : null;
        if (! empty($gstNumber)) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        $amounts = $this->membershipService->calculateAmounts($plan);
        $paymentId = (string) Str::uuid();

        try {
            $api = new Api((string) config('razorpay.key_id'), (string) config('razorpay.key_secret'));
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
        } catch (Throwable $e) {
            Log::error('Razorpay test checkout order creation failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Razorpay order: '.$e->getMessage(),
            ], 500);
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
            'razorpay_order_id' => $order['id'],
            'gst_number' => $gstNumber,
            'status' => Payment::STATUS_CREATED,
            'provider' => 'razorpay',
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $order['id'],
            'amount' => (int) $order['amount'],
            'currency' => $order['currency'],
            'key_id' => (string) config('razorpay.key_id'),
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

    public function verify(Request $request): JsonResponse
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
            return response()->json([
                'success' => false,
                'message' => 'Payment record with order_id ['.$orderId.'] was not found in the database. Please create the order first.',
            ], 404);
        }

        $user = User::query()->find($payment->user_id);
        $plan = MembershipPlan::query()->find($payment->membership_plan_id);

        if (! $user || ! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Associated user or membership plan is missing.',
            ], 404);
        }

        $gstNumber = $reqGstNumber ?: ($payment->gst_number ?: $user->gst_number);
        if (! empty($gstNumber) && empty($user->gst_number)) {
            $user->forceFill(['gst_number' => $gstNumber])->save();
        }

        // Verify Razorpay signature unless explicitly skipped for test debugging
        if (! $skipSignature) {
            $expectedSignature = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('razorpay.key_secret'));
            if (! hash_equals($expectedSignature, (string) $signature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment signature. Razorpay verification failed.',
                    'hint' => 'You can click "Auto-Generate Valid Signature" to produce a valid HMAC for this order and payment id, or check "Skip signature check".',
                ], 422);
            }
        }

        // 1. Activate Membership inside DB transaction
        $updatedUser = DB::transaction(function () use ($payment, $paymentId, $signature, $user, $plan, $gstNumber): User {
            $lockedPayment = Payment::query()->where('id', $payment->id)->lockForUpdate()->firstOrFail();

            $lockedPayment->update([
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature ?: $lockedPayment->razorpay_signature,
                'status' => Payment::STATUS_SUCCESS,
                'paid_at' => now(),
                'provider' => 'razorpay',
                'gst_number' => $gstNumber ?: $lockedPayment->gst_number,
            ]);

            return $this->membershipService->activateMembership($user, $plan, $lockedPayment);
        });

        // 2. Automatically create & pay invoice in Zoho
        $zohoResult = null;
        $zohoError = null;

        try {
            $zohoResult = $this->membershipZohoInvoiceService->createPaidInvoiceForMembership(
                $updatedUser->fresh(),
                $plan,
                $payment->fresh()
            );
        } catch (Throwable $e) {
            $zohoError = $e->getMessage();
            Log::error('Zoho test invoice creation error', ['error' => $zohoError]);
        }

        $freshUser = $updatedUser->fresh();
        $freshPayment = $payment->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Payment verified and membership activated successfully!',
            'payment' => [
                'id' => $freshPayment->id,
                'order_id' => $freshPayment->razorpay_order_id,
                'payment_id' => $freshPayment->razorpay_payment_id,
                'status' => $freshPayment->status,
                'total_amount' => (float) $freshPayment->total_amount,
                'gst_number' => $freshPayment->gst_number,
                'paid_at' => $freshPayment->paid_at?->toIso8601String(),
                'zoho_invoice_id' => $freshPayment->zoho_invoice_id,
            ],
            'user' => [
                'id' => $freshUser->id,
                'name' => $freshUser->display_name ?: trim(($freshUser->first_name ?? '').' '.($freshUser->last_name ?? '')),
                'email' => $freshUser->email,
                'membership_status' => $freshUser->membership_status,
                'membership_status_label' => match (strtolower(trim(str_replace(' ', '_', (string) $freshUser->membership_status)))) {
                    'only_unity_peer', 'global_peer' => 'Global Peer',
                    default => Str::headline(str_replace('_', ' ', (string) $freshUser->membership_status)),
                },
                'membership_starts_at' => $freshUser->membership_starts_at,
                'membership_ends_at' => $freshUser->membership_ends_at,
                'zoho_customer_id' => $freshUser->zoho_customer_id,
                'zoho_last_invoice_id' => $freshUser->zoho_last_invoice_id,
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

    public function generateSignature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'payment_id' => ['required', 'string'],
        ]);

        $orderId = trim($validated['order_id']);
        $paymentId = trim($validated['payment_id']);
        $secret = (string) config('razorpay.key_secret');

        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        return response()->json([
            'success' => true,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'signature' => $signature,
        ]);
    }
}
