<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdminPage;
use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\RolePagePermission;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminIpAccessPolicy
{
    public const PAGE_ROUTE_NAME = 'admin.system.ip-access.index';

    /**
     * Determine whether the authenticated actor can view IP Access configuration.
     */
    public function view(?Authenticatable $user = null): bool
    {
        return $this->hasPermission($user, 'view');
    }

    /**
     * Determine whether the authenticated actor can add an IP address.
     */
    public function create(?Authenticatable $user = null): bool
    {
        return $this->hasPermission($user, 'create');
    }

    /**
     * Determine whether the authenticated actor can update enforcement settings.
     */
    public function update(?Authenticatable $user = null): bool
    {
        return $this->hasPermission($user, 'edit');
    }

    /**
     * Determine whether the authenticated actor can remove an IP address.
     */
    public function delete(?Authenticatable $user = null): bool
    {
        return $this->hasPermission($user, 'delete');
    }

    /**
     * Resolve the AdminUser record for the given authenticatable user or active guards.
     */
    public function resolveAdminUser(?Authenticatable $user = null): ?AdminUser
    {
        $candidate = $user ?? Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user() ?? Auth::user();

        if ($candidate instanceof AdminUser) {
            return $candidate;
        }

        $adminGuardUser = Auth::guard('admin')->user();
        if ($adminGuardUser instanceof AdminUser) {
            return $adminGuardUser;
        }

        if ($candidate instanceof User && Schema::hasTable('admin_users')) {
            try {
                $email = strtolower(trim((string) $candidate->email));

                return AdminUser::query()
                    ->where('id', (string) $candidate->id)
                    ->when($email !== '', fn ($q) => $q->orWhereRaw('LOWER(email) = ?', [$email]))
                    ->first();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Resolve actor metadata (id and email) for audit logging.
     *
     * @return array{id: string|null, email: string|null}
     */
    public function resolveActorMetadata(?Authenticatable $user = null): array
    {
        $adminUser = $this->resolveAdminUser($user);
        $candidate = $user ?? Auth::guard('sanctum')->user() ?? Auth::guard('admin')->user() ?? Auth::user();

        $id = $adminUser?->id ?? ($candidate ? (string) $candidate->getAuthIdentifier() : null);
        $email = $adminUser?->email ?? ($candidate->email ?? null);

        return [
            'id' => $id !== null && $id !== '' ? (string) $id : null,
            'email' => $email !== null && $email !== '' ? strtolower(trim((string) $email)) : null,
        ];
    }

    /**
     * Verify whether the actor is a Super Admin or has explicit RBAC permission on the IP Access page.
     */
    private function hasPermission(?Authenticatable $user, string $permissionKey): bool
    {
        $adminUser = $this->resolveAdminUser($user);
        if (! $adminUser) {
            return false;
        }

        $directRole = strtolower(trim((string) ($adminUser->role ?? '')));
        if (in_array($directRole, ['super_admin', 'global_admin', 'global_founder'], true)) {
            return true;
        }

        if (AdminAccess::isSuperAdmin($adminUser)) {
            return true;
        }

        return $this->hasExplicitDynamicRbacPermission($adminUser, $permissionKey);
    }

    /**
     * Check explicit dynamic RBAC permission on admin.system.ip-access.index.
     */
    private function hasExplicitDynamicRbacPermission(AdminUser $adminUser, string $permissionKey): bool
    {
        if (
            ! Schema::hasTable('admin_user_roles')
            || ! Schema::hasTable('admin_pages')
            || ! Schema::hasTable('role_page_permissions')
            || ! Schema::hasTable('permissions')
        ) {
            return false;
        }

        try {
            $roleIds = DB::table('admin_user_roles')
                ->where('user_id', $adminUser->id)
                ->pluck('role_id')
                ->all();

            if ($roleIds === []) {
                return false;
            }

            $page = AdminPage::query()
                ->where('route_name', self::PAGE_ROUTE_NAME)
                ->where('is_active', true)
                ->first();

            if (! $page) {
                return false;
            }

            $permissionId = Permission::idByKey($permissionKey);
            if (! $permissionId) {
                return false;
            }

            return RolePagePermission::query()
                ->whereIn('role_id', $roleIds)
                ->where('page_id', $page->id)
                ->where('permission_id', $permissionId)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}

