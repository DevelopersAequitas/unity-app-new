<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\Permission;
use App\Models\RoleModuleAccess;
use App\Models\RolePagePermission;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Shared\Authorization\EffectiveAccessResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ScreenConfigController extends BaseApiController
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
        private readonly EffectiveAccessResolverService $accessResolver,
    ) {}

    /**
     * API 16: List Screens & Modules.
     */
    public function index(Request $request): JsonResponse
    {
        $targetApp = $request->query('target_app') ? (string) $request->query('target_app') : null;
        $screens = $this->accessResolver->resolveVisibleScreens(['global_admin'], true, $targetApp);

        return $this->success([
            'total_modules' => count($screens),
            'modules' => $screens,
        ], 'Screens registry fetched successfully.');
    }

    /**
     * API 17: Create Screen / Module Page.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => ['nullable', 'uuid'],
            'module_slug' => ['nullable', 'string', 'max:150'],
            'module_name' => ['nullable', 'string', 'max:150'],
            'screen_key' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'route_name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'page_url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'target_app' => ['nullable', 'string', 'in:admin_panel,leadership_app,main_app,both'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Schema::hasTable('admin_modules') || ! Schema::hasTable('admin_pages')) {
            return $this->error('Screen registry tables (admin_modules, admin_pages) are not yet initialized. Please run Script B.', 422);
        }

        $moduleId = $validated['module_id'] ?? null;
        if (! $moduleId) {
            $modSlug = $validated['module_slug'] ?? 'general';
            $mod = AdminModule::query()->firstOrCreate(
                ['slug' => $modSlug],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $validated['module_name'] ?? Str::headline($modSlug),
                    'sort_order' => 10,
                    'is_active' => true,
                ]
            );
            $moduleId = (string) $mod->id;
        }

        $cols = Schema::getColumnListing('admin_pages');
        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $payload = array_intersect_key([
            'id' => (string) Str::uuid(),
            'module_id' => $moduleId,
            'screen_key' => $validated['screen_key'] ?? $validated['route_name'],
            'name' => $validated['name'],
            'route_name' => $validated['route_name'],
            'slug' => $slug,
            'page_url' => $validated['page_url'] ?? ('/admin/'.$slug),
            'icon' => $validated['icon'] ?? 'bi-file-earmark',
            'target_app' => $validated['target_app'] ?? 'admin_panel',
            'sort_order' => $validated['sort_order'] ?? 10,
            'is_active' => $validated['is_active'] ?? true,
            'description' => $validated['description'] ?? null,
        ], array_flip($cols));

        $page = AdminPage::query()->updateOrCreate(['route_name' => $validated['route_name']], $payload);

        return $this->success($page, 'Screen saved successfully.', 201);
    }

    /**
     * API 18: Update Screen.
     */
    public function update(Request $request, string $screenId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'route_name' => ['sometimes', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'page_url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'target_app' => ['nullable', 'string', 'in:admin_panel,leadership_app,main_app,both'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if (! Schema::hasTable('admin_pages')) {
            return $this->error('Screen registry table (admin_pages) is not yet initialized.', 422);
        }

        $page = AdminPage::query()->findOrFail($screenId);
        $cols = Schema::getColumnListing('admin_pages');
        $page->fill(array_intersect_key($validated, array_flip($cols)))->save();

        return $this->success($page, 'Screen updated successfully.');
    }

    /**
     * API 19: Get Role Screen Access Matrix.
     */
    public function roleScreens(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $rKey = $formatted['normalized_key'];
            $isGlobal = in_array($rKey, ['global_admin', 'super_admin', 'country_director'], true);

            $screens = $this->accessResolver->resolveVisibleScreens([$rKey], $isGlobal);

            return $this->success([
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'role_name' => $role->name,
                'modules' => $screens,
            ], 'Role screen access matrix fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 20: Update Role Screen Access Matrix.
     */
    public function syncRoleScreens(Request $request, string $roleId): JsonResponse
    {
        $validated = $request->validate([
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['uuid'],
            'screen_ids' => ['nullable', 'array'],
            'screen_ids.*' => ['uuid'],
        ]);

        try {
            $role = $this->configService->resolveRoleOrFail($roleId);

            if (Schema::hasTable('role_module_access') && ! empty($validated['module_ids'])) {
                foreach ($validated['module_ids'] as $modId) {
                    RoleModuleAccess::query()->updateOrCreate(
                        ['role_id' => $role->id, 'module_id' => $modId],
                        ['is_visible' => true]
                    );
                }
            }

            if (Schema::hasTable('role_page_permissions') && Schema::hasTable('permissions') && ! empty($validated['screen_ids'])) {
                $viewPerm = Permission::query()->firstOrCreate(
                    ['key' => 'view'],
                    ['id' => (string) Str::uuid(), 'name' => 'View Page', 'sort_order' => 1]
                );

                RolePagePermission::query()
                    ->where('role_id', $role->id)
                    ->where('permission_id', $viewPerm->id)
                    ->delete();

                foreach ($validated['screen_ids'] as $pageId) {
                    RolePagePermission::query()->create([
                        'id' => (string) Str::uuid(),
                        'role_id' => $role->id,
                        'page_id' => $pageId,
                        'permission_id' => $viewPerm->id,
                        'is_granted' => true,
                    ]);
                }
            }

            return $this->roleScreens((string) $role->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}

