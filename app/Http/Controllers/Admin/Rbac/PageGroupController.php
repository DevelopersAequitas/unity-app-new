<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\PageGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageGroupController extends Controller
{
    public function index(): View
    {
        $groups = PageGroup::withCount('pages')->orderBy('sort_order')->paginate(25);

        return view('admin.rbac.page-groups.index', compact('groups'));
    }

    public function create(): View
    {
        $pages = AdminPage::where('is_active', true)
            ->with('module')
            ->orderBy('sort_order')
            ->get();

        return view('admin.rbac.page-groups.create', compact('pages'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['uuid', 'exists:admin_pages,id'],
        ]);

        $group = PageGroup::create($validated);
        $group->pages()->sync($validated['page_ids'] ?? []);

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group created successfully.');
    }

    public function edit(PageGroup $pageGroup): View
    {
        $pageGroup->load('pages');
        $pages = AdminPage::where('is_active', true)
            ->with('module')
            ->orderBy('sort_order')
            ->get();

        return view('admin.rbac.page-groups.edit', compact('pageGroup', 'pages'));
    }

    public function update(Request $request, PageGroup $pageGroup): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['uuid', 'exists:admin_pages,id'],
        ]);

        $pageGroup->update($validated);
        $pageGroup->pages()->sync($validated['page_ids'] ?? []);

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group updated successfully.');
    }

    public function destroy(PageGroup $pageGroup): RedirectResponse
    {
        $pageGroup->delete();

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group deleted successfully.');
    }
}
