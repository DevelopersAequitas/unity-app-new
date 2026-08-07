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
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $admin = $this->resolveAdminUser($request);
        $modules = AdminModule::query()->orderBy('sort_order')->get();
        $selectedModule = $request->get('module');

        $pagesQuery = AdminPage::query()
            ->with('module')
            ->when($selectedModule, fn ($q) => $q->where('module_id', $selectedModule))
            ->orderBy('sort_order');

        if ($admin && ! AdminAccess::isGlobalAdmin($admin) && ! $request->boolean('all')) {
            $visibleModuleIds = app(PermissionService::class)->visibleModules($admin)->pluck('id')->all();
            $pagesQuery->whereIn('module_id', $visibleModuleIds);
        }

        $pages = $pagesQuery->paginate(50);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'pages' => $pages,
                'modules' => $modules,
                'selectedModule' => $selectedModule,
            ]);
        }

        return view('admin.rbac.pages.index', compact('pages', 'modules', 'selectedModule'));
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
        $modules = AdminModule::query()->orderBy('sort_order')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'modules' => $modules,
            ]);
        }

        return view('admin.rbac.pages.form', ['page' => null, 'modules' => $modules]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
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

        $page = AdminPage::query()->create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page created successfully.',
                'page' => $page->load('module'),
            ], 201);
        }

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(Request $request, string $id): View|JsonResponse
    {
        $page = AdminPage::query()->with('module')->findOrFail($id);
        $modules = AdminModule::query()->orderBy('sort_order')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'page' => $page,
                'modules' => $modules,
            ]);
        }

        return view('admin.rbac.pages.form', compact('page', 'modules'));
    }

    public function update(Request $request, string $id): RedirectResponse|JsonResponse
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

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page updated successfully.',
                'page' => $page->fresh('module'),
            ]);
        }

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $page = AdminPage::query()->findOrFail($id);
        $page->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Page deleted successfully.',
            ]);
        }

        return redirect()->route('admin.rbac.pages.index')
            ->with('success', 'Page deleted successfully.');
    }
}
