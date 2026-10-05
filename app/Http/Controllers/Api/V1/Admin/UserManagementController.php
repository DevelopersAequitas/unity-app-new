<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AdminPeerIndexRequest;
use App\Http\Requests\Admin\AdminPeerUpgradeRequest;
use App\Models\CircleMember;
use App\Models\Impact;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminPeerService;
use App\Services\Admin\AdminScopeService;
use App\Services\Membership\MembershipNotificationService;
use App\Services\Membership\MembershipUpgradeService;
use App\Services\Membership\MembershipWelcomeEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminAuditService $audit,
        private readonly AdminPeerService $peerService,
        private readonly MembershipUpgradeService $upgradeService,
    ) {}

    public function index(AdminPeerIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 20);

        $paginator = $this->peerService->listPeers($filters, $request->user(), $perPage);

        return $this->success($this->peerService->transformPaginated($paginator));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::query()
            ->with([
                'roles:id,key,name',
                'circleMemberships' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at')->with('circle:id,name'),
                'mainBusinessCategory:id,name',
                'businessCategory:id,name',
                'cityRelation:id,name,state,country',
            ])
            ->findOrFail($id);

        $this->scope->applyUserScope(User::query()->where('id', $id), $request->user())->firstOrFail();

        return $this->success($this->peerService->transformPeer($user));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $target = User::query()->findOrFail($id);
        $this->scope->applyUserScope(User::query()->where('id', $id), $request->user())->firstOrFail();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'membership_status' => ['sometimes', 'string'],
            'membership_expiry' => ['sometimes', 'nullable', 'date'],
            'membership_ends_at' => ['sometimes', 'nullable', 'date'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*.id' => ['required_with:attachments', 'string'],
            'attachments.*.url' => ['required_with:attachments', 'url'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.original_name' => ['nullable', 'string', 'max:255'],
            'attachments.*.s3_key' => ['nullable', 'string', 'max:2048'],
        ]);

        $oldStatus = (string) ($target->membership_status ?? '');
        $attachments = $this->normalizeMembershipAttachments($validated['attachments'] ?? []);
        unset($validated['attachments']);

        if (array_key_exists('membership_expiry', $validated) && Schema::hasColumn('users', 'membership_ends_at')) {
            $validated['membership_ends_at'] = $validated['membership_expiry'];
        } elseif (array_key_exists('membership_ends_at', $validated) && Schema::hasColumn('users', 'membership_expiry')) {
            $validated['membership_expiry'] = $validated['membership_ends_at'];
        }

        $old = $target->only(array_keys($validated));
        $target->fill($validated)->save();

        $this->audit->log($request->user(), 'admin.user.update', 'users', $target->id, $old, $validated, $request);

        $fresh = $target->fresh();
        if ($fresh && $this->becameActiveMembership($oldStatus, (string) ($fresh->membership_status ?? ''))) {
            app(MembershipNotificationService::class)->sendMembershipWelcome($fresh, 'api_admin_membership_update', $attachments);
            app(MembershipWelcomeEmailService::class)->sendIfEligible($fresh, true, 'api_admin_membership_update', $attachments);
        }

        return $this->success($fresh);
    }

    private function becameActiveMembership(string $oldStatus, string $newStatus): bool
    {
        $inactive = ['', 'visitor', 'free_peer', 'free_trial_peer', 'inactive', 'rejected', 'cancelled', 'canceled', 'suspended'];

        return ! in_array(strtolower($newStatus), $inactive, true)
            && strtolower($oldStatus) !== strtolower($newStatus);
    }

    private function normalizeMembershipAttachments(array $attachments): array
    {
        return collect($attachments)
            ->filter(fn ($attachment): bool => is_array($attachment) && filled($attachment['id'] ?? null) && filled($attachment['url'] ?? null))
            ->map(fn (array $attachment): array => array_filter([
                'id' => (string) $attachment['id'],
                'url' => (string) $attachment['url'],
                'mime_type' => $attachment['mime_type'] ?? null,
                'original_name' => $attachment['original_name'] ?? $attachment['name'] ?? null,
                's3_key' => $attachment['s3_key'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    public function patchStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        return $this->update($request->merge($validated), $id);
    }

    public function patchMembershipStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['membership_status' => ['required', 'string']]);

        return $this->update($request->merge($validated), $id);
    }

    public function assignRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string']]);
        $target = User::query()->findOrFail($id);
        $role = Role::query()->where('key', $validated['role'])->firstOrFail();
        $target->roles()->syncWithoutDetaching([$role->id]);
        $this->audit->log($request->user(), 'admin.user.assign_role', 'users', $target->id, [], ['role' => $validated['role']], $request);

        return $this->success($target->load('roles'));
    }

    public function removeRole(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string']]);
        $target = User::query()->findOrFail($id);
        $role = Role::query()->where('key', $validated['role'])->firstOrFail();
        $target->roles()->detach($role->id);
        $this->audit->log($request->user(), 'admin.user.remove_role', 'users', $target->id, ['role' => $validated['role']], [], $request);

        return $this->success($target->load('roles'));
    }

    public function activitySummary(string $id): JsonResponse
    {
        return $this->success([
            'impacts' => Impact::query()->where('user_id', $id)->count(),
            'approved_impacts' => Impact::query()->where('user_id', $id)->where('status', 'approved')->count(),
            'payments' => Payment::query()->where('user_id', $id)->count(),
            'circles' => CircleMember::query()->where('user_id', $id)->whereNull('deleted_at')->count(),
        ]);
    }

    public function paymentHistory(string $id): JsonResponse
    {
        return $this->success(Payment::query()->where('user_id', $id)->latest('created_at')->paginate(20));
    }

    public function impactHistory(string $id): JsonResponse
    {
        return $this->success(Impact::query()->where('user_id', $id)->latest('created_at')->paginate(20));
    }

    public function circleMemberships(string $id): JsonResponse
    {
        return $this->success(CircleMember::query()->with('circle:id,name')->where('user_id', $id)->whereNull('deleted_at')->get());
    }

    public function upgrade(AdminPeerUpgradeRequest $request, string $id): JsonResponse
    {
        $target = User::query()->findOrFail($id);
        $this->scope->applyUserScope(User::query()->where('id', $id), $request->user())->firstOrFail();

        $validated = $request->validated();
        $adminUser = $request->user();

        $startDate = filled($validated['membership_starts_at'] ?? $validated['membership_start_date'] ?? null)
            ? Carbon::parse($validated['membership_starts_at'] ?? $validated['membership_start_date'])->startOfDay()
            : now()->startOfDay();

        $durationMonths = (int) ($validated['duration_months'] ?? 12);
        $endDate = filled($validated['membership_ends_at'] ?? $validated['membership_end_date'] ?? null)
            ? Carbon::parse($validated['membership_ends_at'] ?? $validated['membership_end_date'])->endOfDay()
            : $startDate->copy()->addMonths($durationMonths)->endOfDay();

        $planCode = (string) ($validated['plan_code'] ?? $validated['plan'] ?? '013');
        $targetStatus = (string) ($validated['target_status'] ?? User::STATUS_GREEN_PEER);

        $oldData = [
            'membership_status' => $target->membership_status,
            'membership_starts_at' => $target->membership_starts_at,
            'membership_ends_at' => $target->membership_ends_at,
        ];

        $upgradeData = [
            'membership_starts_at' => $startDate,
            'membership_ends_at' => $endDate,
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'duration_months' => $durationMonths,
            'plan_code' => $planCode,
            'zoho_plan_code' => $planCode,
            'membership_approved_by' => $adminUser?->id,
            'membership_approved_at' => now(),
            'amount' => $validated['amount'] ?? null,
            'force_dates' => true,
        ];

        $upgradedUser = $this->upgradeService->markAsOnlyGreenPeerAfterPayment($target, $upgradeData);

        if ($targetStatus && $targetStatus !== User::STATUS_GREEN_PEER) {
            $upgradedUser->membership_status = $targetStatus;
            $upgradedUser->save();
        }

        $this->audit->log(
            $adminUser,
            'admin.peer.upgrade',
            'users',
            $upgradedUser->id,
            $oldData,
            [
                'membership_status' => $upgradedUser->membership_status,
                'starts_at' => $startDate->toDateTimeString(),
                'ends_at' => $endDate->toDateTimeString(),
                'plan_code' => $planCode,
                'notes' => $validated['notes'] ?? $validated['reason'] ?? null,
            ],
            $request
        );

        $fresh = User::query()
            ->with([
                'roles:id,key,name',
                'circleMemberships' => fn ($q) => $q->where('status', 'approved')->whereNull('deleted_at')->with('circle:id,name'),
                'mainBusinessCategory:id,name',
                'businessCategory:id,name',
                'cityRelation:id,name,state,country',
            ])
            ->findOrFail($upgradedUser->id);

        return $this->success(
            $this->peerService->transformPeer($fresh),
            'Peer upgraded successfully to Pro/Global Peer.'
        );
    }
}
