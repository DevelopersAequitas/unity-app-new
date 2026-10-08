<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Order;
use App\Models\Store\OrderStatusHistory;
use App\Services\Store\OrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminStoreOrderWebController extends Controller
{
    protected OrderLifecycleService $lifecycleService;

    public function __construct(OrderLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all');
        $search = $request->input('search', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $tabs = [
            'all' => 'All Orders',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped / Dispatched',
            'ready_for_pickup' => 'Ready for Pickup',
            'delivered' => 'Delivered / Completed',
            'cancelled' => 'Cancelled / Refunded',
        ];

        $query = Order::with(['user', 'items.product', 'pickupPoint'])->orderBy('created_at', 'desc');

        if ($tab !== 'all') {
            if ($tab === 'pending') {
                $query->whereIn('status', ['pending', 'placed', 'confirmed', 'PLACED', 'CONFIRMED']);
            } elseif ($tab === 'processing') {
                $query->whereIn('status', ['processing', 'packing', 'packed', 'PROCESSING', 'PACKING', 'PACKED']);
            } elseif ($tab === 'shipped') {
                $query->whereIn('status', ['shipped', 'dispatched', 'out_for_delivery', 'SHIPPED', 'DISPATCHED']);
            } elseif ($tab === 'ready_for_pickup') {
                $query->whereIn('status', ['ready_for_pickup', 'READY_FOR_PICKUP']);
            } elseif ($tab === 'delivered') {
                $query->whereIn('status', ['delivered', 'picked_up', 'completed', 'DELIVERED', 'PICKED_UP', 'COMPLETED']);
            } elseif ($tab === 'cancelled') {
                $query->whereIn('status', ['cancelled', 'refunded', 'CANCELLED', 'REFUNDED']);
            } else {
                $query->where('status', 'ILIKE', $tab);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_no', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone_number', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->paginate(20);

        return view('admin.store.orders.index', compact('orders', 'tab', 'search', 'dateFrom', 'dateTo', 'tabs'));
    }

    public function show(string $id)
    {
        $order = Order::with([
            'user',
            'items.product',
            'pickupPoint',
            'statusHistory',
        ])->findOrFail($id);

        $validNextTransitions = [];
        $st = strtoupper($order->status);
        if (in_array($st, ['PENDING', 'PLACED', 'CONFIRMED'])) {
            $validNextTransitions = ['processing', 'shipped', 'ready_for_pickup', 'cancelled'];
        } elseif (in_array($st, ['PROCESSING', 'PACKING', 'PACKED'])) {
            $validNextTransitions = ['shipped', 'ready_for_pickup', 'cancelled'];
        } elseif (in_array($st, ['SHIPPED', 'DISPATCHED', 'OUT_FOR_DELIVERY'])) {
            $validNextTransitions = ['delivered', 'cancelled'];
        } elseif (in_array($st, ['READY_FOR_PICKUP'])) {
            $validNextTransitions = ['delivered', 'cancelled'];
        }

        return view('admin.store.orders.show', compact('order', 'validNextTransitions'));
    }

    public function updateStatus(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $newStatus = $request->input('status');
        $notes = $request->input('notes', 'Status update');
        $admin = Auth::guard('admin')->user();

        $fromStatus = $order->status;
        $order->update(['status' => $newStatus]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $newStatus,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'notes' => $notes,
            'reason' => $notes,
            'changed_by' => $admin ? (string) $admin->id : null,
        ]);

        return back()->with('success', "Order status changed from {$fromStatus} to {$newStatus}.");
    }

    public function packingSlip(string $id)
    {
        $order = Order::with(['user', 'items.product', 'pickupPoint'])->findOrFail($id);

        return view('admin.store.orders.packing-slip', compact('order'));
    }

    public function dispatchShipment(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $courierName = $request->input('courier_name', 'BlueDart');
        $trackingNumber = $request->input('tracking_number');

        $order->update([
            'status' => 'shipped',
            'courier_name' => $courierName,
            'tracking_number' => $trackingNumber,
        ]);

        $admin = Auth::guard('admin')->user();
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'shipped',
            'from_status' => $order->status,
            'to_status' => 'shipped',
            'notes' => "Dispatched via {$courierName} (AWB: {$trackingNumber})",
            'reason' => "Dispatched via {$courierName} (AWB: {$trackingNumber})",
            'changed_by' => $admin ? (string) $admin->id : null,
        ]);

        return back()->with('success', 'Order marked as shipped with courier AWB details.');
    }

    public function verifyPickup(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $pickupPin = $request->input('pickup_pin');

        $order->update([
            'status' => 'delivered',
            'pickup_pin' => $pickupPin,
        ]);

        $admin = Auth::guard('admin')->user();
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'delivered',
            'from_status' => $order->status,
            'to_status' => 'delivered',
            'notes' => 'Customer collected merchandise at Hub. PIN verified by '.($admin ? $admin->name : 'Admin'),
            'reason' => 'Customer collected merchandise at Hub. PIN verified by '.($admin ? $admin->name : 'Admin'),
            'changed_by' => $admin ? (string) $admin->id : null,
        ]);

        return back()->with('success', 'Pickup verified and order marked as delivered.');
    }

    public function cancelOrder(Request $request, string $id)
    {
        $order = Order::with('user')->findOrFail($id);
        $reason = $request->input('reason', 'Administrative cancellation');

        try {
            if ($order->user) {
                $this->lifecycleService->cancelOrder($order->user, $order->id, $reason);
            } else {
                $order->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            }

            return back()->with('success', 'Order cancelled and coins refunded.');
        } catch (\Exception $e) {
            return back()->with('error', 'Order cancel failed: ' . $e->getMessage());
        }
    }
}
