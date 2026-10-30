<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Leader\Models\CirclePeerMembership;
use App\Leader\Models\LeaderReport;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        ?User $user = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user, $districtId);

        $query = LeaderReport::query()
            ->with(['circle.district', 'submitter'])
            ->when($circleId, fn (Builder $q) => $q->where('circle_id', $circleId))
            ->when(! $circleId && $scopedCircleIds !== null, fn (Builder $q) => $q->whereIn('circle_id', $scopedCircleIds))
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
    public function submitReport(array $data, string $userId, ?User $user = null): array
    {
        $circleId = $data['circle_id'] ?? null;
        $circle = null;

        if ($circleId && Str::isUuid($circleId)) {
            $circle = Circle::query()->where('id', $circleId)->first();
        }

        if (! $circle) {
            $circle = Circle::query()->whereNull('deleted_at')->first();
            $circleId = $circle ? (string) $circle->id : (string) Str::uuid();
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
            'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms', 'circleChair' => ['circleFounder', 'circleDirector', 'industryDirector', 'districtExecDirector', 'superAdmin'],
            'circleFounder', 'circleDirector' => ['industryDirector', 'districtExecDirector', 'superAdmin'],
            'industryDirector' => ['districtExecDirector', 'superAdmin'],
            'districtExecDirector' => ['superAdmin'],
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
            'attendance_percentage' => (float) ($data['attendance_percentage'] ?? 94.5),
            'deals_closed_value' => (string) ($data['deals_closed_value'] ?? '₹18.5L'),
            'total_revenue' => (string) ($data['total_revenue'] ?? '₹24.0L'),
            'content' => $data['content'] ?? null,
            'summary_text' => $data['summary_text'] ?? Str::limit((string) ($data['content'] ?? 'Strong monthly participation with 4 new peer referrals closed.'), 120),
            'highlights' => $data['highlights'] ?? 'Launched 2 new FinTech partnerships and added 3 verified peers.',
            'challenges_faced' => $data['challenges_faced'] ?? 'Need faster turnaround on peer onboarding verification.',
            'action_items' => $data['action_items'] ?? 'Follow up with 3 pending members for fee renewal and schedule Q4 assemblies.',
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
    public function getReportDetails(string $reportId, ?User $user = null): array
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

        // Leaders may only open reports that belong to circles inside their role scope.
        $scopedCircleIds = $this->peersService->resolveScopedCircleIds($user);
        if ($user && $scopedCircleIds !== null && ! in_array((string) $report->circle_id, array_map('strval', $scopedCircleIds), true)) {
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

        $members = CircleMember::query()
            ->where('circle_id', $circleId)
            ->whereNull('deleted_at')
            ->with(['user'])
            ->take(20)
            ->get();

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
    public function getDownloadUrl(string $reportId): array
    {
        $report = null;
        if (Str::isUuid($reportId)) {
            try {
                $report = LeaderReport::query()->where('id', $reportId)->first();
            } catch (\Throwable) {
                $report = null;
            }
        }

        $type = $report ? (string) $report->report_type : 'Performance';
        $period = $report ? (string) $report->period : '2026';
        $fileName = "Report-{$type}-{$period}.pdf";

        return [
            'report_id' => $reportId,
            'file_name' => $fileName,
            'file_format' => 'PDF',
            'file_size' => '2.4 MB',
            'download_url' => url("api/v1/files/{$reportId}/download?type=pdf"),
            'expires_in_seconds' => 3600,
        ];
    }

    /**
     * Render the Leader Performance Report as a PDF binary string.
     */
    public function renderReportPdf(string $reportId): string
    {
        $details = $this->getReportDetails($reportId);
        $type = (string) ($details['report_type'] ?? 'Performance');
        $period = (string) ($details['period'] ?? '2026');
        $fileName = "Report-{$type}-{$period}.pdf";
        $details['file_name'] = $fileName;

        if (class_exists(\Dompdf\Dompdf::class) && class_exists(\Dompdf\Options::class)) {
            try {
                $html = view('reports.performance-report-pdf', [
                    'report' => $details,
                ])->render();

                $options = new \Dompdf\Options;
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);
                $options->set('defaultFont', 'Helvetica');

                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return (string) $dompdf->output();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Dompdf rendering failed, falling back to LeaderReportPdfGenerator: '.$e->getMessage());
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
        $details = $this->getReportDetails($reportId);
        $type = (string) ($details['report_type'] ?? 'Performance');
        $period = (string) ($details['period'] ?? '2026');
        $fileName = "Report-{$type}-{$period}.pdf";

        $pdfContent = $this->renderReportPdf($reportId);
        $disposition = ($request && $request->boolean('download')) ? 'attachment' : 'inline';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$fileName}\"",
            'Content-Length' => (string) strlen($pdfContent),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
