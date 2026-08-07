<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\AdminUser;
use App\Services\Admin\PermissionService;
use App\Support\AdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminModuleController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $admin = $this->resolveAdminUser($request);

        if ($admin && ! AdminAccess::isGlobalAdmin($admin) && ! $request->boolean('all')) {
            $modules = app(PermissionService::class)->visibleModules($admin);
        } else {
            $modules = AdminModule::query()
                ->withCount('pages')
                ->orderBy('sort_order')
                ->get();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'modules' => $modules,
            ]);
        }

        return view('admin.rbac.modules.index', compact('modules'));
    }

    private function resolveAdminUser(Request $request): ?AdminUser
    {
        $user = $request->user();
        if ($user) {
            if ($user instanceof AdminUser) {
                return $user;
            }

            return AdminUser::query()
                ->where('id', $user->id)
                ->orWhereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
                ->first();
        }

        if (auth('admin')->check()) {
            return auth('admin')->user();
        }

        return null;
    }

    public function create(Request $request): View|JsonResponse
    {
        $nextOrder = (int) AdminModule::query()->max('sort_order') + 1;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'nextOrder' => $nextOrder,
            ]);
        }

        return view('admin.rbac.modules.form', [
            'module' => null,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:admin_modules,slug',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $module = AdminModule::query()->create($validated);

        // If user submitted quick page additions
        if ($request->has('quick_pages') && is_array($request->quick_pages)) {
            foreach ($request->quick_pages as $order => $pageData) {
                if (! empty($pageData['name']) && ! empty($pageData['route_name'])) {
                    AdminPage::query()->create([
                        'module_id' => $module->id,
                        'name' => $pageData['name'],
                        'route_name' => $pageData['route_name'],
                        'slug' => $pageData['slug'] ?? Str::slug($pageData['name']),
                        'icon' => $pageData['icon'] ?? $module->icon,
                        'sort_order' => $order + 1,
                        'is_active' => true,
                    ]);
                }
            }
        }

        app(PermissionService::class)->clearAllCaches();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Module created successfully.',
                'module' => $module->load('pages'),
            ], 201);
        }

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function edit(Request $request, string $id): View|JsonResponse
    {
        $module = AdminModule::query()->with('pages')->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'module' => $module,
            ]);
        }

        return view('admin.rbac.modules.form', compact('module'));
    }

    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $module = AdminModule::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:admin_modules,slug,'.$id,
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $module->update($validated);

        app(PermissionService::class)->clearAllCaches();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Module updated successfully.',
                'module' => $module->fresh('pages'),
            ]);
        }

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $module = AdminModule::query()->findOrFail($id);
        $module->delete();

        app(PermissionService::class)->clearAllCaches();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Module deleted successfully.',
            ]);
        }

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module deleted successfully.');
    }

    public function updateOrder(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|string',
        ]);

        foreach ($validated['order'] as $position => $moduleId) {
            AdminModule::query()
                ->where('id', $moduleId)
                ->update(['sort_order' => $position]);
        }

        app(PermissionService::class)->clearAllCaches();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Module order updated.',
            ]);
        }

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module order updated.');
    }
}
