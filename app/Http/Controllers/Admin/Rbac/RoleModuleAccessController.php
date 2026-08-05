<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RoleModuleAccessController extends Controller
{
    private const ALLOWED_MODULES = [
        'Dashboard',
        'RBAC & Role Hierarchy',
        'Activities',
        'Referral Report',
        'Posts & Timeline',
        'Pending Requests',
        'Support Tickets',
        'Events Management',
        'Brand Partners',
        'Ads',
        'Peers',
        'Member Introducers',
        'Sponsored Member Milestone Awards',
        'Unity Contacts',
        'Industries',
        'Login History',
        'Circles',
        'Circulars',
        'Coins',
        'Life Impact',
        'Notifications & Email',
        'Circle Categories',
        'Impact Option',
        'App Configuration',
        'App Updates Manager',
        'Birthday Creative',
        'Anniversary Creative',
        'Tutorials',
        'Leads',
    ];

    /**
     * Display the sidebar visibility matrix — role × module toggles.
     */
    public function index(Request $request): View
    {
        $roles = Role::orderBy('name')->get();
        $modules = AdminModule::where('is_active', true)
            ->whereIn('name', self::ALLOWED_MODULES)
            ->orderBy('sort_order')
            ->get();

        $selectedRoleId = $request->query('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        // Existing visibility: [module_id] = is_visible (bool)
        $visibility = [];
        if ($selectedRole) {
            $accessRecords = RoleModuleAccess::where('role_id', $selectedRole->id)->get();
            if ($accessRecords->isNotEmpty()) {
                foreach ($accessRecords as $access) {
                    $visibility[$access->module_id] = (bool) $access->is_visible;
                }
            } else {
                // Default all active modules to visible if role has no saved settings yet
                foreach ($modules as $module) {
                    $visibility[$module->id] = true;
                }
            }
        }

        return view('admin.rbac.module-access.index', compact(
            'roles',
            'modules',
            'selectedRole',
            'visibility',
        ));
    }

    /**
     * Save module visibility for a role.
     * Expects: { role_id, visible_module_ids: [uuid, ...] }
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'visible_module_ids' => ['present', 'array'],
            'visible_module_ids.*' => ['uuid', 'exists:admin_modules,id'],
        ]);

        $roleId = $validated['role_id'];
        $visibleIds = collect($validated['visible_module_ids']);

        // Get allowed module IDs
        $allowedModuleIds = AdminModule::whereIn('name', self::ALLOWED_MODULES)->pluck('id');

        // Delete existing only for allowed modules and re-insert
        RoleModuleAccess::where('role_id', $roleId)
            ->whereIn('module_id', $allowedModuleIds)
            ->delete();

        foreach ($allowedModuleIds as $moduleId) {
            RoleModuleAccess::create([
                'role_id' => $roleId,
                'module_id' => $moduleId,
                'is_visible' => $visibleIds->contains($moduleId),
            ]);
        }

        Cache::flush();

        return response()->json(['message' => 'Module visibility saved successfully.']);
    }
}
