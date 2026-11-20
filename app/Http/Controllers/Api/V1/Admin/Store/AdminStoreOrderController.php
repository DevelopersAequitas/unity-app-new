<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminOrderStatusRequest;
use App\Http\Requests\Store\Admin\AdminShipmentRequest;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\OrderStatusHistory;
use App\Models\Store\Shipment;
use App\Services\Store\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreOrderController extends BaseApiController
{
    protected OrderLifecycleService $lifecycleService;

    public function __construct(OrderLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items.product', 'shipment']);

        $tab = $request->input('tab');
        if ($tab && $tab !== 'all') {
            if ($tab === 'pending') {
                $query->whereIn('status', ['pending', 'placed', 'confirmed', 'PLACED', 'CONFIRMED']);
            } elseif ($tab === 'processing') {
                $query->whereIn('status', ['processing', 'packing', 'packed', 'PROCESSING', 'PACKING', 'PACKED']);
            } elseif ($tab === 'shipped') {
                $query->whereIn('status', ['shipped', 'dispatched', 'SHIPPED', 'DISPATCHED']);
            } elseif ($tab === 'out_for_delivery') {
                $query->whereIn('status', ['out_for_delivery', 'ready_for_pickup', 'OUT_FOR_DELIVERY', 'READY_FOR_PICKUP']);
            } elseif ($tab === 'delivered') {
                $query->whereIn('status', ['delivered', 'picked_up', 'completed', 'DELIVERED', 'PICKED_UP', 'COMPLETED']);
            } elseif ($tab === 'cancelled') {
                $query->whereIn('status', ['cancelled', 'refunded', 'CANCELLED', 'REFUNDED']);
            } else {
                $query->where('status', 'ILIKE', $tab);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'ILIKE', "%{$search}%")
                    ->orWhere('courier_name', 'ILIKE', "%{$search}%")
                    ->orWhere('tracking_number', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($orderNo = $request->input('order_no')) {
            $query->where('order_no', 'ILIKE', "%{$orderNo}%");
        }
        if ($userId = $request->input('peer_id')) {
            $query->where('user_id', $userId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', 'ILIKE', $status);
        }
        if ($deliveryMode = $request->input('delivery_mode')) {
            $query->where('delivery_type', $deliveryMode);
        }

        $perPage = (int) $request->input('per_page', 20);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($orders, 'Admin orders retrieved');
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with([
            'user',
            'items.product.primaryImage',
            'items.variant',
            'payments.ledgerTransaction',
            'statusHistory.changer',
            'shipment.events',
            'returns.photos',
            'refunds',
            'receipt',
        ])->findOrFail($id);

        return $this->success($order, 'Order retrieved');
    }

    public function updateStatus(AdminOrderStatusRequest $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $newStatus = strtoupper($request->input('status'));
        $fromStatus = $order->status;
        $adminUser = $request->user();

        if ($newStatus === 'READY_FOR_PICKUP') {
            $order = $this->lifecycleService->markReadyForPickup($order);

            return $this->success($order, 'Order marked as Ready for Pickup');
        }

        $updateData = ['status' => $newStatus];
        if ($request->filled('courier_name')) {
            $updateData['courier_name'] = $request->input('courier_name');
            $updateData['delivery_person_name'] = $request->input('courier_name');
        }
        if ($request->filled('tracking_number')) {
            $updateData['tracking_number'] = $request->input('tracking_number');
            $updateData['delivery_person_phone'] = $request->input('tracking_number');
        }
        if ($request->filled('note')) {
            $updateData['notes'] = $request->input('note');
        }
        $order->update($updateData);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'changed_by' => $adminUser ? $adminUser->id : null,
            'reason' => $request->input('note') ?? "Status changed to {$newStatus}",
            'created_at' => now(),
        ]);

        $eventMap = [
            'CONFIRMED' => 'order.confirmed',
            'PROCESSING' => 'order.processing',
            'PACKED' => 'order.packed',
            'SHIPPED' => 'order.shipped',
            'OUT_FOR_DELIVERY' => 'order.out_for_delivery',
            'DELIVERED' => 'order.delivered',
            'CANCELLED' => 'order.cancelled',
        ];

        if (isset($eventMap[$newStatus])) {
            NotificationEvent::create([
                'event_key' => $eventMap[$newStatus],
                'user_id' => $order->user_id,
                'reference_type' => 'ORDER',
                'reference_id' => $order->id,
                'payload' => ['order_no' => $order->order_no],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);
        }

        return $this->success($order->fresh(['statusHistory']), 'Order status updated successfully');
    }

    public function addNote(Request $request, string $id): JsonResponse
    {
        $request->validate(['note' => 'required|string|max:1000']);
        $order = Order::findOrFail($id);
        $order->update(['notes' => $request->input('note')]);

        return $this->success($order, 'Note added to order');
    }

    public function packingSlip(string $id): JsonResponse
    {
        $order = Order::with(['user', 'items.product', 'items.variant'])->findOrFail($id);

        return $this->success([
            'order_no' => $order->order_no,
            'placed_at' => $order->placed_at,
            'shipping_address' => $order->shipping_address,
            'items' => $order->items,
        ], 'Packing slip details retrieved');
    }

    public function createShipment(AdminShipmentRequest $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $data = $request->validated();
        $data['order_id'] = $order->id;
        $data['status'] = 'DISPATCHED';
        $data['shipped_at'] = now();

        $shipment = Shipment::updateOrCreate(['order_id' => $order->id], $data);

        $order->update(['status' => 'SHIPPED']);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => 'PACKED',
            'to_status' => 'SHIPPED',
            'reason' => "Shipped via {$data['courier']} AWB: {$data['awb']}",
            'created_at' => now(),
        ]);

        return $this->success($shipment, 'Shipment created and order marked as shipped', 201);
    }

    public function verifyPickup(Request $request, string $id): JsonResponse
    {
        $request->validate(['pickup_code' => 'required|string']);
        $order = Order::findOrFail($id);

        $verified = $this->lifecycleService->verifyPickup($order, $request->input('pickup_code'), $request->user());
        if (! $verified) {
            return $this->error('Invalid pickup code', 422);
        }

        return $this->success(['verified' => true], 'Pickup code verified and order delivered');
    }
}
