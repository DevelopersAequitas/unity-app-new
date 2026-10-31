<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Modules\DynamicAccess\Models\ReportDefinition;
use App\Modules\DynamicAccess\Models\RoleReportPermission;
use App\Modules\DynamicAccess\Models\UserOrganizationalAssignment;
use App\Modules\DynamicAccess\Requests\StoreOrganizationalAssignmentRequest;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Modules\DynamicAccess\Services\OrganizationalAssignmentSyncService;
use App\Shared\Authorization\EffectiveAccessResolverService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AssignmentAndReportConfigController extends BaseApiController
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
        private readonly OrganizationalAssignmentSyncService $syncService,
        private readonly OrganizationalScopeService $scopeService,
        private readonly EffectiveAccessResolverService $accessResolver,
    ) {}

    /**
     * API 26: List User Role Assignments.
     */
    public function listUserRoles(Request $request): JsonResponse
    {
        if (! Schema::hasTable('admin_user_roles')) {
            return $this->success(['total' => 0, 'items' => []]);
        }

        $query = DB::table('admin_user_roles as aur')
            ->join('roles as r', 'r.id', '=', 'aur.role_id')
            ->leftJoin('users as u', 'u.id', '=', 'aur.user_id')
            ->leftJoin('admin_users as au', 'au.id', '=', 'aur.user_id')
            ->select([
                'aur.user_id',
                'aur.role_id',
                'aur.permission_type',
                'aur.created_at',
                'r.key as role_key',
                'r.name as role_name',
                DB::raw('COALESCE(u.display_name, au.name) as user_name'),
                DB::raw('COALESCE(u.email, au.email) as user_email'),
            ]);

        if ($request->filled('user_id')) {
            $query->where('aur.user_id', (string) $request->query('user_id'));
        }
        if ($request->filled('role_id')) {
            $query->where('aur.role_id', (string) $request->query('role_id'));
        }

        $rows = $query->orderByDesc('aur.created_at')->limit(200)->get();

        return $this->success([
            'total' => $rows->count(),
            'items' => $rows,
        ], 'User role assignments fetched successfully.');
    }

    /**
     * API 27: Assign Role to User.
     */
    public function storeUserRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['nullable', 'string'],
            'role_key' => ['nullable', 'string'],
            'circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'scope_type' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $actor = $request->user() ?? auth('admin')->user();
            $record = $this->syncService->assign($validated, $actor ? (string) $actor->id : null);

            return $this->success($record, 'Role assigned to user successfully.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 28: Update User Role Assignment.
     */
    public function updateUserRole(Request $request, string $assignmentId): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_primary' => ['nullable', 'boolean'],
            'effective_to' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $actor = $request->user() ?? auth('admin')->user();
            $record = $this->syncService->assign($validated, $actor ? (string) $actor->id : null);

            return $this->success($record, 'User role assignment updated successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 29: Revoke User Role Assignment.
     */
    public function destroyUserRole(Request $request, string $assignmentId): JsonResponse
    {
        $userId = $request->query('user_id') ?? $request->input('user_id');
        $roleId = $request->query('role_id') ?? $request->input('role_id');

        if (Schema::hasTable('admin_user_roles')) {
            $q = DB::table('admin_user_roles');
            if (Schema::hasColumn('admin_user_roles', 'id') && Str::isUuid($assignmentId)) {
                $q->where('id', $assignmentId);
            } elseif ($userId && $roleId) {
                $q->where('user_id', (string) $userId)->where('role_id', (string) $roleId);
            } else {
                $q->where('user_id', $assignmentId);
            }
            $q->delete();
        }

        if ($userId) {
            $this->syncService->invalidateUserCache((string) $userId);
        }

        return $this->success(['revoked' => true], 'User role assignment revoked successfully.');
    }

    /**
     * API 30: Get User Effective Access Profile.
     */
    public function effectiveAccess(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $profile = $this->accessResolver->resolveEffectiveAccess($user);

        return $this->success($profile, 'Effective access profile resolved successfully.');
    }

    /**
     * API 31: List Organizational Assignments.
     */
    public function listOrgAssignments(Request $request): JsonResponse
    {
        if ($request->filled('user_id')) {
            $user = User::query()->findOrFail((string) $request->query('user_id'));
            $scope = $this->scopeService->resolveForActor($user);

            return $this->success([
                'total' => count($scope['assignments']),
                'items' => $scope['assignments'],
            ], 'Organizational assignments fetched successfully.');
        }

        if (Schema::hasTable('user_organizational_assignments')) {
            $rows = UserOrganizationalAssignment::query()
                ->with(['user:id,first_name,last_name,display_name,email', 'role:id,key,name', 'circle:id,name'])
                ->when($request->filled('circle_id'), fn ($q) => $q->where('circle_id', (string) $request->query('circle_id')))
                ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', (string) $request->query('role_id')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            return $this->success([
                'total' => $rows->count(),
                'items' => $rows,
            ], 'Organizational assignments fetched successfully.');
        }

        return $this->success(['total' => 0, 'items' => []], 'Organizational assignments fetched successfully.');
    }

    /**
     * API 32: Create Organizational Assignment.
     */
    public function storeOrgAssignment(StoreOrganizationalAssignmentRequest $request): JsonResponse
    {
        try {
            $actor = $request->user() ?? auth('admin')->user();
            $record = $this->syncService->assign($request->validated(), $actor ? (string) $actor->id : null);

            return $this->success($record, 'Organizational assignment created successfully.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 33: Bulk Assign Organizational Scope.
     */
    public function bulkStoreOrgAssignments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.user_id' => ['required', 'uuid', 'exists:users,id'],
            'assignments.*.role_id' => ['nullable', 'string'],
            'assignments.*.role_key' => ['nullable', 'string'],
            'assignments.*.scope_type' => ['nullable', 'string'],
            'assignments.*.circle_id' => ['nullable', 'uuid', 'exists:circles,id'],
            'assignments.*.district_id' => ['nullable', 'uuid'],
            'assignments.*.industry_id' => ['nullable', 'uuid'],
            'assignments.*.committee_key' => ['nullable', 'string'],
        ]);

        try {
            $actor = $request->user() ?? auth('admin')->user();
            $actorId = $actor ? (string) $actor->id : null;
            $created = [];
            foreach ($validated['assignments'] as $item) {
                $created[] = $this->syncService->assign($item, $actorId);
            }

            return $this->success([
                'total' => count($created),
                'items' => $created,
            ], 'Bulk organizational assignments created successfully.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 34: Update Organizational Assignment.
     */
    public function updateOrgAssignment(StoreOrganizationalAssignmentRequest $request, string $assignmentId): JsonResponse
    {
        try {
            $actor = $request->user() ?? auth('admin')->user();
            $record = $this->syncService->assign($request->validated(), $actor ? (string) $actor->id : null);

            return $this->success($record, 'Organizational assignment updated successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 35: Revoke Organizational Assignment.
     */
    public function destroyOrgAssignment(string $assignmentId): JsonResponse
    {
        if (Schema::hasTable('user_organizational_assignments')) {
            $uoa = UserOrganizationalAssignment::query()->find($assignmentId);
            if ($uoa) {
                $userId = (string) $uoa->user_id;
                $uoa->status = 'inactive';
                $uoa->save();
                $uoa->delete();
                $this->syncService->invalidateUserCache($userId);
            }
        }

        return $this->success(['revoked' => true], 'Organizational assignment revoked successfully.');
    }

    /**
     * API 36: Get User Organizational Scope Summary.
     */
    public function userScopeSummary(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $scope = $this->scopeService->resolveForActor($user);
        $scopedPeerIds = $this->scopeService->resolveScopedPeerIds($user);

        return $this->success([
            'user_id' => (string) $user->id,
            'membership_status' => (string) ($user->membership_status ?? 'free_peer'),
            'primary_role_key' => $scope['primary_role_key'],
            'effective_role_keys' => $scope['effective_role_keys'],
            'effective_scope_type' => $scope['effective_scope_type'],
            'is_global' => $scope['is_global'],
            'circle_ids' => $scope['circle_ids'],
            'district_ids' => $scope['district_ids'],
            'industry_ids' => $scope['industry_ids'],
            'committee_keys' => $scope['committee_keys'],
            'scoped_peers_count' => $scopedPeerIds === null ? User::query()->whereNull('deleted_at')->count() : count($scopedPeerIds),
            'assignments' => $scope['assignments'],
        ], 'User organizational scope summary resolved successfully.');
    }

    /**
     * API 37: List Report Definitions.
     */
    public function listReports(): JsonResponse
    {
        $reports = $this->accessResolver->resolveAllowedReports(['global_admin'], true);

        return $this->success([
            'total' => count($reports),
            'items' => $reports,
        ], 'Report definitions fetched successfully.');
    }

    /**
     * API 38 & 39: Create / Update Report Definition.
     */
    public function saveReportDefinition(Request $request, ?string $reportId = null): JsonResponse
    {
        $validated = $request->validate([
            'report_key' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'data_source_key' => ['required', 'string', 'max:100'],
            'default_scope_type' => ['nullable', 'string', 'max:50'],
            'supports_export' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (! Schema::hasTable('report_definitions')) {
            return $this->error('Table report_definitions is not yet initialized. Please run Script B.', 422);
        }

        $def = ReportDefinition::query()->updateOrCreate(
            ['report_key' => $validated['report_key']],
            [
                'name' => $validated['name'],
                'category' => $validated['category'] ?? 'leadership',
                'description' => $validated['description'] ?? null,
                'data_source_key' => $validated['data_source_key'],
                'default_scope_type' => $validated['default_scope_type'] ?? 'own_circle',
                'supports_export' => (bool) ($validated['supports_export'] ?? true),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 10),
            ]
        );

        return $this->success($def, 'Report definition saved successfully.', $reportId ? 200 : 201);
    }

    /**
     * API 40: Get Role Report Access Matrix.
     */
    public function roleReports(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $rKey = $formatted['normalized_key'];
            $isGlobal = in_array($rKey, ['global_admin', 'super_admin', 'country_director'], true);

            return $this->success([
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'role_name' => $role->name,
                'reports' => $this->accessResolver->resolveAllowedReports([$rKey], $isGlobal),
            ], 'Role report permissions fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 41: Update Role Report Access Matrix.
     */
    public function syncRoleReports(Request $request, string $roleId): JsonResponse
    {
        $validated = $request->validate([
            'reports' => ['required', 'array'],
            'reports.*.report_id' => ['required', 'uuid'],
            'reports.*.can_view' => ['required', 'boolean'],
            'reports.*.can_export' => ['nullable', 'boolean'],
            'reports.*.can_schedule' => ['nullable', 'boolean'],
            'reports.*.allowed_scope_override' => ['nullable', 'string'],
        ]);

        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            if (! Schema::hasTable('role_report_permissions')) {
                return $this->error('Table role_report_permissions is not yet initialized. Please run Script B.', 422);
            }

            foreach ($validated['reports'] as $rep) {
                RoleReportPermission::query()->updateOrCreate(
                    ['role_id' => $role->id, 'report_id' => $rep['report_id']],
                    [
                        'can_view' => (bool) $rep['can_view'],
                        'can_export' => (bool) ($rep['can_export'] ?? false),
                        'can_schedule' => (bool) ($rep['can_schedule'] ?? false),
                        'allowed_scope_override' => $rep['allowed_scope_override'] ?? null,
                    ]
                );
            }

            return $this->roleReports((string) $role->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}
