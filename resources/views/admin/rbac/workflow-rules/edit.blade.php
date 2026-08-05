@extends('admin.layouts.app')
@section('title', 'Edit Workflow Rule — RBAC')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.rbac.workflow-rules.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <h1 class="h4 fw-bold mb-0">Edit Workflow Rule</h1>
</div>

<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('admin.rbac.workflow-rules.update', $workflowApprovalRule) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Module <span class="text-danger">*</span></label>
                <select name="module_id" class="form-select @error('module_id') is-invalid @enderror">
                    <option value="">— Select Module —</option>
                    @foreach($modules as $module)
                        <option value="{{ $module->id }}" {{ old('module_id', $workflowApprovalRule->module_id) == $module->id ? 'selected' : '' }}>{{ $module->name }}</option>
                    @endforeach
                </select>
                @error('module_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Workflow Action <span class="text-danger">*</span></label>
                <input type="text" name="workflow_action" class="form-control @error('workflow_action') is-invalid @enderror" value="{{ old('workflow_action', $workflowApprovalRule->workflow_action) }}">
                @error('workflow_action')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Approver Role <span class="text-danger">*</span></label>
                <select name="approver_role_id" class="form-select @error('approver_role_id') is-invalid @enderror">
                    <option value="">— Select Approver Role —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ old('approver_role_id', $workflowApprovalRule->approver_role_id) == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('approver_role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $workflowApprovalRule->sort_order) }}" min="0">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $workflowApprovalRule->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Rule</button>
        </form>
    </div>
</div>
@endsection
