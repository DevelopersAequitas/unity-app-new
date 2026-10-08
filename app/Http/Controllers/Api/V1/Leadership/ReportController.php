<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends LeadershipBaseController
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /**
     * M1. Overall leadership dashboard overview.
     */
    public function overview(): JsonResponse
    {
        $stats = $this->reportingService->getDashboardOverview();

        return $this->success($stats, 'Dashboard overview stats fetched successfully.');
    }

    /**
     * M2. Nomination statistics.
     */
    public function nominations(Request $request): JsonResponse
    {
        $report = $this->reportingService->getNominationReport($request->all());

        return $this->success($report, 'Nomination report fetched successfully.');
    }

    /**
     * M3. Voting analytics.
     */
    public function voting(Request $request): JsonResponse
    {
        $report = $this->reportingService->getVotingReport($request->all());

        return $this->success($report, 'Voting report fetched successfully.');
    }

    /**
     * M4. Jury performance and completion.
     */
    public function jury(Request $request): JsonResponse
    {
        $report = $this->reportingService->getJuryReport($request->all());

        return $this->success($report, 'Jury report fetched successfully.');
    }

    /**
     * M5. Winner and publication report.
     */
    public function winners(Request $request): JsonResponse
    {
        $report = $this->reportingService->getWinnerReport($request->all());

        return $this->success($report, 'Winner report fetched successfully.');
    }

    /**
     * M6. Export filtered reports asynchronously.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|in:nominations,voting,jury,winners',
            'filters' => 'nullable|array',
            'format' => 'nullable|in:xlsx,csv,pdf',
        ]);

        $export = $this->reportingService->queueExport(
            $validated['report_type'],
            $validated['filters'] ?? [],
            $validated['format'] ?? 'xlsx'
        );

        return $this->success($export, 'Report export queued.', 202);
    }
}
