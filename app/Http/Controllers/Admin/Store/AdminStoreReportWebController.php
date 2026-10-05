<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Product;
use App\Services\Store\StoreAdminReportService;
use App\Services\Store\StoreReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStoreReportWebController extends Controller
{
    protected StoreAdminReportService $reportService;
    protected StoreReconciliationService $reconciliationService;

    public function __construct(StoreAdminReportService $reportService, StoreReconciliationService $reconciliationService)
    {
        $this->reportService = $reportService;
        $this->reconciliationService = $reconciliationService;
    }

    public function sales(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());

        $ordersQuery = DB::table('orders')->whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay()
        ]);

        $totalOrders = (int) (clone $ordersQuery)->count();
        $totalCoinsVolume = (int) (clone $ordersQuery)->sum('total_coins');
        $avgOrderCoins = $totalOrders > 0 ? round($totalCoinsVolume / $totalOrders) : 0;

        $salesByDay = DB::table('orders')
            ->select(DB::raw('DATE(created_at) as order_date'), DB::raw('COUNT(id) as orders_count'), DB::raw('COALESCE(SUM(total_coins), 0) as daily_coins'))
            ->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'), 'desc')
            ->get();

        return view('admin.store.reports.sales', compact('totalOrders', 'totalCoinsVolume', 'avgOrderCoins', 'salesByDay', 'dateFrom', 'dateTo'));
    }

    public function coinEconomy(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());

        $totalCirculation = (int) DB::table('users')->sum('coins_balance');
        $totalEarnedInCirculation = (int) DB::table('coins_ledger')->where('bucket', 'EARNED')->sum('amount');
        $totalBonusInCirculation = (int) DB::table('coins_ledger')->where('bucket', 'BONUS')->sum('amount');
        $totalBurnedInStore = (int) DB::table('coins_ledger')->where('amount', '<', 0)->sum(DB::raw('ABS(amount)'));

        $burnByDay = DB::table('coins_ledger')
            ->select(DB::raw('DATE(created_at) as burn_date'), DB::raw('COUNT(*) as burn_count'), DB::raw('COALESCE(SUM(ABS(amount)), 0) as daily_burned'))
            ->where('amount', '<', 0)
            ->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'), 'desc')
            ->get();

        return view('admin.store.reports.coin-economy', compact(
            'totalCirculation',
            'totalEarnedInCirculation',
            'totalBonusInCirculation',
            'totalBurnedInStore',
            'burnByDay',
            'dateFrom',
            'dateTo'
        ));
    }

    public function products(Request $request)
    {
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'sales_desc');

        $query = Product::with(['category'])->withCount('orderItems');

        if ($search) {
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        if ($sort === 'price_desc') {
            $query->orderBy('price_coins', 'desc');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('price_coins', 'asc');
        } else {
            $query->orderBy('order_items_count', 'desc');
        }

        $products = $query->paginate(20);

        return view('admin.store.reports.products', compact('products', 'search', 'sort'));
    }

    public function reconciliation()
    {
        $users = DB::table('users')->select('id', 'first_name', 'last_name', 'display_name', 'email', 'company_name', 'coins_balance')->limit(100)->get();
        $usersLedgerReconciliation = [];
        $totalDiscrepancyCount = 0;
        $matchedCount = 0;
        $totalVarianceCoins = 0;

        foreach ($users as $u) {
            $ledgerSum = (int) DB::table('coins_ledger')->where('user_id', $u->id)->sum('amount');
            $cached = (int) $u->coins_balance;
            $diff = $cached - $ledgerSum;
            
            if ($ledgerSum !== $cached) {
                $totalDiscrepancyCount++;
                $totalVarianceCoins += abs($diff);
            } else {
                $matchedCount++;
            }

            $userName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->display_name ?? 'Peer Member');
            $usersLedgerReconciliation[] = [
                'user_id' => $u->id,
                'user_name' => $userName,
                'email' => $u->email,
                'company_name' => $u->company_name,
                'cached_balance' => $cached,
                'ledger_sum' => $ledgerSum,
                'diff' => $diff
            ];
        }

        $totalAudited = count($users);

        return view('admin.store.reports.reconciliation', compact('usersLedgerReconciliation', 'totalDiscrepancyCount', 'matchedCount', 'totalAudited', 'totalVarianceCoins'));
    }

    public function exportSalesCsv(Request $request): StreamedResponse
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->toDateString());

        $sales = DB::table('orders')
            ->select(DB::raw('DATE(created_at) as order_date'), DB::raw('COUNT(id) as orders_count'), DB::raw('COALESCE(SUM(total_coins), 0) as total_coins'))
            ->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'), 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales_report_' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function() use ($sales) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Total Orders', 'Total Coins Volume']);

            foreach ($sales as $s) {
                fputcsv($handle, [$s->order_date, $s->orders_count, $s->total_coins]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
