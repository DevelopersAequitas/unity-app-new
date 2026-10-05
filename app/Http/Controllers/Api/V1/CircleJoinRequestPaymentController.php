<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\VerifyCirclePaymentRequest;
use App\Models\CircleJoinRequest;
use App\Services\Circles\CircleJoinPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CircleJoinRequestPaymentController extends BaseApiController
{
    public function __construct(
        private readonly CircleJoinPaymentService $circleJoinPaymentService,
    ) {}

    /**
     * Create a Razorpay order for an approved Circle Join Request.
     *
     * POST /api/v1/circle-join-requests/{id}/payment/order
     */
    public function createOrder(Request $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->find($id);

        if (! $joinRequest) {
            return $this->error('Circle join request not found.', 404);
        }

        try {
            $gstNumber = trim((string) ($request->input('gst_number') ?? $request->input('gstin') ?? ''));
            $data = $this->circleJoinPaymentService->createOrder(
                $joinRequest,
                $request->user(),
                $gstNumber !== '' ? $gstNumber : null
            );

            return $this->success($data, null);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }

    /**
     * Verify Razorpay payment and finalize Circle membership.
     *
     * POST /api/v1/circle-join-requests/{id}/payment/verify
     */
    public function verify(VerifyCirclePaymentRequest $request, string $id): JsonResponse
    {
        $joinRequest = CircleJoinRequest::query()->find($id);

        if (! $joinRequest) {
            return $this->error('Circle join request not found.', 404);
        }

        try {
            $gstNumber = trim((string) ($request->validated('gst_number') ?? $request->validated('gstin') ?? $request->input('gst_number') ?? $request->input('gstin') ?? ''));
            $updated = $this->circleJoinPaymentService->verifyPayment(
                $joinRequest,
                $request->user(),
                (string) $request->validated('razorpay_order_id'),
                (string) $request->validated('razorpay_payment_id'),
                (string) $request->validated('razorpay_signature'),
                $gstNumber !== '' ? $gstNumber : null
            );

            return $this->success([
                'join_request_id' => (string) $updated->id,
                'circle_id' => (string) $updated->circle_id,
                'status' => (string) $updated->status,
                'fee_paid_at' => $updated->fee_paid_at?->toIso8601String(),
                'membership_status' => $updated->user?->membership_status ?? 'circle_peer',
            ], 'Payment verified and Circle membership activated successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (HttpException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        }
    }
}
