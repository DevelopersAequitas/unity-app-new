<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreCancelOrderRequest;
use App\Http\Requests\Store\StorePlaceOrderRequest;
use App\Services\Store\OrderLifecycleService;
use App\Services\Store\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    protected OrderService $orderService;

    protected OrderLifecycleService $lifecycleService;

    public function __construct(OrderService $orderService, OrderLifecycleService $lifecycleService)
    {
        $this->orderService = $orderService;
        $this->lifecycleService = $lifecycleService;
    }

    public function placeOrder(StorePlaceOrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $idempotencyKey = $request->header('Idempotency-Key');

            $result = $this->orderService->placeOrder($user, $request->validated(), $idempotencyKey);

            return $this->success($result['order'], 'Order placed successfully', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['status', 'delivery_type', 'from', 'to']);
        $perPage = (int) $request->input('per_page', 20);

        $orders = $this->lifecycleService->getUserOrders($user, $filters, $perPage);

        return $this->success($orders, 'Orders retrieved successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $order = $this->lifecycleService->getOrderDetails($user, $id);

            // Fetch or generate slip URL if delivered or available
            $slipUrl = $order->slip_url;
            $stLower = strtolower($order->status);
            if (! $slipUrl && in_array($stLower, ['delivered', 'completed', 'picked_up'])) {
                try {
                    $slipUrl = app(\App\Services\Store\OrderSlipService::class)->getOrGenerateSlipUrl($order);
                } catch (\Throwable $e) {
                    $slipUrl = route('admin.store.orders.packing-slip', $order->id);
                }
            }

            // Add action flags for mobile / Flutter
            $actionFlags = [
                'can_cancel' => in_array($order->status, ['PLACED', 'CONFIRMED', 'PENDING_PAYMENT', 'processing'], true),
                'can_return' => in_array($stLower, ['delivered', 'completed']),
                'can_track' => (bool) ($order->shipment || $order->tracking_number || $order->delivery_person_phone),
                'can_download_receipt' => (bool) $order->receipt,
                'can_download_slip' => (bool) $slipUrl,
                'can_pickup' => in_array(strtoupper($order->status), ['READY_FOR_PICKUP', 'OUT_FOR_DELIVERY']),
            ];

            $data = $order->toArray();
            $data['courier_name'] = $order->courier_name;
            $data['tracking_number'] = $order->tracking_number;
            $data['delivery_person_name'] = $order->delivery_person_name ?: $order->courier_name;
            $data['delivery_person_phone'] = $order->delivery_person_phone ?: $order->tracking_number;
            $data['slip_url'] = $slipUrl;
            $data['order_slip_url'] = $slipUrl;
            $data['action_flags'] = $actionFlags;

            return $this->success($data, 'Order details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function slip(Request $request, string $id)
    {
        try {
            $user = $request->user();
            $order = $this->lifecycleService->getOrderDetails($user, $id);
            $slipService = app(\App\Services\Store\OrderSlipService::class);
            $slipUrl = $slipService->getOrGenerateSlipUrl($order);

            $orderNo = $order->order_no ?: $order->id;
            $fileName = 'slip_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderNo) . '.pdf';
            $filePath = storage_path('app/public/slips' . DIRECTORY_SEPARATOR . $fileName);

            if (file_exists($filePath)) {
                return response()->download($filePath, $fileName);
            }

            return redirect($slipUrl);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function cancel(StoreCancelOrderRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $reason = $request->input('reason');
            $order = $this->lifecycleService->cancelOrder($user, $id, $reason);

            return $this->success($order, 'Order cancelled and coins refunded successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function statusHistory(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $order = $this->lifecycleService->getOrderDetails($user, $id);

            return $this->success($order->statusHistory, 'Order status history retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function receipt(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $order = $this->lifecycleService->getOrderDetails($user, $id);

            if (! $order->receipt) {
                return $this->error('Receipt not found for this order', 404);
            }

            return $this->success($order->receipt, 'Receipt retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function tracking(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $order = $this->lifecycleService->getOrderDetails($user, $id);

            return $this->success($order->shipment ? $order->shipment->load('events') : null, 'Tracking info retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }
}
