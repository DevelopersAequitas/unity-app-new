<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\LeaderInterestSubmission;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadershipController extends BaseApiController
{
    private const SUPPORTED_ROLES = ['ded','id','cf','cd','chair','vice_chair','secretary','powerhouse','advisor','honorary','industry_advisor','circle_advisor','circle_influencer'];

    public function __construct(private readonly AdminAuditService $audit)
    {
    }

    public function roles(): JsonResponse
    {
        return $this->success(self::SUPPORTED_ROLES);
    }

    public function applications(Request $request): JsonResponse
    {
        return $this->success(LeaderInterestSubmission::query()->latest('created_at')->paginate((int) $request->input('per_page', 20)));
    }

    public function applicationShow(string $id): JsonResponse
    {
        return $this->success(LeaderInterestSubmission::query()->findOrFail($id));
    }

    public function applicationApprove(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);
        $record = LeaderInterestSubmission::query()->findOrFail($id);
        $record->status = 'approved';
        $record->admin_remarks = $validated['remarks'] ?? null;
        $record->reviewed_by = $request->user()->id;
        $record->reviewed_at = now();
        $record->save();
        $this->audit->log($request->user(), 'admin.leadership.application.approve', 'leader_interest_submissions', (string) $record->id, [], $record->toArray(), $request);

        return $this->success($record);
    }

    public function applicationReject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $record = LeaderInterestSubmission::query()->findOrFail($id);
        $record->status = 'rejected';
        $record->admin_remarks = $validated['rejection_reason'];
        $record->reviewed_by = $request->user()->id;
        $record->reviewed_at = now();
        $record->save();

        return $this->success($record);
    }

    public function assignments(Request $request): JsonResponse
    {
        $query = DB::table('circle_members as cm')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->join('circles', 'circles.id', '=', 'cm.circle_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->selectRaw('cm.id, cm.user_id, cm.circle_id, cm.role::text as role, cm.status, cm.joined_at, users.display_name, circles.name as circle_name');

        return $this->success($query->paginate((int) $request->input('per_page', 20)));
    }

    public function assignmentStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'circle_id' => ['required', 'uuid', 'exists:circles,id'],
            'role' => ['required', 'string'],
        ]);

        abort_unless(in_array($validated['role'], self::SUPPORTED_ROLES, true), 422, 'Unsupported role.');

        $circleRole = match ($validated['role']) {
            'cf' => 'founder',
            'cd' => 'director',
            'powerhouse' => 'committee_leader',
            default => $validated['role'],
        };

        $exists = DB::table('circle_members')->where('user_id', $validated['user_id'])->where('circle_id', $validated['circle_id'])->whereNull('deleted_at')->exists();

        if ($exists) {
            DB::table('circle_members')->where('user_id', $validated['user_id'])->where('circle_id', $validated['circle_id'])->update(['role' => $circleRole, 'status' => 'approved', 'updated_at' => now()]);
        } else {
            DB::table('circle_members')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $validated['user_id'], 'circle_id' => $validated['circle_id'], 'role' => $circleRole, 'status' => 'approved', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $this->success(['assigned' => true]);
    }

    public function assignmentUpdate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string']]);
        DB::table('circle_members')->where('id', $id)->update(['role' => $validated['role'], 'updated_at' => now()]);

        return $this->success(['updated' => true]);
    }

    public function assignmentDelete(Request $request, string $id): JsonResponse
    {
        DB::table('circle_members')->where('id', $id)->update(['status' => 'inactive', 'deleted_at' => now(), 'updated_at' => now()]);
        $this->audit->log($request->user(), 'admin.leadership.assignment.revoke', 'circle_members', $id, [], ['status' => 'inactive'], $request);

        return $this->success(['revoked' => true]);
    }

    public function performance(Request $request): JsonResponse
    {
        $rows = DB::table('circle_members as cm')
            ->join('users', 'users.id', '=', 'cm.user_id')
            ->leftJoin('impacts', 'impacts.user_id', '=', 'cm.user_id')
            ->whereIn(DB::raw('cm.role::text'), ['founder', 'director', 'chair', 'vice_chair', 'secretary', 'committee_leader'])
            ->selectRaw('cm.user_id, users.display_name, cm.circle_id, cm.role::text as role, COUNT(impacts.id) as impact_count')
            ->groupBy('cm.user_id', 'users.display_name', 'cm.circle_id', DB::raw('cm.role::text'))
            ->orderByDesc('impact_count')
            ->paginate((int) $request->input('per_page', 20));

        return $this->success($rows);
    }
}
