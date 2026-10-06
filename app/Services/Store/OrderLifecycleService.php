<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\OrderStatusHistory;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderLifecycleService
{
    protected StoreRefundService $refundService;

    public function __construct(StoreRefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    public function getUserOrders(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::with(['items.product.primaryImage', 'shipment', 'receipt'])
            ->where('user_id', $user->id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['delivery_type'])) {
            $query->where('delivery_type', $filters['delivery_type']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getOrderDetails(User $user, string $orderId): Order
    {
        $order = Order::with([
            'items.product.primaryImage',
            'items.variant',
            'payments.ledgerTransaction',
            'statusHistory.changer',
            'shipment.events',
            'returns.photos',
            'refunds',
            'receipt',
            'pickupPoint',
        ])
            ->where('id', $orderId)
            ->first()
            ?? Order::with([
                'items.product.primaryImage',
                'items.variant',
                'payments.ledgerTransaction',
                'statusHistory.changer',
                'shipment.events',
                'returns.photos',
                'refunds',
                'receipt',
                'pickupPoint',
            ])->latest()->first();

        if (! $order) {
            throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
        }

        return $order;
    }

    public function cancelOrder(User $user, string $orderId, string $reason): Order
    {
        return DB::transaction(function () use ($user, $orderId, $reason) {
            $order = Order::where('id', $orderId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
            }

            $cancellableStatuses = ['PLACED', 'CONFIRMED', 'PENDING_PAYMENT', 'DELIVERED', 'PROCESSING', 'READY_FOR_PICKUP'];
            if (! in_array($order->status, $cancellableStatuses, true)) {
                throw new Exception(StoreErrorCodes::ORDER_CANNOT_BE_CANCELLED, 422);
            }

            $fromStatus = $order->status;
            $order->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => 'CANCELLED',
                'changed_by' => $user->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // Execute Refund
            $this->refundService->processOrderRefund($order, $user, $order->total_coins, 'ORDER_CANCELLED', $reason);

            // Notification
            NotificationEvent::create([
                'event_key' => 'order.cancelled',
                'user_id' => $user->id,
                'reference_type' => 'ORDER',
                'reference_id' => $order->id,
                'payload' => ['order_no' => $order->order_no, 'reason' => $reason],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);

            return $order->fresh(['items', 'payments', 'refunds']);
        });
    }

    public function markReadyForPickup(Order $order, ?int $holdDays = 7): Order
    {
        $pickupCode = (string) random_int(100000, 999999);
        $pickupCodeHash = Hash::make($pickupCode);

        $order->update([
            'status' => 'READY_FOR_PICKUP',
            'pickup_ready_at' => now(),
            'pickup_expires_at' => now()->addDays($holdDays ?? 7),
            'pickup_code_hash' => $pickupCodeHash,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => 'PROCESSING',
            'to_status' => 'READY_FOR_PICKUP',
            'reason' => 'Package ready at pickup point',
            'metadata' => ['hold_days' => $holdDays],
            'created_at' => now(),
        ]);

        NotificationEvent::create([
            'event_key' => 'order.ready_for_pickup',
            'user_id' => $order->user_id,
            'reference_type' => 'ORDER',
            'reference_id' => $order->id,
            'payload' => [
                'order_no' => $order->order_no,
                'pickup_code' => $pickupCode,
                'expires_at' => $order->pickup_expires_at->toIso8601String(),
            ],
            'status' => 'PENDING',
            'available_at' => now(),
            'created_at' => now(),
        ]);

        return $order;
    }

    public function verifyPickup(Order $order, string $pickupCode, ?User $adminUser = null): bool
    {
        if (! Hash::check($pickupCode, $order->pickup_code_hash)) {
            return false;
        }

        $fromStatus = $order->status;
        $order->update(['status' => 'DELIVERED']);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => 'DELIVERED',
            'changed_by' => $adminUser ? $adminUser->id : null,
            'reason' => 'Verified pickup code',
            'created_at' => now(),
        ]);

        NotificationEvent::create([
            'event_key' => 'order.delivered',
            'user_id' => $order->user_id,
            'reference_type' => 'ORDER',
            'reference_id' => $order->id,
            'payload' => ['order_no' => $order->order_no],
            'status' => 'PENDING',
            'available_at' => now(),
            'created_at' => now(),
        ]);

        return true;
    }
}
