<?php

namespace App\Services\Store;

use App\Models\CoinsLedger;
use App\Models\Store\MembershipLedger;
use App\Models\Store\Order;
use App\Models\Store\Product;
use App\Models\Store\StoreReturn;
use App\Models\Store\WalletAdjustmentRequest;

class StoreAdminReportService
{
    public function getDashboardMetrics(): array
    {
        $today = now()->startOfDay();

        $ordersToday = Order::where('created_at', '>=', $today)->count();
        $pendingOrders = Order::whereIn('status', ['PLACED', 'CONFIRMED', 'PROCESSING'])->count();
        $stuckOrders = Order::whereIn('status', ['CONFIRMED', 'PROCESSING'])
            ->where('created_at', '<=', now()->subHours(48))
            ->count();

        $coinsRedeemedToday = (int) abs(CoinsLedger::where('amount', '<', 0)
            ->where('reference_type', 'ORDER')
            ->where('created_at', '>=', $today)
            ->sum('amount'));

        $coinsIssuedToday = (int) CoinsLedger::where('amount', '>', 0)
            ->where('created_at', '>=', $today)
            ->sum('amount');

        $lowStockProducts = Product::active()->where('track_inventory', true)->where('stock_qty', '<=', 5)->count();
        $pendingReturns = StoreReturn::where('status', 'REQUESTED')->count();
        $pendingAdjustments = WalletAdjustmentRequest::where('status', 'PENDING')->count();

        return [
            'orders_today' => $ordersToday,
            'pending_orders' => $pendingOrders,
            'stuck_orders' => $stuckOrders,
            'coins_issued_today' => $coinsIssuedToday,
            'coins_redeemed_today' => $coinsRedeemedToday,
            'low_stock_products' => $lowStockProducts,
            'pending_returns' => $pendingReturns,
            'pending_wallet_adjustments' => $pendingAdjustments,
        ];
    }

    public function getSalesReport(array $filters = []): array
    {
        $query = Order::query()->where('status', '!=', 'CANCELLED');

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $totalSalesCoins = (int) $query->sum('total_coins');
        $totalOrders = $query->count();

        return [
            'total_orders' => $totalOrders,
            'total_sales_coins' => $totalSalesCoins,
            'avg_order_value_coins' => $totalOrders > 0 ? (int) round($totalSalesCoins / $totalOrders) : 0,
        ];
    }

    public function getCoinRedemptionReport(array $filters = []): array
    {
        $query = CoinsLedger::where('amount', '<', 0);

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $totalBurn = (int) abs($query->sum('amount'));

        return [
            'total_coins_redeemed' => $totalBurn,
            'bonus_coins_redeemed' => (int) abs($query->clone()->where('bucket', 'BONUS')->sum('amount')),
            'earned_coins_redeemed' => (int) abs($query->clone()->where('bucket', 'EARNED')->sum('amount')),
        ];
    }

    public function getCoinIssuanceReport(array $filters = []): array
    {
        $query = CoinsLedger::where('amount', '>', 0);

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'total_coins_issued' => (int) $query->sum('amount'),
            'earned_issued' => (int) $query->clone()->where('bucket', 'EARNED')->sum('amount'),
            'bonus_issued' => (int) $query->clone()->where('bucket', 'BONUS')->sum('amount'),
        ];
    }

    public function getMembershipRenewalsReport(array $filters = []): array
    {
        $query = MembershipLedger::query();

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'total_renewals' => $query->count(),
            'total_coins_collected' => (int) $query->sum('coins_paid'),
        ];
    }
}
