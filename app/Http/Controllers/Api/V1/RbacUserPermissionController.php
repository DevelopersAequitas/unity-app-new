<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RbacUserPermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Get RBAC summary for the currently authenticated user/admin.
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Check if user is an AdminUser or linked via email/id
        $adminUser = AdminUser::query()->where('id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        if (! $adminUser) {
            return response()->json([
                'success' => true,
                'is_admin' => false,
                'roles' => [],
                'modules' => [],
                'data_scope' => ['is_global' => false, 'circle_ids' => []],
            ]);
        }

        $roles = DB::table('admin_user_roles')
            ->join('roles', 'admin_user_roles.role_id', '=', 'roles.id')
            ->where('admin_user_roles.user_id', $adminUser->id)
            ->select('roles.id', 'roles.key', 'roles.name', 'roles.role_type', 'roles.scope_rule')
            ->get();

        $userTree = $this->permissionService->userPermissionTree($adminUser);
        $visibleModules = $this->permissionService->visibleModules($adminUser);
        $dataScope = $this->permissionService->dataScope($adminUser);

        return response()->json([
            'success' => true,
            'is_admin' => true,
            'admin_user' => [
                'id' => $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
            ],
            'roles' => $roles,
            'modules' => $userTree,
            'allowed_modules' => $visibleModules,
            'data_scope' => [
                'scope_type' => $dataScope->scopeType,
                'is_global' => $dataScope->isGlobal,
                'scope_ids' => $dataScope->scopeIds,
                'circle_ids' => $dataScope->circleIds,
                'district_id' => $dataScope->districtId,
                'state_id' => $dataScope->stateId,
                'industry_ids' => $dataScope->industryIds,
            ],
        ]);
    }
}
