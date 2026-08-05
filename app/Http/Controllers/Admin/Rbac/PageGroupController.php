<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\PageGroup;
use App\Models\PageGroupItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageGroupController extends Controller
{
    public function index(): View
    {
        $groups = PageGroup::query()
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        return view('admin.rbac.page-groups.index', compact('groups'));
    }

    public function create(): View
    {
        $pages = AdminPage::query()
            ->with('module')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module.name');

        return view('admin.rbac.page-groups.form', ['group' => null, 'pages' => $pages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:page_groups,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'uuid|exists:admin_pages,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $group = PageGroup::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['page_ids'])) {
            $sortOrder = 0;
            foreach ($validated['page_ids'] as $pageId) {
                PageGroupItem::query()->create([
                    'page_group_id' => $group->id,
                    'page_id' => $pageId,
                    'sort_order' => ++$sortOrder,
                ]);
            }
        }

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group created successfully.');
    }

    public function edit(string $id): View
    {
        $group = PageGroup::query()->with('pages')->findOrFail($id);

        $pages = AdminPage::query()
            ->with('module')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module.name');

        $selectedPageIds = $group->pages->pluck('id')->all();

        return view('admin.rbac.page-groups.form', compact('group', 'pages', 'selectedPageIds'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $group = PageGroup::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:page_groups,slug,'.$id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'uuid|exists:admin_pages,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $group->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        // Rebuild page group items
        PageGroupItem::query()->where('page_group_id', $group->id)->delete();

        if (! empty($validated['page_ids'])) {
            $sortOrder = 0;
            foreach ($validated['page_ids'] as $pageId) {
                PageGroupItem::query()->create([
                    'page_group_id' => $group->id,
                    'page_id' => $pageId,
                    'sort_order' => ++$sortOrder,
                ]);
            }
        }

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        PageGroup::query()->findOrFail($id)->delete();

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group deleted successfully.');
    }
}
