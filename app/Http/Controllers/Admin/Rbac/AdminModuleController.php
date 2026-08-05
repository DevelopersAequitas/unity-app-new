<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminModuleController extends Controller
{
    public function index(): View
    {
        $modules = AdminModule::orderBy('sort_order')->paginate(25);

        return view('admin.rbac.modules.index', compact('modules'));
    }

    public function create(): View
    {
        return view('admin.rbac.modules.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:admin_modules,slug'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        AdminModule::create($validated);

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module created successfully.');
    }

    public function edit(AdminModule $module): View
    {
        return view('admin.rbac.modules.edit', compact('module'));
    }

    public function update(Request $request, AdminModule $module): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:admin_modules,slug,'.$module->id],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $module->update($validated);

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(AdminModule $module): RedirectResponse
    {
        $module->delete();

        return redirect()->route('admin.rbac.modules.index')
            ->with('success', 'Module deleted successfully.');
    }
}
