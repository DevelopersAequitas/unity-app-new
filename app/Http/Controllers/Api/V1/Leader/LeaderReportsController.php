<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderSubmitReportRequest;
use App\Services\Leader\LeaderReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderReportsController extends Controller
{
    public function __construct(
        private readonly LeaderReportsService $reportsService,
    ) {}

    /**
     * Get submitted performance reports list scoped to circle or district.
     */
    public function index(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $type = $request->query('type') ? (string) $request->query('type') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;

        $data = $this->reportsService->listReports($circleId, $type, $districtId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Submit a weekly or monthly performance report.
     */
    public function store(LeaderSubmitReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user ? (string) $user->id : '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5';

        $reportId = $this->reportsService->submitReport($request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully!',
            'data' => [
                'report_id' => $reportId,
            ],
        ], 201);
    }

    /**
     * Get attendance trend spline data points.
     */
    public function attendanceTrend(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $data = $this->reportsService->getAttendanceTrend($circleId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get report dynamic pre-signed / download link.
     */
    public function download(string $id): JsonResponse
    {
        $data = $this->reportsService->getDownloadUrl($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
