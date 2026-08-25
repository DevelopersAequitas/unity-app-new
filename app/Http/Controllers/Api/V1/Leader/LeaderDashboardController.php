<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Services\Leader\LeaderDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderDashboardController extends Controller
{
    public function __construct(
        private readonly LeaderDashboardService $dashboardService,
    ) {}

    /**
     * Get dashboard summary and KPI metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->dashboardService->getMetrics($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get top 5 impacters leaderboard.
     */
    public function topImpacters(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->dashboardService->getTopImpacters($circleId, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
