<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreAdminReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreDashboardController extends BaseApiController
{
    protected StoreAdminReportService $reportService;

    public function __construct(StoreAdminReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(): JsonResponse
    {
        $metrics = $this->reportService->getDashboardMetrics();

        return $this->success($metrics, 'Dashboard metrics retrieved');
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);
        $sales = $this->reportService->getSalesReport($filters);
        $redemptions = $this->reportService->getCoinRedemptionReport($filters);
        $issuance = $this->reportService->getCoinIssuanceReport($filters);

        return $this->success([
            'sales' => $sales,
            'redemptions' => $redemptions,
            'issuance' => $issuance,
        ], 'Summary metrics retrieved');
    }

    public function pendingActions(): JsonResponse
    {
        $metrics = $this->reportService->getDashboardMetrics();

        return $this->success([
            'pending_orders' => $metrics['pending_orders'],
            'stuck_orders' => $metrics['stuck_orders'],
            'pending_returns' => $metrics['pending_returns'],
            'pending_adjustments' => $metrics['pending_wallet_adjustments'],
            'low_stock_products' => $metrics['low_stock_products'],
        ], 'Pending operational actions retrieved');
    }
}
