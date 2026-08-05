@extends('admin.layouts.app')
@section('title', 'Workflow Approval Rules — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Workflow Approval Rules</h1>
        <p class="text-muted small mb-0">Define which role approves actions per module dynamically.</p>
    </div>
    <a href="{{ route('admin.rbac.workflow-rules.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Rule
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Approver Role</th>
                    <th>Sort</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $rule)
                <tr>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $rule->module?->name ?? '—' }}</span></td>
                    <td><code>{{ $rule->workflow_action }}</code></td>
                    <td><span class="badge bg-dark">{{ $rule->approverRole?->name ?? '—' }}</span></td>
                    <td>{{ $rule->sort_order }}</td>
                    <td>
                        @if($rule->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.workflow-rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.rbac.workflow-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No workflow rules defined yet. <a href="{{ route('admin.rbac.workflow-rules.create') }}">Create one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rules->hasPages())
    <div class="card-footer bg-white">{{ $rules->links() }}</div>
    @endif
</div>
@endsection
