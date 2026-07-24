<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntroductionRequest;
use App\Models\User;
use App\Services\Users\IntroducedPeerService;
use App\Services\Users\UserMilestoneSyncService;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IntroductionRequestsController extends Controller
{
    public function __construct(
        private readonly IntroducedPeerService $introducedPeerService,
    ) {}

    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin !== null && AdminAccess::isGlobalAdmin($admin), 403);

        $query = IntroductionRequest::query()
            ->with(['requester', 'introducer', 'reviewer'])
            ->where('status', 'pending')
            ->latest('created_at');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->whereHas('requester', fn ($r) => $r->where('display_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like))
                    ->orWhereHas('introducer', fn ($r) => $r->where('display_name', 'ILIKE', $like)
                        ->orWhere('email', 'ILIKE', $like)
                        ->orWhere('company_name', 'ILIKE', $like));
            });
        }

        $introductionRequests = $query->paginate(25)->appends($request->query());

        return view('admin.introduction_requests.index', [
            'introductionRequests' => $introductionRequests,
            'filters' => $request->only(['search']),
        ]);
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin !== null && AdminAccess::isGlobalAdmin($admin), 403);

        try {
            DB::transaction(function () use ($id, $admin): void {
                /** @var IntroductionRequest $introRequest */
                $introRequest = IntroductionRequest::query()->lockForUpdate()->findOrFail($id);

                if ($introRequest->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'status' => ['This request has already been processed.'],
                    ]);
                }

                /** @var User $requester */
                $requester = User::query()->lockForUpdate()->findOrFail($introRequest->requester_id);

                /** @var User $introducer */
                $introducer = User::query()->lockForUpdate()->findOrFail($introRequest->introducer_id);

                // Requester must not already have an introducer (re-validate inside transaction)
                if ($requester->introduced_by !== null) {
                    throw ValidationException::withMessages([
                        'requester' => ['This member already has an introducer assigned.'],
                    ]);
                }

                // Circular relationship check inside transaction
                if ($this->wouldCreateCircularRelationship($requester->id, $introducer->id)) {
                    throw ValidationException::withMessages([
                        'introducer_id' => ['Approving this request would create a circular introduction relationship.'],
                    ]);
                }

                // Assign introducer
                $requester->introduced_by = $introducer->id;
                $requester->save();

                // Recalculate members_introduced_count from actual DB count
                $count = User::where('introduced_by', $introducer->id)->count();
                $introducer->members_introduced_count = $count;
                $introducer->save();

                // Mark request as approved
                $introRequest->status = 'approved';
                $introRequest->reviewed_by = $admin->id;
                $introRequest->reviewed_at = now();
                $introRequest->save();

                // Sync milestones for the introducer
                $this->introducedPeerService->getIntroducedPeers($introducer); // warm relation
                app(UserMilestoneSyncService::class)->sync($introducer);

                Log::info('introduction_request.approved', [
                    'request_id' => $introRequest->id,
                    'requester_id' => $requester->id,
                    'introducer_id' => $introducer->id,
                    'admin_id' => $admin->id,
                ]);
            });

            return back()->with('success', 'Introduction request approved successfully.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin !== null && AdminAccess::isGlobalAdmin($admin), 403);

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($id, $admin, $request): void {
                /** @var IntroductionRequest $introRequest */
                $introRequest = IntroductionRequest::query()->lockForUpdate()->findOrFail($id);

                if ($introRequest->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'status' => ['This request has already been processed.'],
                    ]);
                }

                $introRequest->status = 'rejected';
                $introRequest->admin_note = $request->input('admin_note');
                $introRequest->reviewed_by = $admin->id;
                $introRequest->reviewed_at = now();
                $introRequest->save();

                Log::info('introduction_request.rejected', [
                    'request_id' => $introRequest->id,
                    'admin_id' => $admin->id,
                ]);
            });

            return back()->with('success', 'Introduction request rejected.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Walk up the introducer chain to detect circular relationships.
     */
    private function wouldCreateCircularRelationship(string $requesterId, string $introducerId): bool
    {
        $visited = [];
        $currentId = $introducerId;

        while ($currentId !== null) {
            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;

            $current = User::withoutTrashed()->select('id', 'introduced_by')->find($currentId);

            if (! $current) {
                break;
            }

            $parentId = $current->introduced_by;

            if ($parentId === null) {
                break;
            }

            if ($parentId === $requesterId) {
                return true;
            }

            $currentId = $parentId;
        }

        return false;
    }
}
