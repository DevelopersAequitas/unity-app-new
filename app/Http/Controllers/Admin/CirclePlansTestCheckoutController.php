<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class CirclePlansTestCheckoutController extends Controller
{
    public function __construct(
        private readonly CircleJoinRequestService $joinRequestService,
        private readonly CircleJoinPaymentService $circleJoinPaymentService,
        private readonly CirclePriceResolver $priceResolver,
        private readonly MembershipZohoInvoiceService $membershipZohoInvoiceService,
    ) {}

    public function index(): View
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
            ->get();

        $circlePlans = MembershipPlan::query()
            ->circleOnly()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $recentPayments = Payment::query()
            ->with(['user:id,first_name,last_name,display_name,email', 'plan'])
            ->where('provider', 'razorpay')
            ->orWhereNotNull('razorpay_order_id')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.circle-plans.test-checkout', [
            'joinRequests' => $joinRequests,
            'circles' => $circles,
            'users' => $users,
            'circlePlans' => $circlePlans,
            'recentPayments' => $recentPayments,
            'razorpayKeyId' => (string) config('razorpay.key_id'),
        ]);
    }

    public function createRequest(Request $request): JsonResponse
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

            $joinRequest->load(['user', 'circle', 'cdApprovedBy', 'idApprovedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Circle join request created successfully.',
                'data' => $joinRequest,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function approveCd(Request $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->findOrFail($id);
        $admin = Auth::guard('admin')->user() ?? $request->user();

        try {
            $updated = $this->joinRequestService->approveByCd($joinRequest, $admin);
            $updated->load(['user', 'circle', 'cdApprovedBy', 'idApprovedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Circle Director approval completed.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function approveId(Request $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->findOrFail($id);
        $admin = Auth::guard('admin')->user() ?? $request->user();

        try {
            $updated = $this->joinRequestService->approveById($joinRequest, $admin);
            $updated->load(['user', 'circle', 'cdApprovedBy', 'idApprovedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Industry Director approval completed. Request is now pending circle fee.',
                'data' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'join_request_id' => ['required', 'string', 'exists:circle_join_requests,id'],
        ]);

        $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])->findOrFail($validated['join_request_id']);
        $user = $joinRequest->user;

        try {
            $orderData = $this->circleJoinPaymentService->createOrder($joinRequest, $user);

            return response()->json([
                'success' => true,
                'message' => 'Razorpay order created successfully for circle join.',
                'data' => $orderData,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'join_request_id' => ['required', 'string', 'exists:circle_join_requests,id'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $joinRequest = CircleJoinRequest::query()->with(['user', 'circle'])->findOrFail($validated['join_request_id']);
        $user = $joinRequest->user;

        try {
            $updated = $this->circleJoinPaymentService->verifyPayment(
                $joinRequest,
                $user,
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature']
            );

            $payment = Payment::query()->where('razorpay_order_id', $validated['razorpay_order_id'])->first();

            $isMember = CircleMember::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $joinRequest->circle_id)
                ->whereNull('deleted_at')
                ->where('status', 'approved')
                ->exists();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified, Circle membership finalized, and Zoho invoice processed successfully.',
                'data' => [
                    'join_request' => $updated,
                    'payment' => $payment,
                    'is_circle_member' => $isMember,
                    'zoho_invoice_id' => $payment?->zoho_invoice_id,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function generateSignature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'payment_id' => ['required', 'string'],
        ]);

        $orderId = trim((string) $validated['order_id']);
        $paymentId = trim((string) $validated['payment_id']);
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
