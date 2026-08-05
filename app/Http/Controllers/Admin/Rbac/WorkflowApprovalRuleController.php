<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\Role;
use App\Models\WorkflowApprovalRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowApprovalRuleController extends Controller
{
    public function index(): View
    {
        $rules = WorkflowApprovalRule::with(['module', 'approverRole'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->paginate(25);

        return view('admin.rbac.workflow-rules.index', compact('rules'));
    }

    public function create(): View
    {
        $modules = AdminModule::where('is_active', true)->orderBy('sort_order')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.rbac.workflow-rules.create', compact('modules', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'uuid', 'exists:admin_modules,id'],
            'workflow_action' => ['required', 'string', 'max:100'],
            'approver_role_id' => ['required', 'uuid', 'exists:roles,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        WorkflowApprovalRule::create($validated);

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow approval rule created.');
    }

    public function edit(WorkflowApprovalRule $workflowApprovalRule): View
    {
        $modules = AdminModule::where('is_active', true)->orderBy('sort_order')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.rbac.workflow-rules.edit', compact('workflowApprovalRule', 'modules', 'roles'));
    }

    public function update(Request $request, WorkflowApprovalRule $workflowApprovalRule): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'uuid', 'exists:admin_modules,id'],
            'workflow_action' => ['required', 'string', 'max:100'],
            'approver_role_id' => ['required', 'uuid', 'exists:roles,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $workflowApprovalRule->update($validated);

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow approval rule updated.');
    }

    public function destroy(WorkflowApprovalRule $workflowApprovalRule): RedirectResponse
    {
        $workflowApprovalRule->delete();

        return redirect()->route('admin.rbac.workflow-rules.index')
            ->with('success', 'Workflow approval rule deleted.');
    }
}
