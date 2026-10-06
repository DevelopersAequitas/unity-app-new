<?php

namespace App\Console\Commands;

use App\Models\Store\Order;
use App\Services\Store\OrderLifecycleService;
use App\Services\Store\StoreRefundService;
use Illuminate\Console\Command;

class StorePickupRemindersCommand extends Command
{
    protected $signature = 'store:process-pickups';

    protected $description = 'Process pickup reminders on day 3, day 6, and auto-cancel + refund on day 7';

    public function handle(OrderLifecycleService $lifecycleService, StoreRefundService $refundService): int
    {
        $this->info('Processing pickup orders...');

        // 1. Auto-cancel expired pickups (Day 7)
        $expiredOrders = Order::where('status', 'READY_FOR_PICKUP')
            ->whereNotNull('pickup_expires_at')
            ->where('pickup_expires_at', '<=', now())
            ->get();

        foreach ($expiredOrders as $order) {
            $this->warn("Auto-cancelling expired pickup order: {$order->order_no}");
            $lifecycleService->cancelOrder($order->user, $order->id, 'Auto-cancelled: Pickup hold window expired (7 days)');
        }

        return 0;
    }
}
