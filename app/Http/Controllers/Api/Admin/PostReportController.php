<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PostReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostReportController extends BaseApiController
{
    /**
     * Display a listing of post reports with multi-criteria filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PostReport::query()
            ->with([
                'post' => function ($q) {
                    $q->withTrashed()->with([
                        'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                        'circle:id,name',
                    ]);
                },
                'reporter:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'reasonOption:id,title',
                'reviewer:id,name,email',
            ]);

        // Status filter (open, reviewed, resolved, dismissed)
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                if ($status === 'open') {
                    $query->where(function ($q) {
                        $q->where('status', 'open')->orWhereNull('status');
                    });
                } else {
                    $query->where('status', $status);
                }
            }
        }

        // Reason filter (spam, abuse, harassment, fake, etc.)
        if ($reason = $request->query('reason')) {
            if ($reason !== 'all') {
                $query->where('reason', $reason);
            }
        }

        // Date range filters
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Debounced search query
        if ($search = $request->query('search')) {
            $search = trim((string) $search);
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'ILIKE', "%{$search}%")
                    ->orWhere('admin_note', 'ILIKE', "%{$search}%")
                    ->orWhereHas('reporter', function ($rq) use ($search) {
                        $rq->where('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%");
                    })
                    ->orWhereHas('post', function ($pq) use ($search) {
                        $pq->where('content_text', 'ILIKE', "%{$search}%")
                            ->orWhere('title', 'ILIKE', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('display_name', 'ILIKE', "%{$search}%")
                                    ->orWhere('first_name', 'ILIKE', "%{$search}%")
                                    ->orWhere('last_name', 'ILIKE', "%{$search}%");
                            });
                    });
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Aggregated statistics for post reports queue.
     */
    public function stats(Request $request): JsonResponse
    {
        $total = PostReport::count();
        $open = PostReport::where('status', 'open')->orWhereNull('status')->count();
        $reviewed = PostReport::where('status', 'reviewed')->count();
        $resolved = PostReport::where('status', 'resolved')->count();
        $dismissed = PostReport::where('status', 'dismissed')->count();

        // Breakdown by reason
        $byReason = PostReport::select('reason', DB::raw('count(*) as count'))
            ->whereNotNull('reason')
            ->groupBy('reason')
            ->pluck('count', 'reason')
            ->toArray();

        return $this->success([
            'total' => $total,
            'open' => $open,
            'reviewed' => $reviewed,
            'resolved' => $resolved,
            'dismissed' => $dismissed,
            'by_reason' => $byReason,
        ]);
    }

    /**
     * Show single report details including other reports on the same post.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $report = PostReport::with([
            'post' => function ($q) {
                $q->withTrashed()->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                    'circle:id,name',
                ]);
            },
            'reporter:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
            'reasonOption:id,title',
            'reviewer:id,name,email',
        ])->find($id);

        if (! $report) {
            return $this->error('Post report record not found', 404);
        }

        // Get sibling reports on this post
        $otherReports = PostReport::where('post_id', $report->post_id)
            ->where('id', '!=', $report->id)
            ->with(['reporter:id,display_name,first_name,last_name'])
            ->latest('created_at')
            ->get();

        return $this->success([
            'report' => $report,
            'other_reports' => $otherReports,
            'other_reports_count' => $otherReports->count(),
        ]);
    }

    /**
     * Resolve a post report with optional action on the reported post.
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'take_down_post' => ['nullable', 'boolean'],
        ]);

        $report = PostReport::find($id);
        if (! $report) {
            return $this->error('Post report record not found', 404);
        }

        $admin = $request->user();

        DB::transaction(function () use ($report, $validated, $admin) {
            $report->status = 'resolved';
            $report->admin_note = $validated['admin_note'] ?? $report->admin_note;
            $report->reviewed_by_admin_user_id = $admin?->id;
            $report->reviewed_at = now();
            $report->save();

            // If admin opted to take down the offending post
            if (! empty($validated['take_down_post']) && $report->post) {
                $post = $report->post;
                $post->moderation_status = 'rejected';
                $post->active = false;
                $post->is_deleted = true;
                $post->save();
                $post->delete();
            }
        });

        $report->load(['post.user', 'reporter', 'reviewer']);

        return $this->success($report, 'Report resolved successfully.');
    }

    /**
     * Dismiss a post report.
     */
    public function dismiss(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = PostReport::find($id);
        if (! $report) {
            return $this->error('Post report record not found', 404);
        }

        $admin = $request->user();

        DB::transaction(function () use ($report, $validated, $admin) {
            $report->status = 'dismissed';
            $report->admin_note = $validated['admin_note'] ?? $report->admin_note;
            $report->reviewed_by_admin_user_id = $admin?->id;
            $report->reviewed_at = now();
            $report->save();
        });

        $report->load(['post.user', 'reporter', 'reviewer']);

        return $this->success($report, 'Report dismissed.');
    }
}
