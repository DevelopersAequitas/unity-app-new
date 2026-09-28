<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ask\Ask;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskStatusHistory;
use App\Models\Ask\AskType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AskManagementController extends Controller
{
    /**
     * Display a listing of Asks.
     */
    public function index(Request $request): View
    {
        $rowsPerPage = (int) $request->query('per_page', 20);
        if (! in_array($rowsPerPage, [10, 20, 50, 100], true)) {
            $rowsPerPage = 20;
        }

        $search = trim((string) $request->query('search', ''));
        $flowFilter = trim((string) $request->query('flow', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = Ask::query()
            ->with(['flow', 'type', 'user.city'])
            ->withCount(['matches', 'responses'])
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sq) use ($search): void {
                    $sq->where('title', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function (Builder $uq) use ($search): void {
                            $uq->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%")
                                ->orWhere('display_name', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%")
                                ->orWhere('company_name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when($flowFilter !== '', function (Builder $q) use ($flowFilter): void {
                $q->whereHas('flow', fn (Builder $fq) => $fq->where('code', $flowFilter)->orWhere('id', $flowFilter));
            })
            ->when($statusFilter !== '', function (Builder $q) use ($statusFilter): void {
                $q->where('status', $statusFilter);
            })
            ->when($dateFrom !== '', function (Builder $q) use ($dateFrom): void {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function (Builder $q) use ($dateTo): void {
                $q->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('created_at');

        $asks = $query->paginate($rowsPerPage)->withQueryString();

        // Summary Stats
        $stats = [
            'total' => Ask::count(),
            'published' => Ask::where('status', Ask::STATUS_PUBLISHED)->count(),
            'closed' => Ask::where('status', Ask::STATUS_CLOSED)->count(),
            'draft' => Ask::where('status', Ask::STATUS_DRAFT)->count(),
            'total_matches' => DB::table('ask_matches')->count(),
            'total_responses' => DB::table('ask_responses')->count(),
        ];

        $flows = AskFlow::query()->where('is_active', true)->orderBy('sort_order')->get();
        $statuses = [Ask::STATUS_DRAFT, Ask::STATUS_PUBLISHED, Ask::STATUS_CLOSED, Ask::STATUS_CANCELLED, Ask::STATUS_EXPIRED];

        return view('admin.asks.index', [
            'asks' => $asks,
            'stats' => $stats,
            'flows' => $flows,
            'statuses' => $statuses,
            'filters' => [
                'search' => $search,
                'flow' => $flowFilter,
                'status' => $statusFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $rowsPerPage,
            ],
        ]);
    }

    /**
     * Display full details of an Ask.
     */
    public function show(Ask $ask): View
    {
        $ask->loadMissing([
            'flow',
            'type',
            'user.city',
            'user.level4Category',
            'district',
            'circle',
            'answers.option',
            'answers.optionGroup',
            'matches.matchedUser.city',
            'responses.responder.city',
            'responses.introducedUser',
            'responses.contact',
            'responses.statusHistories.changedBy',
            'statusHistories.changedBy',
            'timelineLink.post',
        ])->loadCount(['matches', 'responses']);

        return view('admin.asks.show', [
            'ask' => $ask,
        ]);
    }

    /**
     * Update the status of an Ask from the Admin Panel.
     */
    public function updateStatus(Request $request, Ask $ask): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,closed,cancelled,expired'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $ask->status;
        $newStatus = (string) $validated['status'];
        $reason = ! empty($validated['reason']) ? (string) $validated['reason'] : 'Status updated by Administrator';

        $updates = ['status' => $newStatus];
        if ($newStatus === Ask::STATUS_CLOSED) {
            $updates['closed_at'] = now();
        }

        $ask->update($updates);

        /** @var User|null $adminUser */
        $adminUser = Auth::guard('admin')->user();
        $adminUserId = $adminUser ? (string) $adminUser->id : $ask->user_id;

        AskStatusHistory::create([
            'ask_id' => $ask->id,
            'changed_by_user_id' => $adminUserId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        return redirect()->back()->with('success', "Ask status updated to {$newStatus} successfully.");
    }

    /**
     * Soft delete an Ask.
     */
    public function destroy(Ask $ask): RedirectResponse
    {
        $ask->delete();

        return redirect()->route('admin.asks.index')->with('success', 'Ask removed successfully.');
    }

    /**
     * Export Asks to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'asks_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function (): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Ask ID',
                'Title',
                'Flow',
                'Type',
                'Creator Name',
                'Creator Email',
                'Creator Company',
                'Creator City',
                'Visibility',
                'Status',
                'Matches Count',
                'Responses Count',
                'Published At',
                'Created At',
            ]);

            Ask::query()
                ->with(['flow', 'type', 'user.city'])
                ->withCount(['matches', 'responses'])
                ->orderByDesc('created_at')
                ->chunk(200, function ($chunk) use ($handle): void {
                    foreach ($chunk as $ask) {
                        $user = $ask->user;
                        $creatorName = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '—';
                        if ($creatorName === '' && $user) {
                            $creatorName = $user->display_name ?: $user->name;
                        }

                        fputcsv($handle, [
                            $ask->id,
                            $ask->title,
                            $ask->flow?->name ?? '—',
                            $ask->type?->name ?? '—',
                            $creatorName,
                            $user?->email ?? '—',
                            $user?->company_name ?? '—',
                            $user?->city?->name ?? (is_string($user?->city) ? $user->city : '—'),
                            $ask->visibility_type,
                            $ask->status,
                            $ask->matches_count,
                            $ask->responses_count,
                            $ask->published_at?->toDateTimeString() ?? '—',
                            $ask->created_at?->toDateTimeString() ?? '—',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * View Ask Configuration (Flows, Types, Option Groups, Options).
     */
    public function config(): View
    {
        $flows = AskFlow::query()
            ->with(['types' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $optionGroups = AskOptionGroup::query()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.asks.config', [
            'flows' => $flows,
            'optionGroups' => $optionGroups,
        ]);
    }

    /**
     * Toggle active status for Flow, Type, OptionGroup, or Option.
     */
    public function toggleConfigStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:flow,type,group,option'],
            'id' => ['required', 'string', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $model = match ($validated['type']) {
            'flow' => AskFlow::query()->findOrFail($validated['id']),
            'type' => AskType::query()->findOrFail($validated['id']),
            'group' => AskOptionGroup::query()->findOrFail($validated['id']),
            'option' => AskOption::query()->findOrFail($validated['id']),
        };

        $newStatus = isset($validated['is_active']) ? (bool) $validated['is_active'] : ! (bool) $model->is_active;
        $model->update(['is_active' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($validated['type']).' status updated successfully.',
            'data' => [
                'type' => $validated['type'],
                'id' => $model->id,
                'is_active' => (bool) $model->is_active,
            ],
        ]);
    }
}
