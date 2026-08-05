<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\AdminPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function index(Request $request): View
    {
        $modules = AdminModule::query()->orderBy('sort_order')->get();
        $selectedModule = $request->get('module');

        $pages = AdminPage::query()
            ->with('module')
            ->when($selectedModule, fn ($q) => $q->where('module_id', $selectedModule))
            ->orderBy('sort_order')
            ->paginate(50);

        return view('admin.rbac.pages.index', compact('pages', 'modules', 'selectedModule'));
    }

    public function create(): View
    {
        $modules = AdminModule::query()->orderBy('sort_order')->get();

        return view('admin.rbac.pages.form', ['page' => null, 'modules' => $modules]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|uuid|exists:admin_modules,id',
            'name' => 'required|string|max:100',
            'route_name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        AdminPage::query()->create($validated);

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(string $id): View
    {
        $page = AdminPage::query()->findOrFail($id);
        $modules = AdminModule::query()->orderBy('sort_order')->get();

        return view('admin.rbac.pages.form', compact('page', 'modules'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $page = AdminPage::query()->findOrFail($id);

        $validated = $request->validate([
            'module_id' => 'required|uuid|exists:admin_modules,id',
            'name' => 'required|string|max:100',
            'route_name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $page->update($validated);

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $page = AdminPage::query()->findOrFail($id);
        $page->delete();

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}
