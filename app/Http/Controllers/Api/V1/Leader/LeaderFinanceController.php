<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Services\Leader\LeaderFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderFinanceController extends Controller
{
    public function __construct(
        private readonly LeaderFinanceService $financeService,
    ) {}

    /**
     * Get financial metrics and collection summaries.
     */
    public function metrics(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $data = $this->financeService->getMetrics($circleId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get transactions and dues ledger list.
     */
    public function transactions(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $status = $request->query('status') ? (string) $request->query('status') : null;

        $data = $this->financeService->getTransactions($circleId, $status);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
