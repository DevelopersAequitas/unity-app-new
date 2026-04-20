<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleJoinRequest;
use App\Models\CoinClaimRequest;
use App\Models\CoinsLedger;
use App\Models\Impact;
use App\Models\ImpactAction;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminScopeService;
use App\Services\Circles\CircleJoinRequestService;
use App\Services\Impacts\ImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminPlatformController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminAuditService $audit,
        private readonly CircleJoinRequestService $joinRequestService,
        private readonly ImpactService $impactService,
    ) {
    }

    private function authorizeAdmin(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->roles()->exists(), 403);

        return $user;
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        $user = $this->authorizeAdmin($request);

        $usersQ = DB::table('users');
        $circlesQ = DB::table('circles');
        $impactsQ = DB::table('impacts');

        $this->scope->applyUserScope($usersQ, $user);
        $this->scope->applyCircleScope($circlesQ, $user);
        $this->scope->applyUserScope($impactsQ, $user, 'impacts');

        return $this->success([
            'total_users' => $usersQ->count(),
            'total_active_members' => DB::table('users')->where('membership_status', 'active')->count(),
            'total_circles' => $circlesQ->count(),
            'total_industries' => Schema::hasTable('industries') ? DB::table('industries')->count() : 0,
            'total_districts' => Schema::hasTable('districts') ? DB::table('districts')->count() : 0,
            'total_leaders' => DB::table('admin_user_roles')->distinct('user_id')->count('user_id'),
            'total_revenue' => Schema::hasTable('payments') ? (float) DB::table('payments')->where('status', 'success')->sum('amount') : 0,
            'total_lives_impacted' => (int) $impactsQ->where('status', 'approved')->sum('impact_value'),
            'upcoming_events_count' => Schema::hasTable('events') ? DB::table('events')->whereDate('start_at', '>=', now()->toDateString())->count() : 0,
        ]);
    }

    public function dashboardRevenue(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->toDateString());

        $query = Payment::query()->whereBetween(DB::raw('DATE(created_at)'), [$from, $to]);

        return $this->success([
            'total' => (float) (clone $query)->where('status', 'success')->sum('amount'),
            'membership_revenue' => (float) (clone $query)->where('source', 'membership')->sum('amount'),
            'circle_fee_revenue' => (float) (clone $query)->where('source', 'circle_fee')->sum('amount'),
            'event_revenue' => (float) (clone $query)->whereIn('source', ['event', 'event_sponsor'])->sum('amount'),
        ]);
    }

    public function pendingCounts(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return $this->success([
            'pending_impacts' => Impact::query()->where('status', 'pending')->count(),
            'pending_coin_claims' => CoinClaimRequest::query()->where('status', 'pending')->count(),
            'pending_circle_join_requests' => CircleJoinRequest::query()->whereIn('status', ['pending_cd_approval', 'pending_id_approval', 'pending_circle_fee'])->count(),
            'renewal_due_count' => Schema::hasTable('member_renewals') ? DB::table('member_renewals')->where('status', 'due')->count() : 0,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $query = User::query()->with(['roles:id,key,name'])->withCount('circleMembers');
        $this->scope->applyUserScope($query->getQuery(), $admin);

        $query->when($request->query('search'), function ($q, $search) {
            $like = '%' . $search . '%';
            $q->where(fn ($w) => $w->where('first_name', 'ILIKE', $like)->orWhere('last_name', 'ILIKE', $like)->orWhere('display_name', 'ILIKE', $like)->orWhere('email', 'ILIKE', $like)->orWhere('phone', 'ILIKE', $like));
        });
        $query->when($request->query('membership_status'), fn ($q, $v) => $q->where('membership_status', $v));
        $query->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
        $query->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL)));

        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn ($rq) => $rq->where('key', $role));
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        return $this->success([
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function userShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $user = User::with(['roles:id,key,name', 'circleMembers.circle:id,name'])->findOrFail($id);
        return $this->success($user);
    }

    public function userUpdate(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $user = User::findOrFail($id);
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'display_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'membership_status' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $old = $user->only(array_keys($data));
        $user->fill($data)->save();
        $this->audit->log($admin, 'user.update', 'users', $user->id, $old, $data, $request);

        return $this->success($user, 'User updated successfully.');
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'role' => ['required', 'string'],
            'industry_id' => ['nullable', 'uuid'],
            'district_id' => ['nullable', 'uuid'],
            'circle_id' => ['nullable', 'uuid'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);
        $role = Role::query()->where('key', $data['role'])->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        if (Schema::hasTable('leader_role_assignments')) {
            DB::table('leader_role_assignments')->updateOrInsert([
                'user_id' => $user->id,
                'role_key' => $data['role'],
                'circle_id' => $data['circle_id'] ?? null,
                'industry_id' => $data['industry_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
            ], [
                'is_active' => true,
                'assigned_by' => $admin->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $this->audit->log($admin, 'user.assign_role', 'users', $user->id, [], $data, $request);

        return $this->success(['user_id' => $user->id, 'role' => $role->key], 'Role assigned successfully.');
    }

    public function removeRole(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'role' => ['required', 'string'],
            'revocation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);
        $role = Role::query()->where('key', $data['role'])->firstOrFail();
        $user->roles()->detach($role->id);

        if (Schema::hasTable('leader_role_assignments')) {
            DB::table('leader_role_assignments')
                ->where('user_id', $user->id)
                ->where('role_key', $data['role'])
                ->update(['is_active' => false, 'revoked_at' => now(), 'revoked_by' => $admin->id, 'revocation_reason' => $data['revocation_reason'] ?? null]);
        }

        $this->audit->log($admin, 'user.remove_role', 'users', $user->id, ['role' => $data['role']], [], $request);

        return $this->success(null, 'Role removed successfully.');
    }

    public function impacts(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $query = Impact::query()->with(['user:id,display_name,email', 'impactedPeer:id,display_name,email', 'action:id,name,points'])
            ->latest('created_at');
        $query->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
        $query->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v));
        $items = $query->paginate((int) $request->query('per_page', 20));

        return $this->success($items);
    }

    public function impactShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(Impact::with(['user', 'impactedPeer', 'action'])->findOrFail($id));
    }

    public function impactPending(Request $request): JsonResponse
    {
        $request->merge(['status' => 'pending']);
        return $this->impacts($request);
    }

    public function impactHistory(Request $request): JsonResponse
    {
        return $this->impacts($request);
    }

    public function impactActions(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(ImpactAction::query()->orderBy('name')->get());
    }

    public function impactActionStore(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'points' => ['required', 'integer', 'min:0']]);
        return $this->success(ImpactAction::create($data));
    }

    public function impactActionUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'points' => ['sometimes', 'integer', 'min:0']]);
        $action = ImpactAction::findOrFail($id);
        $action->fill($data)->save();
        return $this->success($action);
    }

    public function impactActionDelete(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        ImpactAction::findOrFail($id)->delete();
        return $this->success(null, 'Impact action deleted.');
    }

    public function approveImpact(Request $request, Impact $impact): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $payload = $request->validate(['review_remarks' => ['nullable', 'string', 'max:500']]);
        $updated = $this->impactService->approveImpact($impact, $admin, $payload['review_remarks'] ?? null);
        return $this->success($updated, 'Impact approved successfully.');
    }

    public function rejectImpact(Request $request, Impact $impact): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $payload = $request->validate(['review_remarks' => ['required', 'string', 'max:500']]);
        $updated = $this->impactService->rejectImpact($impact, $admin, $payload['review_remarks']);
        return $this->success($updated, 'Impact rejected successfully.');
    }

    public function coinClaims(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $query = CoinClaimRequest::query()->with('user:id,display_name,email')->latest('created_at');
        $query->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
        return $this->success($query->paginate((int) $request->query('per_page', 20)));
    }

    public function coinClaimShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(CoinClaimRequest::with('user')->findOrFail($id));
    }

    public function coinClaimApprove(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:500']]);
        $claim = CoinClaimRequest::findOrFail($id);
        abort_if($claim->status === 'approved', 422, 'Claim already approved.');

        DB::transaction(function () use ($claim, $admin, $data) {
            $claim->status = 'approved';
            $claim->reviewed_by = $admin->id;
            $claim->reviewed_at = now();
            $claim->review_remarks = $data['remarks'] ?? null;
            $claim->save();

            $exists = CoinsLedger::query()->where('activity_id', $claim->id)->where('activity_type', 'coin_claim')->exists();
            if (! $exists) {
                CoinsLedger::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $claim->user_id,
                    'activity_type' => 'coin_claim',
                    'activity_id' => $claim->id,
                    'title' => 'Admin approved coin claim',
                    'coins' => (int) $claim->requested_coins,
                    'source' => 'admin_approval',
                    'created_at' => now(),
                ]);
                User::where('id', $claim->user_id)->increment('coins_balance', (int) $claim->requested_coins);
            }
        });

        $this->audit->log($admin, 'coin_claim.approve', 'coin_claim_requests', $id, [], $data, $request);

        return $this->success($claim->fresh(), 'Coin claim approved.');
    }

    public function coinClaimReject(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $claim = CoinClaimRequest::findOrFail($id);
        $claim->status = 'rejected';
        $claim->reviewed_by = $admin->id;
        $claim->reviewed_at = now();
        $claim->review_remarks = $data['rejection_reason'];
        $claim->save();
        $this->audit->log($admin, 'coin_claim.reject', 'coin_claim_requests', $id, [], $data, $request);
        return $this->success($claim, 'Coin claim rejected.');
    }

    public function coinRules(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        if (Schema::hasTable('coin_rules')) {
            return $this->success(DB::table('coin_rules')->orderBy('created_at', 'desc')->get());
        }

        return $this->success(config('coins.rules', []));
    }

    public function coinRuleStore(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable('coin_rules'), 422, 'coin_rules table not available in this environment.');
        $data = $request->validate(['activity_code' => ['required', 'string'], 'coins' => ['required', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('coin_rules')->insert(array_merge($data, ['id' => $id, 'created_at' => now(), 'updated_at' => now()]));
        return $this->success(DB::table('coin_rules')->where('id', $id)->first(), 'Coin rule created.');
    }

    public function coinRuleUpdate(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable('coin_rules'), 422, 'coin_rules table not available in this environment.');
        $data = $request->validate(['activity_code' => ['sometimes', 'string'], 'coins' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']]);
        DB::table('coin_rules')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return $this->success(DB::table('coin_rules')->where('id', $id)->first(), 'Coin rule updated.');
    }

    public function coinRuleDelete(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable('coin_rules'), 422, 'coin_rules table not available in this environment.');
        DB::table('coin_rules')->where('id', $id)->delete();
        return $this->success(null, 'Coin rule deleted.');
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $query = Payment::query()->latest('created_at');
        $query->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
        $query->when($request->query('source'), fn ($q, $v) => $q->where('source', $v));
        $query->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v));

        return $this->success($query->paginate((int) $request->query('per_page', 20)));
    }

    public function paymentShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(Payment::findOrFail($id));
    }

    public function revenueByMember(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $rows = DB::table('payments')->selectRaw('user_id, SUM(amount) as total')->where('status', 'success')->groupBy('user_id')->orderByDesc('total')->limit(100)->get();
        return $this->success($rows);
    }

    public function posts(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(Post::with('user:id,display_name')->latest('created_at')->paginate((int) $request->query('per_page', 20)));
    }

    public function postShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(Post::with(['user', 'comments.user'])->findOrFail($id));
    }

    public function postStatus(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate(['status' => ['required', 'string', 'in:active,inactive,hidden,rejected']]);
        $post = Post::findOrFail($id);
        $old = ['status' => $post->status];
        $post->status = $data['status'];
        $post->save();
        $this->audit->log($admin, 'post.status', 'posts', $id, $old, $data, $request);
        return $this->success($post);
    }

    public function postDelete(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $post = Post::findOrFail($id);
        if (in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($post), true)) {
            $post->delete();
        } else {
            $post->status = 'hidden';
            $post->save();
        }

        return $this->success(null, 'Post removed successfully.');
    }

    public function postReports(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(PostReport::with(['post', 'reporter', 'reason'])->latest('created_at')->paginate((int) $request->query('per_page', 20)));
    }

    public function postReportShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        return $this->success(PostReport::with(['post', 'reporter', 'reason'])->findOrFail($id));
    }

    public function postReportResolve(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $report = PostReport::findOrFail($id);
        $report->status = 'resolved';
        $report->save();
        return $this->success($report);
    }

    public function postReportDismiss(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        $report = PostReport::findOrFail($id);
        $report->status = 'dismissed';
        $report->save();
        return $this->success($report);
    }

    // Leadership
    public function leadershipRoles(Request $request): JsonResponse { return $this->genericIndex($request, 'roles'); }
    public function leadershipApplications(Request $request): JsonResponse { return $this->genericIndex($request, 'leader_interest_submissions'); }
    public function leadershipApplicationShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'leader_interest_submissions', $id); }
    public function leadershipApplicationApprove(Request $request, string $id): JsonResponse { $request->merge(['status' => 'approved']); return $this->genericUpsert($request, 'leader_interest_submissions', $id); }
    public function leadershipApplicationReject(Request $request, string $id): JsonResponse { $request->merge(['status' => 'rejected']); return $this->genericUpsert($request, 'leader_interest_submissions', $id); }
    public function leadershipAssignments(Request $request): JsonResponse { return $this->genericIndex($request, 'leader_role_assignments'); }
    public function leadershipAssignmentStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'leader_role_assignments'); }
    public function leadershipAssignmentUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'leader_role_assignments', $id); }
    public function leadershipAssignmentDelete(Request $request, string $id): JsonResponse { $request->merge(['is_active' => false, 'revoked_at' => now()]); return $this->genericUpsert($request, 'leader_role_assignments', $id); }
    public function leadershipPerformance(Request $request): JsonResponse { return $this->genericIndex($request, 'leadership_scorecards'); }

    // Industries
    public function industries(Request $request): JsonResponse { return $this->genericIndex($request, 'industries'); }
    public function industryStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'industries'); }
    public function industryShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'industries', $id); }
    public function industryUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'industries', $id); }
    public function industryDelete(Request $request, string $id): JsonResponse { return $this->genericDelete($request, 'industries', $id); }
    public function industryAssignId(Request $request, string $id): JsonResponse { return $this->assignRole($request->merge(['role' => 'id', 'industry_id' => $id]), (string) $request->input('user_id')); }
    public function industryCircles(Request $request, string $id): JsonResponse { $request->merge(['industry_id' => $id]); return $this->genericIndex($request, 'circles'); }
    public function industryStats(Request $request, string $id): JsonResponse { return $this->dashboardSummary($request); }

    // Circles
    public function circles(Request $request): JsonResponse { return $this->genericIndex($request, 'circles'); }
    public function circleStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'circles'); }
    public function circleShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'circles', $id); }
    public function circleUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'circles', $id); }
    public function circleStatus(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'circles', $id); }
    public function circleAssignFounder(Request $request, string $id): JsonResponse { return $this->assignRole($request->merge(['role' => 'cf', 'circle_id' => $id]), (string) $request->input('user_id')); }
    public function circleAssignDirector(Request $request, string $id): JsonResponse { return $this->assignRole($request->merge(['role' => 'cd', 'circle_id' => $id]), (string) $request->input('user_id')); }
    public function circleAssignLeadershipTeam(Request $request, string $id): JsonResponse { $request->merge(['circle_id' => $id]); return $this->genericUpsert($request, 'leader_role_assignments'); }
    public function circleJoinRequests(Request $request, string $id): JsonResponse { $request->merge(['circle_id' => $id]); return $this->genericIndex($request, 'circle_join_requests'); }
    public function circleMembers(Request $request, string $id): JsonResponse { $request->merge(['circle_id' => $id]); return $this->genericIndex($request, 'circle_members'); }
    public function circleMemberStore(Request $request, string $id): JsonResponse { $request->merge(['circle_id' => $id]); return $this->genericUpsert($request, 'circle_members'); }
    public function circleMemberDelete(Request $request, string $id, string $userId): JsonResponse { return $this->genericDelete($request, 'circle_members', $userId); }
    public function circleHealth(Request $request, string $id): JsonResponse { return $this->dashboardSummary($request); }
    public function circlePerformance(Request $request, string $id): JsonResponse { return $this->dashboardSummary($request); }
    public function circlePackage(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'circles', $id); }

    // Join request actions
    public function joinRequestMarkPaid(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $record = CircleJoinRequest::query()->findOrFail($id);
        $updated = $this->joinRequestService->markPaidAndConvertToMember($record, ['marked_by_admin_id' => $admin->id]);
        $this->audit->log($admin, 'circle_join_request.mark_paid', 'circle_join_requests', $id, [], ['status' => $updated->status], $request);
        return $this->success($updated, 'Marked paid and converted to member.');
    }

    public function joinRequestCancel(Request $request, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        $record = CircleJoinRequest::query()->findOrFail($id);
        $record->status = CircleJoinRequest::STATUS_CANCELLED;
        $record->save();
        $this->audit->log($admin, 'circle_join_request.cancel', 'circle_join_requests', $id, [], ['status' => $record->status], $request);
        return $this->success($record, 'Join request cancelled.');
    }

    // Events/Billing/Forms/Notifications/Meetings/Reports
    public function events(Request $request): JsonResponse { return $this->genericIndex($request, 'events'); }
    public function eventStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'events'); }
    public function eventShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'events', $id); }
    public function eventUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'events', $id); }
    public function eventDelete(Request $request, string $id): JsonResponse { return $this->genericDelete($request, 'events', $id); }
    public function eventRegistrations(Request $request, string $id): JsonResponse { $request->merge(['event_id' => $id]); return $this->genericIndex($request, 'event_rsvps'); }
    public function eventAttendees(Request $request, string $id): JsonResponse { return $this->eventRegistrations($request, $id); }
    public function eventSpeakersStore(Request $request, string $id): JsonResponse { $request->merge(['event_id' => $id]); return $this->genericUpsert($request, 'event_speakers'); }
    public function eventSpeakersUpdate(Request $request, string $id, string $speakerId): JsonResponse { return $this->genericUpsert($request, 'event_speakers', $speakerId); }
    public function eventSpeakersDelete(Request $request, string $id, string $speakerId): JsonResponse { return $this->genericDelete($request, 'event_speakers', $speakerId); }
    public function eventExpensesStore(Request $request, string $id): JsonResponse { $request->merge(['event_id' => $id]); return $this->genericUpsert($request, 'event_expenses'); }
    public function eventExpenses(Request $request, string $id): JsonResponse { $request->merge(['event_id' => $id]); return $this->genericIndex($request, 'event_expenses'); }
    public function eventSponsorshipStore(Request $request, string $id): JsonResponse { $request->merge(['event_id' => $id]); return $this->genericUpsert($request, 'event_sponsors'); }
    public function eventPnl(Request $request, string $id): JsonResponse { return $this->dashboardRevenue($request); }
    public function eventApprove(Request $request, string $id): JsonResponse { $request->merge(['status' => 'approved']); return $this->genericUpsert($request, 'events', $id); }
    public function eventReject(Request $request, string $id): JsonResponse { $request->merge(['status' => 'rejected']); return $this->genericUpsert($request, 'events', $id); }

    public function billingInvoices(Request $request): JsonResponse { return $this->payments($request); }
    public function billingInvoiceShow(Request $request, string $id): JsonResponse { return $this->paymentShow($request, $id); }
    public function billingSubscriptions(Request $request): JsonResponse { return $this->genericIndex($request, 'circle_subscriptions'); }
    public function billingPlans(Request $request): JsonResponse { return $this->genericIndex($request, 'membership_plans'); }
    public function billingPlanUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'membership_plans', $id); }

    public function formLeaderInterest(Request $request): JsonResponse { return $this->genericIndex($request, 'leader_interest_submissions'); }
    public function formLeaderInterestShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'leader_interest_submissions', $id); }
    public function formLeaderInterestApprove(Request $request, string $id): JsonResponse { $request->merge(['status' => 'approved']); return $this->genericUpsert($request, 'leader_interest_submissions', $id); }
    public function formLeaderInterestReject(Request $request, string $id): JsonResponse { $request->merge(['status' => 'rejected']); return $this->genericUpsert($request, 'leader_interest_submissions', $id); }
    public function formRegisterVisitor(Request $request): JsonResponse { return $this->genericIndex($request, 'visitor_registrations'); }
    public function formRegisterVisitorShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'visitor_registrations', $id); }
    public function formRegisterVisitorStatus(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'visitor_registrations', $id); }
    public function formRecommendPeer(Request $request): JsonResponse { return $this->genericIndex($request, 'peer_recommendations'); }
    public function formRecommendPeerShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'peer_recommendations', $id); }
    public function formRecommendPeerStatus(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'peer_recommendations', $id); }

    public function notificationLogs(Request $request): JsonResponse { return $this->genericIndex($request, 'notifications'); }
    public function notificationBroadcast(Request $request): JsonResponse { return $this->genericUpsert($request, 'broadcast_messages'); }
    public function notificationTemplates(Request $request): JsonResponse { return $this->genericIndex($request, 'communication_templates'); }
    public function notificationTemplateStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'communication_templates'); }
    public function notificationTemplateUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'communication_templates', $id); }
    public function circulars(Request $request): JsonResponse { return $this->genericIndex($request, 'circulars'); }
    public function circularStore(Request $request): JsonResponse { return $this->genericUpsert($request, 'circulars'); }
    public function circularUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'circulars', $id); }
    public function circularDelete(Request $request, string $id): JsonResponse { return $this->genericDelete($request, 'circulars', $id); }

    public function circleMeetings(Request $request, string $circleId): JsonResponse { $request->merge(['circle_id' => $circleId]); return $this->genericIndex($request, 'circle_meetings'); }
    public function circleMeetingsStore(Request $request, string $circleId): JsonResponse { $request->merge(['circle_id' => $circleId]); return $this->genericUpsert($request, 'circle_meetings'); }
    public function meetingShow(Request $request, string $id): JsonResponse { return $this->genericShow($request, 'circle_meetings', $id); }
    public function meetingUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'circle_meetings', $id); }
    public function meetingAttendanceStore(Request $request, string $id): JsonResponse { $request->merge(['meeting_id' => $id]); return $this->genericUpsert($request, 'attendance_records'); }
    public function meetingAttendance(Request $request, string $id): JsonResponse { $request->merge(['meeting_id' => $id]); return $this->genericIndex($request, 'attendance_records'); }
    public function attendanceUpdate(Request $request, string $id): JsonResponse { return $this->genericUpsert($request, 'attendance_records', $id); }
    public function meetingSubstitutesStore(Request $request, string $id): JsonResponse { $request->merge(['meeting_id' => $id]); return $this->genericUpsert($request, 'substitute_logs'); }
    public function warnings(Request $request): JsonResponse { return $this->genericIndex($request, 'absence_warnings'); }
    public function warningResolve(Request $request, string $id): JsonResponse { $request->merge(['is_resolved' => true]); return $this->genericUpsert($request, 'absence_warnings', $id); }

    public function reportMembers(Request $request): JsonResponse { return $this->users($request); }
    public function reportCircles(Request $request): JsonResponse { return $this->genericIndex($request, 'circles'); }
    public function reportIndustries(Request $request): JsonResponse { return $this->genericIndex($request, 'industries'); }
    public function reportRevenue(Request $request): JsonResponse { return $this->dashboardRevenue($request); }
    public function reportImpacts(Request $request): JsonResponse { return $this->impacts($request); }
    public function reportEvents(Request $request): JsonResponse { return $this->events($request); }
    public function reportCoinClaims(Request $request): JsonResponse { return $this->coinClaims($request); }
    public function reportJoinRequests(Request $request): JsonResponse { return $this->genericIndex($request, 'circle_join_requests'); }
    public function reportExport(Request $request): JsonResponse { return $this->revenueByMember($request); }

    public function genericIndex(Request $request, string $table): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable($table), 404, 'Table not found.');
        $query = DB::table($table)->orderByDesc('created_at');
        $items = $query->paginate((int) $request->query('per_page', 20));
        return $this->success($items);
    }

    public function genericShow(Request $request, string $table, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable($table), 404, 'Table not found.');
        $item = DB::table($table)->where('id', $id)->first();
        abort_if(! $item, 404);
        return $this->success($item);
    }

    public function genericUpsert(Request $request, string $table, ?string $id = null): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable($table), 404, 'Table not found.');
        $payload = $request->except(['_token']);
        if ($id) {
            $old = (array) (DB::table($table)->where('id', $id)->first() ?? []);
            DB::table($table)->where('id', $id)->update(array_merge($payload, ['updated_at' => now()]));
            $this->audit->log($admin, 'admin.update', $table, $id, $old, $payload, $request);
            return $this->success(DB::table($table)->where('id', $id)->first());
        }

        $id = $payload['id'] ?? (string) \Illuminate\Support\Str::uuid();
        DB::table($table)->insert(array_merge($payload, ['id' => $id, 'created_at' => now(), 'updated_at' => now()]));
        $this->audit->log($admin, 'admin.create', $table, $id, [], $payload, $request);
        return $this->success(DB::table($table)->where('id', $id)->first(), 'Created successfully.');
    }

    public function genericDelete(Request $request, string $table, string $id): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);
        abort_unless(Schema::hasTable($table), 404, 'Table not found.');
        $old = (array) (DB::table($table)->where('id', $id)->first() ?? []);
        if (Schema::hasColumn($table, 'deleted_at')) {
            DB::table($table)->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        } elseif (Schema::hasColumn($table, 'is_active')) {
            DB::table($table)->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
        } else {
            DB::table($table)->where('id', $id)->delete();
        }

        $this->audit->log($admin, 'admin.delete', $table, $id, $old, [], $request);
        return $this->success(null, 'Deleted successfully.');
    }
}
