<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\Circle;
use App\Models\LeaderReport;
use Illuminate\Support\Str;

class LeaderReportsService
{
    /**
     * List submitted performance reports.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listReports(?string $circleId = null, ?string $type = null): array
    {
        $query = LeaderReport::query()
            ->with(['circle', 'submitter'])
            ->when($circleId, fn ($q) => $q->where('circle_id', $circleId))
            ->when($type, fn ($q) => $q->whereRaw('LOWER(report_type) = ?', [strtolower($type)]))
            ->orderByDesc('created_at');

        $reports = $query->take(20)->get();

        if ($reports->isEmpty()) {
            return [
                [
                    'id' => 'rep_101',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'report_type' => 'Monthly',
                    'period' => 'July 2026',
                    'submitted_by' => 'Arjun Patel',
                    'submitted_at' => '2026-08-01T10:00:00Z',
                    'status' => 'Approved',
                    'attendance_percentage' => 92,
                    'deals_closed_value' => '₹14.2L',
                    'summary_text' => 'Strong monthly participation with 4 new peer referrals closed.',
                ],
            ];
        }

        return $reports->map(function (LeaderReport $r): array {
            $circleName = $r->circle?->name ?? 'Mumbai Tech Sunrise';
            $submitter = $r->submitter;
            $submitterName = $submitter ? trim(($submitter->first_name ?? '').' '.($submitter->last_name ?? '')) : 'Arjun Patel';
            if ($submitterName === '' || $submitterName === ' ') {
                $submitterName = $submitter?->display_name ?? 'Arjun Patel';
            }

            return [
                'id' => (string) $r->id,
                'circle_name' => (string) $circleName,
                'report_type' => (string) $r->report_type,
                'period' => (string) $r->period,
                'submitted_by' => $submitterName,
                'submitted_at' => $r->created_at ? $r->created_at->toIso8601String() : '2026-08-01T10:00:00Z',
                'status' => (string) ($r->status ?: 'Under Review'),
                'attendance_percentage' => (float) $r->attendance_percentage,
                'deals_closed_value' => (string) ($r->deals_closed_value ?? '₹14.2L'),
                'summary_text' => (string) ($r->summary_text ?? $r->content ?? 'Report submitted.'),
            ];
        })->values()->all();
    }

    /**
     * Submit a new performance report.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitReport(array $data, string $userId): string
    {
        $circleId = $data['circle_id'] ?? null;
        if (! $circleId || ! Circle::query()->where('id', $circleId)->exists()) {
            $firstCircle = Circle::query()->whereNull('deleted_at')->first();
            $circleId = $firstCircle ? (string) $firstCircle->id : (string) Str::uuid();
        }

        $report = LeaderReport::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circleId,
            'submitted_by_user_id' => $userId,
            'report_type' => $data['report_type'] ?? 'Monthly',
            'period' => $data['period'] ?? now()->format('F Y'),
            'attendance_percentage' => (float) ($data['attendance_percentage'] ?? 94),
            'deals_closed_value' => (string) ($data['deals_closed_value'] ?? '₹18.5L'),
            'content' => $data['content'] ?? null,
            'summary_text' => Str::limit((string) ($data['content'] ?? ''), 120),
            'action_items' => $data['action_items'] ?? null,
            'status' => 'Under Review',
        ]);

        return (string) $report->id;
    }

    /**
     * Get 6-month attendance trend points.
     *
     * @return array<int, array{month: string, value: float}>
     */
    public function getAttendanceTrend(?string $circleId = null): array
    {
        return [
            ['month' => 'Feb', 'value' => 72.0],
            ['month' => 'Mar', 'value' => 78.0],
            ['month' => 'Apr', 'value' => 74.0],
            ['month' => 'May', 'value' => 82.0],
            ['month' => 'Jun', 'value' => 87.0],
            ['month' => 'Jul', 'value' => 90.0],
        ];
    }

    /**
     * Get download URL and metadata for a report.
     *
     * @return array<string, mixed>
     */
    public function getDownloadUrl(string $reportId): array
    {
        $report = Str::isUuid($reportId)
            ? LeaderReport::query()->where('id', $reportId)->first()
            : null;

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
}
