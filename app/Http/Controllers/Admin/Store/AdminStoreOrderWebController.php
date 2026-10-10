<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Mail\StoreOrderStatusMail;
use App\Models\Store\Order;
use App\Models\Store\OrderStatusHistory;
use App\Services\PushNotificationService;
use App\Services\Store\OrderLifecycleService;
use App\Services\Store\OrderSlipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            'out_for_delivery' => 'Out for Delivery',
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
            'items.variant',
            'pickupPoint',
            'statusHistory',
        ])->findOrFail($id);

        // Always show all 4 progression & operational statuses in the dropdown
        $allStatuses = [
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
        ];

        return view('admin.store.orders.show', compact('order', 'allStatuses'));
    }

    public function updateStatus(Request $request, string $id)
    {
        $order = Order::with(['user', 'items'])->findOrFail($id);
        $rawStatus = (string) $request->input('status');
        $newStatus = strtolower($rawStatus);
        $notes = $request->input('notes', 'Status update');
        $admin = Auth::guard('admin')->user();

        // Map legacy ready_for_pickup to out_for_delivery
        if ($newStatus === 'ready_for_pickup') {
            $newStatus = 'out_for_delivery';
        }

        // When status is Out for Delivery, courier/delivery person name & phone are mandatory
        if ($newStatus === 'out_for_delivery') {
            $request->validate([
                'delivery_person_name' => 'required|string|max:255',
                'delivery_person_phone' => 'required|string|max:50',
            ], [
                'delivery_person_name.required' => 'Courier or Delivery Person Name is required for Out for Delivery status.',
                'delivery_person_phone.required' => 'Tracking / AWB or Contact Number is required for Out for Delivery status.',
            ]);

            $order->delivery_person_name = $request->input('delivery_person_name');
            $order->courier_name = $request->input('delivery_person_name');
            $order->delivery_person_phone = $request->input('delivery_person_phone');
            $order->tracking_number = $request->input('delivery_person_phone');
        } elseif ($newStatus === 'shipped') {
            if ($request->filled('delivery_person_name')) {
                $order->courier_name = $request->input('delivery_person_name');
                $order->delivery_person_name = $request->input('delivery_person_name');
            }
            if ($request->filled('delivery_person_phone')) {
                $order->tracking_number = $request->input('delivery_person_phone');
                $order->delivery_person_phone = $request->input('delivery_person_phone');
            }
        }

        $fromStatus = $order->status;
        $dbStatus = strtoupper($newStatus);
        $order->status = $dbStatus;

        // When order is marked Delivered, generate and store the official packing slip PDF
        if ($newStatus === 'delivered') {
            try {
                $slipService = app(OrderSlipService::class);
                $slipUrl = $slipService->generateAndSaveSlipPdf($order);
                if ($slipUrl) {
                    $order->slip_url = $slipUrl;
                }
            } catch (\Throwable $e) {
                Log::error('Order slip generation failed: '.$e->getMessage());
            }
        }

        $order->save();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $dbStatus,
            'note' => $notes,
            'reason' => $notes,
            'changed_by' => $admin ? (string) $admin->id : null,
            'created_at' => now(),
        ]);

        // Send In-App & Email Notifications
        $this->notifyOrderStatusChange($order, $newStatus, $notes);

        return back()->with('success', "Order status changed from {$fromStatus} to ".ucfirst(str_replace('_', ' ', $newStatus)).'.');
    }

    public function packingSlip(string $id)
    {
        $order = Order::with(['user', 'items.product', 'items.variant', 'pickupPoint'])->findOrFail($id);

        return view('admin.store.orders.packing-slip', compact('order'));
    }

    public function downloadSlipPdf(string $id)
    {
        $order = Order::with(['user', 'items.product', 'items.variant'])->findOrFail($id);
        $slipService = app(OrderSlipService::class);
        $slipUrl = $slipService->getOrGenerateSlipUrl($order);

        $orderNo = $order->order_no ?: $order->id;
        $fileName = 'slip_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderNo).'.pdf';
        $filePath = storage_path('app/public/slips'.DIRECTORY_SEPARATOR.$fileName);

        if (file_exists($filePath)) {
            return response()->download($filePath, $fileName);
        }

        return redirect($slipUrl);
    }

    public function dispatchShipment(Request $request, string $id)
    {
        $order = Order::with('user')->findOrFail($id);
        $courierName = $request->input('courier_name', 'BlueDart');
        $trackingNumber = $request->input('tracking_number');

        $fromStatus = $order->status;
        $order->update([
            'status' => 'SHIPPED',
            'courier_name' => $courierName,
            'delivery_person_name' => $courierName,
            'tracking_number' => $trackingNumber,
            'delivery_person_phone' => $trackingNumber,
        ]);

        $admin = Auth::guard('admin')->user();
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => 'SHIPPED',
            'note' => "Dispatched via {$courierName} (AWB: {$trackingNumber})",
            'reason' => "Dispatched via {$courierName} (AWB: {$trackingNumber})",
            'changed_by' => $admin ? (string) $admin->id : null,
            'created_at' => now(),
        ]);

        $this->notifyOrderStatusChange($order, 'shipped', "Dispatched via {$courierName} (AWB: {$trackingNumber})");

        return back()->with('success', 'Order marked as shipped with courier AWB details.');
    }

    public function verifyPickup(Request $request, string $id)
    {
        $order = Order::with('user')->findOrFail($id);
        $pickupPin = $request->input('pickup_pin');

        $fromStatus = $order->status;
        $order->update([
            'status' => 'DELIVERED',
            'pickup_pin' => $pickupPin,
        ]);

        try {
            $slipService = app(OrderSlipService::class);
            $slipUrl = $slipService->generateAndSaveSlipPdf($order);
            if ($slipUrl) {
                $order->update(['slip_url' => $slipUrl]);
            }
        } catch (\Throwable $e) {
            Log::error('Order slip generation failed: '.$e->getMessage());
        }

        $admin = Auth::guard('admin')->user();
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => 'DELIVERED',
            'note' => 'Customer collected merchandise at Hub. PIN verified by '.($admin ? $admin->name : 'Admin'),
            'reason' => 'Customer collected merchandise at Hub. PIN verified by '.($admin ? $admin->name : 'Admin'),
            'changed_by' => $admin ? (string) $admin->id : null,
            'created_at' => now(),
        ]);

        $this->notifyOrderStatusChange($order, 'delivered', 'Customer pickup verified.');

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

            $this->notifyOrderStatusChange($order, 'cancelled', $reason);

            return back()->with('success', 'Order cancelled and coins refunded.');
        } catch (\Exception $e) {
            return back()->with('error', 'Order cancel failed: '.$e->getMessage());
        }
    }

    /**
     * Send in-app push and email notifications for order status changes.
     */
    protected function notifyOrderStatusChange(Order $order, string $status, ?string $notes = null): void
    {
        $user = $order->user;
        if (! $user) {
            return;
        }

        $orderNo = $order->order_no ?: $order->id;
        $statusKey = strtolower($status);
        $itemCount = $order->items ? $order->items->count() : 1;
        $totalCoins = number_format($order->total_coins);

        $notificationTitle = match ($statusKey) {
            'processing' => 'Order In Processing',
            'shipped' => 'Order Shipped',
            'out_for_delivery', 'ready_for_pickup' => 'Order Out for Delivery',
            'delivered' => 'Order Delivered Successfully',
            'cancelled' => 'Order Cancelled & Refunded',
            default => 'Order Status Updated',
        };

        $notificationBody = match ($statusKey) {
            'processing' => "Your order #{$orderNo} ({$itemCount} item".($itemCount > 1 ? 's' : '').", {$totalCoins} coins) is now being processed by our store fulfillment team.",
            'shipped' => "Your order #{$orderNo} has been dispatched! Total: {$totalCoins} coins. Track your package in the app.",
            'out_for_delivery', 'ready_for_pickup' => "Order #{$orderNo} is Out for Delivery today! Delivery Partner: ".($order->delivery_person_name ?: 'Courier').($order->delivery_person_phone ? " (Contact: {$order->delivery_person_phone})" : '').'.',
            'delivered' => "Your order #{$orderNo} has been delivered! Your official packing slip PDF is ready to view & download.",
            'cancelled' => "Your order #{$orderNo} has been cancelled. {$totalCoins} coins have been refunded back to your wallet.",
            default => "Your order #{$orderNo} status is now: ".ucfirst(str_replace('_', ' ', $status)).'.',
        };

        // 1. In-App Notification & Push
        try {
            $payload = [
                'order_id' => (string) $order->id,
                'order_no' => (string) $orderNo,
                'status' => $status,
                'total_coins' => (int) $order->total_coins,
                'notification_type' => 'store_order_status',
                'notifiable_type' => 'order',
                'notifiable_id' => (string) $order->id,
                'screen' => 'order_details',
            ];

            if ($order->delivery_person_name) {
                $payload['delivery_person_name'] = $order->delivery_person_name;
                $payload['delivery_person_phone'] = $order->delivery_person_phone;
            }
            if ($order->slip_url) {
                $payload['slip_url'] = $order->slip_url;
            }

            // Directly insert into app_notifications so it is always stored with proper category & type
            try {
                \App\Models\Notifications\AppNotification::create([
                    'user_id' => $user->id,
                    'type' => 'store_order_status',
                    'category' => 'store',
                    'title' => $notificationTitle,
                    'body' => $notificationBody,
                    'message' => $notificationBody,
                    'channel' => 'in_app',
                    'priority' => 'high',
                    'reference_type' => 'order',
                    'reference_id' => (string) $order->id,
                    'screen' => '/order-details',
                    'data' => $payload,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Direct AppNotification create error: '.$e->getMessage());
            }

            if (class_exists(PushNotificationService::class)) {
                app(PushNotificationService::class)->storeAndSend(
                    $user,
                    $notificationTitle,
                    $notificationBody,
                    $payload,
                    $payload
                );
            }
        } catch (\Throwable $e) {
            Log::error('In-app notification failed for order status change: '.$e->getMessage());
        }

        // 2. Email Notification
        if (! empty($user->email)) {
            try {
                Mail::to($user->email)->send(new StoreOrderStatusMail($order, $status, $notes, $order->slip_url));
            } catch (\Throwable $e) {
                Log::error('Order status email sending failed: '.$e->getMessage());
            }
        }
    }
}
