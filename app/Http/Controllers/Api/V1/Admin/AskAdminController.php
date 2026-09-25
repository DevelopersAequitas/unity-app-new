<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ask\AskResource;
use App\Models\Ask\Ask;
use App\Models\Ask\AskStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AskAdminController extends Controller
{
    /**
     * Admin API: List Asks with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $search = trim((string) $request->query('search', ''));
        $flow = trim((string) $request->query('flow', ''));
        $status = trim((string) $request->query('status', ''));

        $asks = Ask::query()
            ->with(['flow', 'type', 'user.city'])
            ->withCount(['matches', 'responses'])
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search): void {
                        $uq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('display_name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            })
            ->when($flow !== '', function (Builder $q) use ($flow): void {
                $q->whereHas('flow', fn (Builder $fq) => $fq->where('code', $flow)->orWhere('id', $flow));
            })
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AskResource::collection($asks->items()),
            'meta' => [
                'current_page' => $asks->currentPage(),
                'per_page' => $asks->perPage(),
                'total' => $asks->total(),
                'last_page' => $asks->lastPage(),
            ],
        ]);
    }

    /**
     * Admin API: Show full details of an Ask.
     */
    public function show(Ask $ask): JsonResponse
    {
        $ask->loadMissing([
            'flow',
            'type',
            'user.city',
            'answers.option',
            'answers.optionGroup',
            'matches.matchedUser.city',
            'responses.responder.city',
            'responses.contact',
            'statusHistories.changedBy',
        ])->loadCount(['matches', 'responses']);

        return response()->json([
            'success' => true,
            'data' => new AskResource($ask),
        ]);
    }

    /**
     * Admin API: Update Ask status.
     */
    public function updateStatus(Request $request, Ask $ask): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,closed,cancelled,expired'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $ask->status;
        $newStatus = (string) $validated['status'];
        $reason = ! empty($validated['reason']) ? (string) $validated['reason'] : 'Updated by Admin via API';

        $updates = ['status' => $newStatus];
        if ($newStatus === Ask::STATUS_CLOSED) {
            $updates['closed_at'] = now();
        }

        $ask->update($updates);

        /** @var User|null $adminUser */
        $adminUser = $request->user();
        $adminUserId = $adminUser ? (string) $adminUser->id : $ask->user_id;

        AskStatusHistory::create([
            'ask_id' => $ask->id,
            'changed_by_user_id' => $adminUserId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ask status updated to {$newStatus} successfully.",
            'data' => new AskResource($ask->fresh(['flow', 'type', 'answers.option'])),
        ]);
    }

    /**
     * Admin API: Soft delete an Ask.
     */
    public function destroy(Ask $ask): JsonResponse
    {
        $ask->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ask deleted successfully.',
        ]);
    }

    /**
     * Admin API: Summary statistics for dashboards.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'total_asks' => Ask::count(),
                'published_asks' => Ask::where('status', Ask::STATUS_PUBLISHED)->count(),
                'closed_asks' => Ask::where('status', Ask::STATUS_CLOSED)->count(),
                'draft_asks' => Ask::where('status', Ask::STATUS_DRAFT)->count(),
                'total_matches' => DB::table('ask_matches')->count(),
                'total_responses' => DB::table('ask_responses')->count(),
                'by_flow' => [
                    'collaboration' => Ask::whereHas('flow', fn ($q) => $q->where('code', 'collaboration'))->count(),
                    'referral' => Ask::whereHas('flow', fn ($q) => $q->where('code', 'referral'))->count(),
                    'help' => Ask::whereHas('flow', fn ($q) => $q->where('code', 'help'))->count(),
                ],
            ],
        ]);
    }
}
