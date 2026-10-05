<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Order;
use App\Models\Store\Product;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSupportTicket;
use App\Models\Store\WalletAdjustmentRequest;
use App\Services\Store\StoreAdminReportService;
use App\Services\Store\StoreCatalogService;
use App\Services\Store\StoreConfigService;
use App\Services\Store\StoreReconciliationService;
use App\Support\AdminAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminStoreDashboardWebController extends Controller
{
    protected StoreAdminReportService $reportService;
    protected StoreConfigService $configService;

    public function __construct(StoreAdminReportService $reportService, StoreConfigService $configService)
    {
        $this->reportService = $reportService;
        $this->configService = $configService;
    }

    public function index(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Orders Today KPI
        $ordersTodayCount = Order::whereDate('created_at', $today)->count();
        $ordersTodayCoins = Order::whereDate('created_at', $today)->sum('total_coins');
        $ordersYesterdayCount = Order::whereDate('created_at', Carbon::yesterday())->count();
        $ordersGrowth = $ordersYesterdayCount > 0 
            ? round((($ordersTodayCount - $ordersYesterdayCount) / $ordersYesterdayCount) * 100, 1) 
            : ($ordersTodayCount > 0 ? 100 : 0);

        // 2. Pending Actions
        $pendingReturnsCount = StoreReturn::whereIn('status', ['PENDING', 'pending', 'PENDING_INSPECTION'])->count();
        $pendingRefundsCount = DB::table('refunds')->whereIn('status', ['PENDING', 'pending', 'PENDING_APPROVAL'])->count();
        $pendingAdjustmentsCount = WalletAdjustmentRequest::whereIn('status', ['PENDING', 'pending'])->count();
        $openTicketsCount = StoreSupportTicket::whereIn('status', ['OPEN', 'open', 'IN_PROGRESS', 'in_progress'])->count();

        // 3. Coin Economy Metrics
        $coinsIssuedToday = (int) DB::table('coins_ledger')
            ->whereDate('created_at', $today)
            ->where('amount', '>', 0)
            ->sum('amount');

        $coinsRedeemedToday = (int) DB::table('coins_ledger')
            ->whereDate('created_at', $today)
            ->where('amount', '<', 0)
            ->sum(DB::raw('ABS(amount)'));

        $coinsIssuedMonth = (int) DB::table('coins_ledger')
            ->where('created_at', '>=', $startOfMonth)
            ->where('amount', '>', 0)
            ->sum('amount');

        $coinsRedeemedMonth = (int) DB::table('coins_ledger')
            ->where('created_at', '>=', $startOfMonth)
            ->where('amount', '<', 0)
            ->sum(DB::raw('ABS(amount)'));

        $coinsInCirculation = (int) DB::table('users')->sum('coins_balance');

        // 4. Inventory Metrics
        $totalActiveProducts = Product::where('status', 'ACTIVE')->count();
        $lowStockCount = DB::table('product_variants')
            ->where('status', 'ACTIVE')
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->count();
        $outOfStockCount = DB::table('product_variants')
            ->where('status', 'ACTIVE')
            ->where('stock_quantity', '<=', 0)
            ->count();

        // 5. Recent Alerts
        $alerts = [];
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'bi-exclamation-triangle',
                'message' => "{$lowStockCount} product variants are below the low-stock threshold.",
                'link' => route('admin.store.inventory.low-stock'),
                'link_text' => 'View Inventory'
            ];
        }
        if ($pendingReturnsCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'bi-arrow-return-left',
                'message' => "{$pendingReturnsCount} return requests are awaiting quality inspection.",
                'link' => route('admin.store.returns.index'),
                'link_text' => 'Inspect Returns'
            ];
        }
        if ($pendingAdjustmentsCount > 0) {
            $alerts[] = [
                'type' => 'primary',
                'icon' => 'bi-shield-check',
                'message' => "{$pendingAdjustmentsCount} wallet adjustment requests require checker approval.",
                'link' => route('admin.store.wallet.adjustments'),
                'link_text' => 'Approval Queue'
            ];
        }
        if ($openTicketsCount > 0) {
            $alerts[] = [
                'type' => 'secondary',
                'icon' => 'bi-ticket-perforated',
                'message' => "{$openTicketsCount} customer support tickets are currently open.",
                'link' => route('admin.store.support.index'),
                'link_text' => 'View Tickets'
            ];
        }

        // 6. Recent Orders
        $recentOrders = Order::with(['user', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // 7. Top Redeemed Products
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', 'products.sku', DB::raw('COALESCE(SUM(order_items.quantity), 0) as units_sold'), DB::raw('COALESCE(SUM(order_items.total_price_coins), 0) as coins_redeemed'))
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get();

        return view('admin.store.dashboard', compact(
            'ordersTodayCount',
            'ordersTodayCoins',
            'ordersGrowth',
            'pendingReturnsCount',
            'pendingRefundsCount',
            'pendingAdjustmentsCount',
            'openTicketsCount',
            'coinsIssuedToday',
            'coinsRedeemedToday',
            'coinsIssuedMonth',
            'coinsRedeemedMonth',
            'coinsInCirculation',
            'totalActiveProducts',
            'lowStockCount',
            'outOfStockCount',
            'alerts',
            'recentOrders',
            'topProducts'
        ));
    }
}
