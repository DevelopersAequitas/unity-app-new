<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SpeedNetworking;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpeedNetworking\SpeedCollaborationAnalyticsFilterRequest;
use App\Models\AdminUser;
use App\Services\SpeedNetworking\SpeedCollaborationAnalyticsService;
use App\Services\SpeedNetworking\SpeedCollaborationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SpeedCollaborationAnalyticsController extends Controller
{
    public function __construct(
        protected SpeedCollaborationAnalyticsService $analyticsService,
        protected SpeedCollaborationExportService $exportService
    ) {}

    public function eventsAnalytics(SpeedCollaborationAnalyticsFilterRequest $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventsAnalytics($request->validated()),
        ]);
    }

    public function eventsComparison(SpeedCollaborationAnalyticsFilterRequest $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventsComparison($request->validated()),
        ]);
    }

    public function overview(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventOverview($id),
        ]);
    }

    public function liveMonitor(SpeedCollaborationAnalyticsFilterRequest $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getLiveMonitorSnapshot($id, $request->validated()),
        ]);
    }

    public function attendees(SpeedCollaborationAnalyticsFilterRequest $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventAttendees($id, $request->validated()),
        ]);
    }

    public function peerAnalytics(Request $request, string $id, string $userId): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getPeerEventAnalytics($id, $userId),
        ]);
    }

    public function connections(SpeedCollaborationAnalyticsFilterRequest $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventConnections($id, $request->validated()),
        ]);
    }

    public function rewards(SpeedCollaborationAnalyticsFilterRequest $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventRewardsAnalytics($id, $request->validated()),
        ]);
    }

    public function reports(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getEventReports($id),
        ]);
    }

    public function exportExcel(SpeedCollaborationAnalyticsFilterRequest $request, string $id): Response
    {
        $this->authorizeAdmin($request);
        $validated = $request->validated();
        $reportType = (string) ($validated['report_type'] ?? 'full');

        $file = $this->exportService->buildExcelExport($id, $reportType, $validated);

        return response($file['content'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function exportPdf(SpeedCollaborationAnalyticsFilterRequest $request, string $id): Response
    {
        $this->authorizeAdmin($request);
        $validated = $request->validated();
        $reportType = (string) ($validated['report_type'] ?? 'full');

        $file = $this->exportService->buildPdfExport($id, $reportType, $validated);

        return response($file['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    protected function authorizeAdmin(Request $request): void
    {
        if (Auth::guard('admin')->check()) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user instanceof AdminUser) {
            return;
        }

        $role = mb_strtolower((string) ($user->role ?? ''));
        $isAdmin = (bool) ($user->is_admin ?? false)
            || in_array($role, ['admin', 'super_admin', 'super-admin'], true);

        if (! $isAdmin) {
            abort(403, 'Unauthorized: Admin access is required for Speed Collaboration analytics.');
        }
    }
}
