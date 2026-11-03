<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use App\Models\RolePagePermission;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RolePermissionMatrixController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * Resolve a role by UUID, key, name, role_code, or canonical alias.
     */
    protected function resolveRoleByIdentifier(?string $roleIdentifier, $roles): ?Role
    {
        if (empty($roleIdentifier)) {
            $preferred = $roles->first(function ($r) {
                $norm = strtolower(str_replace(['_', '-', ' '], '', (string) ($r->key ?: $r->name ?: $r->role_code)));
                return in_array($norm, ['circledirector', 'cd', 'circlefounder', 'cf', 'analyticsteam', 'analytics_team'], true);
            });
            return $preferred ?: $roles->first();
        }

        $cleaned = strtolower(trim($roleIdentifier));
        $normalized = strtolower(str_replace(['_', '-', ' '], '', $cleaned));

        // 1. Direct match (UUID, exact key, name, role_code)
        $match = $roles->first(function ($r) use ($roleIdentifier, $cleaned) {
            return (string) $r->id === $roleIdentifier
                || strtolower((string) $r->key) === $cleaned
                || strtolower((string) $r->name) === $cleaned
                || strtolower((string) ($r->role_code ?? '')) === $cleaned;
        });

        if ($match) {
            return $match;
        }

        // 2. Normalized alphanumeric match (handles camelCase vs snake_case e.g. circleDirector vs circle_director)
        $match = $roles->first(function ($r) use ($normalized) {
            $rKey = strtolower(str_replace(['_', '-', ' '], '', (string) $r->key));
            $rName = strtolower(str_replace(['_', '-', ' '], '', (string) $r->name));
            $rCode = strtolower(str_replace(['_', '-', ' '], '', (string) ($r->role_code ?? '')));

            return $rKey === $normalized || $rName === $normalized || $rCode === $normalized;
        });

        if ($match) {
            return $match;
        }

        // 3. Known canonical aliases
        $aliasMap = [
            'cd' => 'circledirector',
            'circle_director' => 'circledirector',
            'cf' => 'circlefounder',
            'circle_founder' => 'circlefounder',
            'ded' => 'districtexecdirector',
            'eed' => 'countrydirector',
            'id' => 'industrydirector',
            'chair' => 'circlechair',
            'ambassador' => 'ambassader',
            'ambassader' => 'ambassader',
        ];
        $targetAlias = $aliasMap[$normalized] ?? null;
        if ($targetAlias) {
            $match = $roles->first(function ($r) use ($targetAlias) {
                $rKey = strtolower(str_replace(['_', '-', ' '], '', (string) $r->key));
                $rName = strtolower(str_replace(['_', '-', ' '], '', (string) $r->name));
                $rCode = strtolower(str_replace(['_', '-', ' '], '', (string) ($r->role_code ?? '')));

                return $rKey === $targetAlias || $rName === $targetAlias || $rCode === $targetAlias;
            });
            if ($match) {
                return $match;
            }
        }

        return $roles->first();
    }

    /**
     * Resolve all matching sibling role IDs for shared canonical roles (e.g. CD & Circle Director).
     *
     * @return array<string>
     */
    protected function getMatchingRoleIds(Role $selectedRole, $roles): array
    {
        $matching = [(string) $selectedRole->id];
        $normSelected = strtolower(str_replace(['_', '-', ' '], '', (string) ($selectedRole->key ?: $selectedRole->name ?: $selectedRole->role_code)));

        foreach ($roles as $r) {
            $rNorm = strtolower(str_replace(['_', '-', ' '], '', (string) ($r->key ?: $r->name ?: $r->role_code)));
            if ($rNorm === $normSelected
                || (in_array($normSelected, ['circledirector', 'cd', 'circlefounder', 'cf'], true)
                    && in_array($rNorm, ['circledirector', 'cd', 'circlefounder', 'cf'], true))
                || ($normSelected === 'ded' && in_array($rNorm, ['ded', 'districtexecdirector'], true))
                || ($normSelected === 'districtexecdirector' && in_array($rNorm, ['ded', 'districtexecdirector'], true))
                || ($normSelected === 'ambassader' && in_array($rNorm, ['ambassader', 'ambassador'], true))
                || ($normSelected === 'ambassador' && in_array($rNorm, ['ambassader', 'ambassador'], true))
            ) {
                $matching[] = (string) $r->id;
            }
        }

        return array_values(array_unique($matching));
    }

    public function index(Request $request): View|JsonResponse
    {
        $roles = Role::query()
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('hierarchy_depth')
            ->orderBy('name')
            ->get();

        $selectedRoleId = $request->get('role_id');
        $selectedRole = $this->resolveRoleByIdentifier($selectedRoleId, $roles);

        $modulesQuery = AdminModule::query()
            ->active()
            ->orderBy('sort_order')
            ->with(['pages' => fn ($q) => $q->active()->orderBy('sort_order')]);

        // Super Admin manages permissions for ALL modules and pages.
        // Any newly created page or module is automatically loaded here.

        $modules = $modulesQuery->get();

        $permissions = Permission::query()->orderBy('sort_order')->get();

        // Get current page assignments for selected role (including canonical sibling roles)
        $currentPermissions = [];
        if ($selectedRole) {
            $matchingRoleIds = $this->getMatchingRoleIds($selectedRole, $roles);

            $assignedPageIds = RolePagePermission::query()
                ->whereIn('role_id', $matchingRoleIds)
                ->pluck('page_id')
                ->unique()
                ->all();

            foreach ($assignedPageIds as $pageId) {
                $currentPermissions[$pageId] = true;
            }

            // Always ensure Dashboard pages are granted by default for all administrative roles
            $dashboardModule = $modules->first(fn ($m) => strtolower((string) ($m->slug ?: $m->name)) === 'dashboard');
            if ($dashboardModule && $dashboardModule->pages) {
                foreach ($dashboardModule->pages as $dashPage) {
                    if (!isset($currentPermissions[$dashPage->id])) {
                        $currentPermissions[$dashPage->id] = true;
                    }
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'roles' => $roles,
                'selectedRole' => $selectedRole,
                'modules' => $modules,
                'permissions' => $permissions,
                'currentPermissions' => empty($currentPermissions) ? (object) [] : $currentPermissions,
            ]);
        }

        return view('admin.rbac.permission-matrix', compact(
            'roles',
            'selectedRole',
            'modules',
            'permissions',
            'currentPermissions',
        ));
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $roles = Role::query()->get();
        $roleIdInput = (string) $request->input('role_id');
        $targetRole = $this->resolveRoleByIdentifier($roleIdInput, $roles);

        if (!$targetRole) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Role not found.'], 404);
            }
            return back()->withErrors(['role_id' => 'Role not found.']);
        }

        $matchingRoleIds = $this->getMatchingRoleIds($targetRole, $roles);

        try {
            DB::transaction(function () use ($matchingRoleIds, $request) {
                // Delete all existing permissions for this role and its aliases
                foreach ($matchingRoleIds as $rId) {
                    RolePagePermission::query()->where('role_id', $rId)->delete();
                }

                $viewPermission = Permission::query()->where('key', 'view')->first();

                // Pre-load all admin page IDs and slug mappings to prevent PostgreSQL UUID cast errors or foreign key violations
                $adminPages = AdminPage::query()->get(['id', 'slug']);
                $validPageIds = [];
                $slugToIdMap = [];
                foreach ($adminPages as $ap) {
                    $validPageIds[(string) $ap->id] = (string) $ap->id;
                    if ($ap->slug) {
                        $slugToIdMap[strtolower(trim($ap->slug))] = (string) $ap->id;
                    }
                }

                // 1. Single-checkbox page access form submission
                if ($request->has('pages')) {
                    $pages = $request->input('pages', []);
                    $resolvedPageIds = [];

                    foreach ($pages as $pageKey => $enabled) {
                        if ((bool) $enabled) {
                            $keyStr = (string) $pageKey;
                            if (isset($validPageIds[$keyStr])) {
                                $resolvedPageIds[$keyStr] = true;
                            } elseif (isset($slugToIdMap[strtolower(trim($keyStr))])) {
                                $resolvedPageIds[$slugToIdMap[strtolower(trim($keyStr))]] = true;
                            }
                        }
                    }

                    if ($viewPermission && !empty($resolvedPageIds)) {
                        foreach (array_keys($resolvedPageIds) as $pId) {
                            foreach ($matchingRoleIds as $rId) {
                                RolePagePermission::query()->firstOrCreate([
                                    'role_id' => $rId,
                                    'page_id' => $pId,
                                    'permission_id' => $viewPermission->id,
                                ]);
                            }
                        }
                    }
                }
                // 2. Backward compatible 2D array form submission
                elseif ($request->has('permissions')) {
                    $permissionMatrix = $request->input('permissions', []);
                    foreach ($permissionMatrix as $pageKey => $permIds) {
                        $keyStr = (string) $pageKey;
                        $realPageId = $validPageIds[$keyStr] ?? ($slugToIdMap[strtolower(trim($keyStr))] ?? null);

                        if ($realPageId && is_array($permIds)) {
                            foreach ($permIds as $permId => $enabled) {
                                if ((bool) $enabled) {
                                    foreach ($matchingRoleIds as $rId) {
                                        RolePagePermission::query()->firstOrCreate([
                                            'role_id' => $rId,
                                            'page_id' => $realPageId,
                                            'permission_id' => $permId,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                // Synchronize RoleModuleAccess visibility with granted pages
                $enabledPageIds = RolePagePermission::query()
                    ->whereIn('role_id', $matchingRoleIds)
                    ->pluck('page_id')
                    ->unique()
                    ->all();

                $activeModules = AdminModule::with('pages')->get();
                foreach ($activeModules as $mod) {
                    $modPageIds = $mod->pages->pluck('id')->all();
                    $hasAnyPage = !empty(array_intersect($enabledPageIds, $modPageIds));
                    foreach ($matchingRoleIds as $rId) {
                        RoleModuleAccess::query()->updateOrCreate(
                            ['role_id' => $rId, 'module_id' => $mod->id],
                            ['is_visible' => $hasAnyPage]
                        );
                    }
                }

                // Invalidate caches
                foreach ($matchingRoleIds as $rId) {
                    $this->permissionService->invalidateCacheForRole($rId);
                }
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Permission matrix updated successfully.',
                    'role_id' => (string) $targetRole->id,
                ]);
            }

            return redirect()->route('admin.rbac.permission-matrix.index', ['role_id' => (string) $targetRole->id])
                ->with('success', 'Permission matrix updated successfully.');
        } catch (\Throwable $e) {
            Log::error('PermissionMatrixController update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'role_id' => $roleIdInput,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update permission matrix: ' . $e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to update permission matrix: ' . $e->getMessage()]);
        }
    }
}
