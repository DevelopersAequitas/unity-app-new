<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\Refund;
use App\Models\Store\StoreReturn;
use App\Services\Store\StoreRefundService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreReturnController extends BaseApiController
{
    protected StoreRefundService $refundService;

    public function __construct(StoreRefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = StoreReturn::with(['order', 'user', 'photos', 'refund']);
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $returns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($returns, 'Admin returns retrieved');
    }

    public function show(string $id): JsonResponse
    {
        $return = StoreReturn::with(['order.items.product', 'user', 'photos', 'refund'])->findOrFail($id);

        return $this->success($return, 'Return details retrieved');
    }

    public function approve(string $id): JsonResponse
    {
        $return = StoreReturn::findOrFail($id);
        $return->update(['status' => 'APPROVED']);

        NotificationEvent::create([
            'event_key' => 'return.approved',
            'user_id' => $return->user_id,
            'reference_type' => 'RETURN',
            'reference_id' => $return->id,
            'payload' => ['return_no' => $return->return_no],
            'status' => 'PENDING',
            'available_at' => now(),
            'created_at' => now(),
        ]);

        return $this->success($return, 'Return request approved');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $return = StoreReturn::findOrFail($id);

        $return->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->input('reason'),
            'reviewed_at' => now(),
        ]);

        NotificationEvent::create([
            'event_key' => 'return.rejected',
            'user_id' => $return->user_id,
            'reference_type' => 'RETURN',
            'reference_id' => $return->id,
            'payload' => ['return_no' => $return->return_no, 'reason' => $request->input('reason')],
            'status' => 'PENDING',
            'available_at' => now(),
            'created_at' => now(),
        ]);

        return $this->success($return, 'Return request rejected');
    }

    public function receive(string $id): JsonResponse
    {
        $return = StoreReturn::findOrFail($id);
        $return->update([
            'status' => 'RECEIVED',
            'received_at' => now(),
        ]);

        return $this->success($return, 'Return item received at warehouse');
    }

    public function inspect(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'passed' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $return = StoreReturn::findOrFail($id);
        $return->update([
            'quality_check_passed' => $request->input('passed'),
            'quality_check_notes' => $request->input('notes'),
            'status' => $request->input('passed') ? 'APPROVED' : 'REJECTED',
        ]);

        return $this->success($return, 'Quality inspection recorded');
    }

    public function refund(Request $request, string $id): JsonResponse
    {
        $return = StoreReturn::with('order.user')->findOrFail($id);
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $refund = $this->refundService->processOrderRefund(
                $return->order,
                $return->order->user,
                $return->order->total_coins,
                'RETURN_REFUND',
                $return->reason_detail ?? 'Return processed',
                $return->id,
                $idempotencyKey,
                $request->user()
            );

            $return->update(['status' => 'COMPLETED']);

            return $this->success($refund, 'Return refunded successfully', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
