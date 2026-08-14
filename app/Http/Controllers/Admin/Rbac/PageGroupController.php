<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\PageGroup;
use App\Models\PageGroupItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageGroupController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $groups = PageGroup::query()
            ->with(['pages' => fn ($q) => $q->with('module')])
            ->withCount('pages')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'groups' => $groups,
            ]);
        }

        return view('admin.rbac.page-groups.index', compact('groups'));
    }

    public function create(Request $request): View|JsonResponse
    {
        $pages = AdminPage::query()
            ->with('module')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module.name');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'pages' => $pages,
            ]);
        }

        return view('admin.rbac.page-groups.form', ['group' => null, 'pages' => $pages]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page group created successfully.',
                'group' => $group->load('pages'),
            ], 201);
        }

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group created successfully.');
    }

    public function edit(Request $request, string $id): View|JsonResponse
    {
        $group = PageGroup::query()->with('pages')->findOrFail($id);

        $pages = AdminPage::query()
            ->with('module')
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module.name');

        $selectedPageIds = $group->pages->pluck('id')->all();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'group' => $group,
                'pages' => $pages,
                'selectedPageIds' => $selectedPageIds,
            ]);
        }

        return view('admin.rbac.page-groups.form', compact('group', 'pages', 'selectedPageIds'));
    }

    public function update(Request $request, string $id): RedirectResponse|JsonResponse
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

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page group updated successfully.',
                'group' => $group->fresh('pages'),
            ]);
        }

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        PageGroup::query()->findOrFail($id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page group deleted successfully.',
            ]);
        }

        return redirect()->route('admin.rbac.page-groups.index')
            ->with('success', 'Page group deleted successfully.');
    }
}
