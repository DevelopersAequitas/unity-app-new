<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Services\Admin\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminModuleController extends Controller
{
    public function index(): View
    {
        $modules = AdminModule::query()
            ->withCount('pages')
            ->orderBy('sort_order')
            ->get();

        return view('admin.rbac.modules.index', compact('modules'));
    }

    public function create(): View
    {
        $nextOrder = (int) AdminModule::query()->max('sort_order') + 1;

        return view('admin.rbac.modules.form', [
            'module' => null,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function edit(string $id): View
    {
        $module = AdminModule::query()->with('pages')->findOrFail($id);

        return view('admin.rbac.modules.form', compact('module'));
    }

    public function update(Request $request, string $id): RedirectResponse
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

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $module = AdminModule::query()->findOrFail($id);
        $module->delete();

        app(PermissionService::class)->clearAllCaches();

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module deleted successfully.');
    }

    public function updateOrder(Request $request): RedirectResponse
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

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module order updated.');
    }
}
