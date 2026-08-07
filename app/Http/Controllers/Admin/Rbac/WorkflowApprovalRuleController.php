<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\Role;
use App\Models\WorkflowApprovalRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowApprovalRuleController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $rules = WorkflowApprovalRule::query()
            ->with(['module', 'approverRole'])
            ->orderBy('module_id')
            ->orderBy('workflow_name')
            ->orderBy('step_order')
            ->paginate(50);

        $modules = AdminModule::query()->active()->orderBy('sort_order')->get();
        $roles = Role::query()->where('status', 'active')->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'rules' => $rules,
                'modules' => $modules,
                'roles' => $roles,
            ]);
        }

        return view('admin.rbac.workflow-rules.index', compact('rules', 'modules', 'roles'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|uuid|exists:admin_modules,id',
            'workflow_name' => 'required|string|max:100',
            'approver_role_id' => 'required|uuid|exists:roles,id',
            'step_order' => 'integer|min:1',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $rule = WorkflowApprovalRule::query()->create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Workflow rule created successfully.',
                'rule' => $rule->load(['module', 'approverRole']),
            ], 201);
        }

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow rule created successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $rule = WorkflowApprovalRule::query()->findOrFail($id);

        $validated = $request->validate([
            'module_id' => 'required|uuid|exists:admin_modules,id',
            'workflow_name' => 'required|string|max:100',
            'approver_role_id' => 'required|uuid|exists:roles,id',
            'step_order' => 'integer|min:1',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $rule->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Workflow rule updated successfully.',
                'rule' => $rule->fresh(['module', 'approverRole']),
            ]);
        }

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow rule updated successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        WorkflowApprovalRule::query()->findOrFail($id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Workflow rule deleted successfully.',
            ]);
        }

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow rule deleted successfully.');
    }
}
