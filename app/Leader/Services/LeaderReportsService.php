<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Leader\Models\CirclePeerMembership;
use App\Leader\Models\LeaderReport;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LeaderReportsService
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
        private readonly LeaderPermissionService $permissionService,
        private readonly LeaderPeersService $peersService,
    ) {}

    /**
     * List submitted performance reports scoped to circle, district, industry or role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listReports(
        ?string $circleId = null,
        ?string $reportType = null,
        ?string $status = null,
        ?string $districtId = null,
        User|AdminUser|null $user = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user, $districtId);

        if ($circleId && Str::isUuid($circleId) && $scopedCircleIds !== null && ! in_array($circleId, array_map('strval', $scopedCircleIds), true)) {
            abort(403, 'You are not authorized to access reports for this circle.');
        }

        $query = LeaderReport::query()
            ->with(['circle.district', 'submitter'])
            ->when($circleId, fn (Builder $q) => $q->where('circle_id', $circleId))
            ->when(! $circleId && $scopedCircleIds !== null, function (Builder $q) use ($scopedCircleIds, $user) {
                $q->where(function (Builder $sub) use ($scopedCircleIds, $user) {
                    if (empty($scopedCircleIds)) {
                        $sub->whereRaw('1 = 0');
                    } else {
                        $sub->whereIn('circle_id', $scopedCircleIds);
                    }
                    if ($user) {
                        $sub->orWhere('submitted_by_user_id', (string) $user->id);
                    }
                });
            })
            ->when($reportType && strtolower($reportType) !== 'all', fn (Builder $q) => $q->whereRaw('LOWER(report_type) = ?', [strtolower($reportType)]))
            ->when($status && strtolower($status) !== 'all', fn (Builder $q) => $q->whereRaw('LOWER(status) = ?', [strtolower($status)]))
            ->orderByDesc('created_at');

        $reports = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        if ($reports->isEmpty()) {
            return [];
        }

        return $reports->map(function (LeaderReport $r): array {
            $circle = $r->circle;
            $circleName = (string) ($circle?->name ?? '');
            $circleId = (string) ($r->circle_id ?? ($circle?->id ?? ''));

            $submitter = $r->submitter;
            $submitterName = $submitter ? trim(($submitter->first_name ?? '').' '.($submitter->last_name ?? '')) : '';
            if ($submitterName === '') {
                $submitterName = (string) ($submitter?->display_name ?? 'Leader');
            }

            $peersRoster = $this->resolvePeersRoster($r, $circleId);

            return [
                'id' => (string) $r->id,
                'circle_id' => $circleId,
                'circle_name' => $circleName,
                'report_type' => (string) $r->report_type,
                'period' => (string) $r->period,
                'submitted_by' => $submitterName,
                'submitter_role' => (string) ($r->submitter_role ?? ''),
                'submitted_at' => $r->created_at ? $r->created_at->toIso8601String() : '',
                'status' => (string) ($r->status ?: 'Under Review'),
                'attendance_percentage' => (float) ($r->attendance_percentage ?? 0.0),
                'deals_closed_value' => (string) ($r->deals_closed_value ?? '₹0'),
                'total_revenue' => (string) ($r->total_revenue ?? '₹0'),
                'summary_text' => (string) ($r->summary_text ?? $r->content ?? ''),
                'action_items' => (string) ($r->action_items ?? ''),
                'peers_roster' => $peersRoster,
            ];
        })->values()->all();
    }

    /**
     * Submit a new performance report with multi-tier routing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submitReport(array $data, string $userId, User|AdminUser|null $user = null): array
    {
        $requestedCircleId = isset($data['circle_id']) && Str::isUuid((string) $data['circle_id'])
            ? (string) $data['circle_id']
            : null;
        $scope = $this->dashboardService->resolveLeaderScope($requestedCircleId, null, $user);
        $circleId = $scope['resolved_circle_id'] ?? ($scope['target_circle_ids'][0] ?? null);
        $circle = $circleId ? Circle::query()->where('id', $circleId)->whereNull('deleted_at')->first() : null;

        if (! $circle) {
            throw ValidationException::withMessages([
                'circle_id' => ['A valid authorized circle_id is required to submit a report.'],
            ]);
        }

        $districtId = $circle?->district_id ?? null;
        $industryId = $circle?->circle_category_id ?? null;

        $roleKey = $user ? $this->permissionService->resolveUserRole($user)['role'] : 'chairBusinessGrowth';
        $roleTitleMap = [
            'superAdmin' => 'Super Admin',
            'countryDirector' => 'Country Director',
            'districtExecDirector' => 'District Exec Director',
            'industryDirector' => 'Industry Director',
            'circleFounder' => 'Circle Founder',
            'circleDirector' => 'Circle Director',
            'chairBusinessGrowth' => 'Chair - Business Growth Committee',
            'chairMembership' => 'Chair - Membership Committee',
            'chairEventsPrograms' => 'Chair - Events & Programs Committee',
            'circleChair' => 'Chair - Business Growth Committee',
        ];
        $submitterRole = $roleTitleMap[$roleKey] ?? 'Chair - Business Growth Committee';

        $visibleToRoles = match ($roleKey) {
            'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms', 'circleChair' => ['circleFounder', 'circleDirector', 'industryDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'],
            'circleFounder', 'circleDirector' => ['industryDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'],
            'industryDirector' => ['districtExecDirector', 'countryDirector', 'superAdmin'],
            'districtExecDirector' => ['countryDirector', 'superAdmin'],
            'countryDirector' => ['superAdmin'],
            default => ['superAdmin'],
        };

        $peersRoster = ! empty($data['peers_roster'])
            ? $data['peers_roster']
            : $this->buildPeersRosterForCircle((string) $circleId);

        $includedSections = $data['included_sections'] ?? [
            'attendance',
            'financials',
            'peer_roster',
            'p2p_meetings',
            'action_items',
        ];

        $report = LeaderReport::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleId,
            'district_id' => $districtId,
            'industry_id' => $industryId,
            'submitted_by_user_id' => $userId,
            'submitter_role' => $submitterRole,
            'report_type' => $data['report_type'] ?? 'Monthly',
            'period' => $data['period'] ?? now()->format('F Y'),
            'attendance_percentage' => isset($data['attendance_percentage']) ? (float) $data['attendance_percentage'] : 0.0,
            'deals_closed_value' => isset($data['deals_closed_value']) ? (string) $data['deals_closed_value'] : null,
            'total_revenue' => isset($data['total_revenue']) ? (string) $data['total_revenue'] : null,
            'content' => $data['content'] ?? null,
            'summary_text' => $data['summary_text'] ?? (isset($data['content']) ? Str::limit((string) $data['content'], 120) : null),
            'highlights' => $data['highlights'] ?? null,
            'challenges_faced' => $data['challenges_faced'] ?? null,
            'action_items' => $data['action_items'] ?? null,
            'included_sections' => $includedSections,
            'peers_roster' => $peersRoster,
            'status' => 'Under Review',
        ]);

        return [
            'report_id' => (string) $report->id,
            'status' => 'Under Review',
            'visible_to_roles' => $visibleToRoles,
        ];
    }

    /**
     * Get full report details with peer roster.
     *
     * @return array<string, mixed>
     */
    public function getReportDetails(string $reportId, User|AdminUser|null $user = null): array
    {
        $report = null;
        if (Str::isUuid($reportId)) {
            try {
                $report = LeaderReport::query()->with(['circle.district', 'submitter'])->where('id', $reportId)->first();
            } catch (\Throwable) {
                $report = null;
            }
        }

        if (! $report) {
            abort(404, 'Report not found.');
        }

        if (! $user) {
            abort(401, 'Authentication is required to access this report.');
        }

        // Leaders may only open reports that belong to circles inside their role scope.
        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);
        if ($scopedCircleIds !== null && ! in_array((string) $report->circle_id, array_map('strval', $scopedCircleIds), true)) {
            abort(403, 'This report does not belong to your circles.');
        }
        $circle = $report->circle;
        $circleId = (string) ($report->circle_id ?? ($circle?->id ?? ''));
        $circleName = (string) ($circle?->name ?? '');
        $district = $circle?->district;
        $districtId = (string) ($report->district_id ?? ($district?->id ?? ''));
        $districtName = (string) ($district?->name ?? '');
        $industryName = (string) ($circle?->category ?? '');

        $submitter = $report->submitter;
        $submitterName = $submitter ? trim(($submitter->first_name ?? '').' '.($submitter->last_name ?? '')) : '';
        if ($submitterName === '') {
            $submitterName = (string) ($submitter?->display_name ?? 'Leader');
        }

        $peersRoster = $this->resolvePeersRoster($report, $circleId);

        return [
            'id' => (string) $report->id,
            'circle_id' => $circleId,
            'circle_name' => $circleName,
            'district_id' => $districtId,
            'district_name' => $districtName,
            'industry_name' => $industryName,
            'report_type' => (string) $report->report_type,
            'period' => (string) $report->period,
            'submitted_by' => $submitterName,
            'submitter_role' => (string) ($report->submitter_role ?? ''),
            'submitted_at' => $report->created_at ? $report->created_at->toIso8601String() : '',
            'status' => (string) ($report->status ?: 'Under Review'),
            'attendance_percentage' => (float) ($report->attendance_percentage ?? 0.0),
            'deals_closed_value' => (string) ($report->deals_closed_value ?? '₹0'),
            'total_revenue' => (string) ($report->total_revenue ?? '₹0'),
            'summary_text' => (string) ($report->summary_text ?? $report->content ?? ''),
            'highlights' => (string) ($report->highlights ?? ''),
            'challenges_faced' => (string) ($report->challenges_faced ?? ''),
            'action_items' => (string) ($report->action_items ?? ''),
            'peers_roster' => $peersRoster,
        ];
    }

    /**
     * Resolve peers roster for a report, building it from circle members if empty.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolvePeersRoster(LeaderReport $report, string $circleId): array
    {
        if (is_array($report->peers_roster) && ! empty($report->peers_roster)) {
            return $report->peers_roster;
        }

        return $this->buildPeersRosterForCircle($circleId);
    }

    /**
     * Build dynamic peers roster with start/renewal dates for a circle.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildPeersRosterForCircle(string $circleId): array
    {
        if (! Str::isUuid($circleId)) {
            return [];
        }

        $membersQuery = CircleMember::query()
            ->where('circle_id', $circleId)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('circle_members', 'left_at')) {
            $membersQuery->whereNull('left_at');
        }
        if (Schema::hasColumn('circle_members', 'status')) {
            $membersQuery->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhereIn(DB::raw('LOWER(status::text)'), ['approved', 'active']);
            });
        }

        $members = $membersQuery->with(['user'])->get();

        if ($members->isEmpty()) {
            return [];
        }

        $membershipRecords = CirclePeerMembership::query()
            ->where('circle_id', $circleId)
            ->get()
            ->keyBy('user_id');

        return $members->map(function (CircleMember $cm) use ($membershipRecords): array {
            $user = $cm->user;
            $userId = (string) ($user?->id ?? $cm->user_id);
            $membership = $membershipRecords->get($userId);

            $userName = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '';
            if ($userName === '') {
                $userName = (string) ($user?->display_name ?? 'Peer Member');
            }

            $platformStart = $membership?->platform_membership_start
                ? $membership->platform_membership_start->format('Y-m-d')
                : ($user?->circle_joined_at ? $user->circle_joined_at->format('Y-m-d') : '');

            $platformEnd = $membership?->platform_membership_end
                ? $membership->platform_membership_end->format('Y-m-d')
                : '';

            $circleJoining = $membership?->circle_joining_date
                ? $membership->circle_joining_date->format('Y-m-d')
                : ($cm->joined_at ? $cm->joined_at->format('Y-m-d') : '');

            $circleRenewal = $membership?->circle_renewal_date
                ? $membership->circle_renewal_date->format('Y-m-d')
                : '';

            $attendanceRate = (float) ($user?->attendance_rate ?? 0);
            $dealsClosedFormatted = $user?->deals_closed_formatted ?? '₹0';
            $p2pCount = (int) ($user?->p2p_meetings_count ?? 0);
            $referralsCount = (int) ($user?->referrals_given_count ?? 0);

            return [
                'peer_id' => $userId,
                'name' => $userName,
                'avatar_url' => $user?->avatar_url ?? $user?->profile_photo_url,
                'company' => (string) ($user?->company_name ?? $user?->business_name ?? ''),
                'designation' => (string) ($user?->designation ?? 'Member'),
                'status' => ucfirst((string) ($user?->status ?? 'Active')),
                'platform_membership_start' => $platformStart,
                'platform_membership_end' => $platformEnd,
                'circle_joining_date' => $circleJoining,
                'circle_renewal_date' => $circleRenewal,
                'attendance' => "{$attendanceRate}%",
                'deals_closed' => $dealsClosedFormatted,
                'p2p_count' => $p2pCount,
                'referrals_count' => $referralsCount,
            ];
        })->values()->all();
    }

    /**
     * Get 6-month attendance trend points.
     *
     * @return array<int, array{month: string, value: float}>
     */
    public function getAttendanceTrend(?string $circleId = null): array
    {
        // No demo data: the trend is empty until real attendance history is available.
        return [];
    }

    /**
     * Get download URL and metadata for a report.
     *
     * @return array<string, mixed>
     */
    public function getDownloadUrl(string $reportId, User|AdminUser|null $user = null): array
    {
        $details = $this->getReportDetails($reportId, $user);
        $type = (string) ($details['report_type'] ?? 'Performance');
        $period = (string) ($details['period'] ?? '2026');
        $fileName = "Report-{$type}-{$period}.pdf";

        return [
            'report_id' => $reportId,
            'file_name' => $fileName,
            'file_format' => 'PDF',
            'file_size' => '2.4 MB',
            'download_url' => url("api/v1/leader/reports/{$reportId}/file"),
            'expires_in_seconds' => 3600,
        ];
    }

    /**
     * Render the Leader Performance Report as a PDF binary string.
     */
    public function renderReportPdf(string $reportId, User|AdminUser|null $user = null): string
    {
        $details = $this->getReportDetails($reportId, $user);
        $type = (string) ($details['report_type'] ?? 'Performance');
        $period = (string) ($details['period'] ?? '2026');
        $fileName = "Report-{$type}-{$period}.pdf";
        $details['file_name'] = $fileName;

        if (class_exists(Dompdf::class) && class_exists(Options::class)) {
            try {
                $html = view('reports.performance-report-pdf', [
                    'report' => $details,
                ])->render();

                $options = new Options;
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);
                $options->set('defaultFont', 'Helvetica');

                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return (string) $dompdf->output();
            } catch (\Throwable $e) {
                Log::warning('Dompdf rendering failed, falling back to LeaderReportPdfGenerator: '.$e->getMessage());
            }
        }

        $generator = new LeaderReportPdfGenerator;

        return $generator->generate($details);
    }

    /**
     * Generate an HTTP response to stream or download the Leader Performance Report PDF.
     */
    public function downloadReportPdf(string $reportId, ?Request $request = null): Response
    {
        $user = $request?->user() ?? auth('sanctum')->user();
        $details = $this->getReportDetails($reportId, $user);
        $type = (string) ($details['report_type'] ?? 'Performance');
        $period = (string) ($details['period'] ?? '2026');
        $fileName = "Report-{$type}-{$period}.pdf";

        $pdfContent = $this->renderReportPdf($reportId, $user);
        $disposition = ($request && $request->boolean('download')) ? 'attachment' : 'inline';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
            'Content-Length' => (string) strlen($pdfContent),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
