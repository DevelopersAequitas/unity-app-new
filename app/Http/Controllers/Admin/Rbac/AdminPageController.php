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
    public function index(): View
    {
        $pages = AdminPage::with('module')
            ->orderBy('module_id')
            ->orderBy('sort_order')
            ->paginate(25);

        return view('admin.rbac.pages.index', compact('pages'));
    }

    public function create(): View
    {
        $modules = AdminModule::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.rbac.pages.create', compact('modules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'uuid', 'exists:admin_modules,id'],
            'page_name' => ['required', 'string', 'max:150'],
            'route_name' => ['required', 'string', 'max:150', 'unique:admin_pages,route_name'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        AdminPage::create($validated);

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(AdminPage $page): View
    {
        $modules = AdminModule::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.rbac.pages.edit', compact('page', 'modules'));
    }

    public function update(Request $request, AdminPage $page): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'uuid', 'exists:admin_modules,id'],
            'page_name' => ['required', 'string', 'max:150'],
            'route_name' => ['required', 'string', 'max:150', 'unique:admin_pages,route_name,'.$page->id],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $page->update($validated);

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(AdminPage $page): RedirectResponse
    {
        $page->delete();

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}
