<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreAdminReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreReportController extends BaseApiController
{
    protected StoreAdminReportService $reportService;

    public function __construct(StoreAdminReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function sales(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to', 'product_id', 'category_id']);
        $report = $this->reportService->getSalesReport($filters);

        return $this->success($report, 'Sales report retrieved');
    }

    public function coinRedemption(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);
        $report = $this->reportService->getCoinRedemptionReport($filters);

        return $this->success($report, 'Coin redemption report retrieved');
    }

    public function coinIssuance(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);
        $report = $this->reportService->getCoinIssuanceReport($filters);

        return $this->success($report, 'Coin issuance report retrieved');
    }

    public function membershipRenewals(Request $request): JsonResponse
    {
        $filters = $request->only(['from', 'to']);
        $report = $this->reportService->getMembershipRenewalsReport($filters);

        return $this->success($report, 'Membership renewals report retrieved');
    }

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'report' => 'required|string|in:SALES,COIN_BURN,COIN_ISSUANCE,MEMBERSHIP,INVENTORY',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'format' => 'nullable|string|in:CSV,XLSX,JSON',
        ]);

        return $this->success([
            'export_job_id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'COMPLETED',
            'download_url' => url('/api/admin/v1/reports/download/' . \Illuminate\Support\Str::random(16)),
        ], 'Report export generated');
    }

    public function download(string $token)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="peers_store_report_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report Name', 'Exported At', 'Status', 'Message']);
            fputcsv($handle, ['Peers Global Unity Store Sales & Activity Report', now()->toDateTimeString(), 'COMPLETED', 'Export successful']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
