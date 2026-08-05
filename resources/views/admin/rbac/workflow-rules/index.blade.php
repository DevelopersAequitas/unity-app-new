@extends('admin.layouts.app')
@section('title', 'Workflow Approval Rules — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Workflow Approval Rules</h4>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- New Rule Form --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Add Workflow Rule</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rbac.workflow-rules.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Module</label>
                    <select name="module_id" class="form-select" required>
                        @foreach($modules as $mod)
                            <option value="{{ $mod->id }}">{{ $mod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Workflow Name</label>
                    <input type="text" name="workflow_name" class="form-control" required placeholder="membership_approval">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Approver Role</label>
                    <select name="approver_role_id" class="form-select" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Step</label>
                    <input type="number" name="step_order" class="form-control" value="1" min="1">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                    </div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Module</th>
                    <th>Workflow</th>
                    <th>Approver</th>
                    <th class="text-center">Step</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $rule)
                <tr>
                    <td>{{ $rule->module?->name }}</td>
                    <td><code>{{ $rule->workflow_name }}</code></td>
                    <td><span class="badge bg-primary">{{ $rule->approverRole?->name }}</span></td>
                    <td class="text-center">{{ $rule->step_order }}</td>
                    <td class="text-center">
                        <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-danger' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.rbac.workflow-rules.destroy', $rule->id) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No workflow rules configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rules->hasPages())
    <div class="card-footer">{{ $rules->links() }}</div>
    @endif
</div>
@endsection
