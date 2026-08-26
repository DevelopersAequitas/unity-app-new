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
     * Get submitted performance reports list scoped to circle, district, industry or role.
     */
    public function index(Request $request): JsonResponse
    {
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;
        $reportType = $request->query('report_type') ? (string) $request->query('report_type') : ($request->query('type') ? (string) $request->query('type') : null);
        $status = $request->query('status') ? (string) $request->query('status') : null;
        $districtId = $request->query('district_id') ? (string) $request->query('district_id') : null;
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $data = $this->reportsService->listReports(
            circleId: $circleId,
            reportType: $reportType,
            status: $status,
            districtId: $districtId,
            user: $request->user(),
            page: $page,
            perPage: $perPage,
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Submit a weekly, monthly, district or industry performance report.
     */
    public function store(LeaderSubmitReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $userId = $user ? (string) $user->id : '8ef4c5ad-13c5-4b08-8e6f-cbde39df23a5';

        $data = $this->reportsService->submitReport($request->validated(), $userId, $user);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully and routed to higher leadership.',
            'data' => $data,
        ], 201);
    }

    /**
     * Get full report details with peer roster.
     */
    public function show(string $id): JsonResponse
    {
        $data = $this->reportsService->getReportDetails($id);

        return response()->json([
            'success' => true,
            'message' => 'Report details fetched successfully.',
            'data' => $data,
        ]);
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
