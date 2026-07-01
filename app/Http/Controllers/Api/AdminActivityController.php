<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Activity\UpdateActivityAdminRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminActivityController extends BaseApiController
{
    public function index(Request $request)
    {
        // TODO: enforce admin authorization here

        $status = $request->input('status', 'pending');

        $query = Activity::with(['user', 'circle', 'event'])
            ->where('status', $status);

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = [
            'items' => ActivityResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function show(Activity $activity)
    {
        $activity->load(['user', 'circle', 'event']);

        return $this->success(new ActivityResource($activity));
    }

    public function updateStatus(UpdateActivityAdminRequest $request, string $id)
    {
        $admin = $request->user();
        // TODO: enforce admin authorization here

        $data = $request->validated();

        $activity = Activity::find($id);

        if (! $activity) {
            return $this->error('Activity not found', 404);
        }

        $result = $this->applyStatusChange(
            $activity,
            $admin,
            $data['status'],
            $data['coins_awarded'] ?? null,
            $data['admin_notes'] ?? null,
            $data['reference'] ?? null
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->success(new ActivityResource($result), 'Activity updated');
    }

    public function approve(Activity $activity, Request $request)
    {
        $admin = $request->user();

        $result = $this->applyStatusChange(
            $activity,
            $admin,
            'approved',
            $request->input('coins_awarded'),
            $request->input('admin_notes'),
            $request->input('reference')
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->success(new ActivityResource($result), 'Activity approved');
    }

    public function reject(Activity $activity, Request $request)
    {
        $admin = $request->user();

        $result = $this->applyStatusChange(
            $activity,
            $admin,
            'rejected',
            0,
            $request->input('admin_notes'),
            $request->input('reference')
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->success(new ActivityResource($result), 'Activity rejected');
    }

    private function applyStatusChange(
        Activity $activity,
        $admin,
        string $toStatus,
        ?int $coinsAwarded = null,
        ?string $adminNotes = null,
        ?string $reference = null
    ) {
        try {
            DB::beginTransaction();

            $lockedActivity = Activity::where('id', $activity->id)->lockForUpdate()->first();

            if (! $lockedActivity) {
                DB::rollBack();

                return $this->error('Activity not found', 404);
            }

            if ($lockedActivity->status !== 'pending') {
                DB::rollBack();

                return $this->error('Only pending activities can be updated', 422);
            }

            $fromStatus = $lockedActivity->status;
            $reference = $reference ?? ('activity_'.$lockedActivity->type);

            $lockedActivity->status = $toStatus;
            $lockedActivity->admin_notes = $adminNotes;
            $lockedActivity->requires_verification = false;
            $lockedActivity->verified_by_admin_id = $admin->id;
            $lockedActivity->verified_at = now();

            if ($toStatus === 'approved') {
                $coinsAwarded = (int) ($coinsAwarded ?? 0);

                if ($coinsAwarded <= 0) {
                    DB::rollBack();

                    return $this->error('coins_awarded must be greater than zero when approving', 422);
                }

                $lockedActivity->coins_awarded = $coinsAwarded;

                $txRow = DB::selectOne(
                    'SELECT coins_apply_transaction(?, ?, ?, ?, ?) AS transaction_id',
                    [
                        $lockedActivity->user_id,
                        $lockedActivity->coins_awarded,
                        $lockedActivity->id,
                        $reference,
                        $admin->id,
                    ]
                );

                $lockedActivity->coins_ledger_id = $txRow->transaction_id ?? null;
            } else {
                $lockedActivity->coins_awarded = 0;
                $lockedActivity->coins_ledger_id = null;
            }

            $lockedActivity->save();

            ActivityAudit::create([
                'activity_id' => $lockedActivity->id,
                'changed_by' => $admin->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'change_reason' => $adminNotes ?: $reference,
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return $this->error('Failed to update activity: '.$e->getMessage(), 500);
        }

        $lockedActivity->load(['user', 'circle', 'event']);

        return $lockedActivity;
    }
}
