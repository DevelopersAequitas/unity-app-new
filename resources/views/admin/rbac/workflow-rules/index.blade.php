@extends('admin.layouts.app')
@section('title', 'Workflow Approval Rules — Dynamic RBAC')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-check2-circle text-primary me-2"></i>Workflow Approval Rules</h4>
        <p class="text-muted small mb-0">Configure multi-step approval hierarchies and required approver roles per module action.</p>
    </div>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- New Rule Form --}}
<div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Create Workflow Approval Step</h6>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.rbac.workflow-rules.store') }}">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted">TARGET MODULE</label>
                    <select name="module_id" class="form-select rounded-3" required>
                        <option value="">— Select Module —</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod->id }}">{{ $mod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted">WORKFLOW EVENT KEY</label>
                    <input type="text" name="workflow_name" class="form-control rounded-3" required placeholder="e.g. membership_approval">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted">REQUIRED APPROVER ROLE</label>
                    <select name="approver_role_id" class="form-select rounded-3" required>
                        <option value="">— Select Approver Role —</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }} ({{ $role->key }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-semibold small text-muted">STEP #</label>
                    <input type="number" name="step_order" class="form-control rounded-3" value="1" min="1">
                </div>
                <div class="col-md-1 text-center">
                    <label class="form-label fw-semibold small text-muted d-block">ACTIVE</label>
                    <div class="form-check form-switch d-inline-block">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked style="cursor: pointer;">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3" title="Save Rule">
                        <i class="bi bi-plus-lg"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Existing Rules --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-primary me-2"></i>Active Approval Workflow Steps</h6>
        <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ count($rules) }} Rules Configured</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Module</th>
                        <th>Workflow Event Key</th>
                        <th>Required Approver Role</th>
                        <th class="text-center">Step Order</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    <tr>
                        <td class="ps-4 fw-bold text-dark">
                            <i class="{{ $rule->module?->icon ?: 'bi-box' }} text-primary me-2"></i>{{ $rule->module?->name ?? '—' }}
                        </td>
                        <td><code>{{ $rule->workflow_name }}</code></td>
                        <td><span class="badge bg-primary px-3 py-2 rounded-pill fs-7"><i class="bi bi-person-badge me-1"></i>{{ $rule->approverRole?->name }}</span></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3 py-1 fw-bold rounded-pill">Step {{ $rule->step_order }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-1">
                                {{ $rule->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <form method="POST" action="{{ route('admin.rbac.workflow-rules.destroy', $rule->id) }}" class="d-inline" onsubmit="return confirm('Delete workflow approval rule?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm rounded-2"><i class="bi bi-trash me-1"></i>Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-diagram-3 fs-1 d-block mb-2 text-secondary"></i>
                            No approval workflow rules configured yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($rules->hasPages())
    <div class="card-footer bg-white py-3">{{ $rules->links() }}</div>
    @endif
</div>
@endsection
