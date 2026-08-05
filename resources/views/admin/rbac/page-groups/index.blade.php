@extends('admin.layouts.app')
@section('title', 'Page Groups — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Page Groups</h1>
        <p class="text-muted small mb-0">Bundle pages together for bulk role assignment.</p>
    </div>
    <a href="{{ route('admin.rbac.page-groups.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Group
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
                    <th>Name</th>
                    <th>Description</th>
                    <th>Pages</th>
                    <th>Sort</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $group)
                <tr>
                    <td class="fw-semibold">{{ $group->name }}</td>
                    <td class="text-muted small">{{ $group->description ?? '—' }}</td>
                    <td><span class="badge bg-info text-dark">{{ $group->pages_count }} pages</span></td>
                    <td>{{ $group->sort_order }}</td>
                    <td>
                        @if($group->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.page-groups.edit', $group) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.rbac.page-groups.destroy', $group) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this group?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No page groups yet. <a href="{{ route('admin.rbac.page-groups.create') }}">Add one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($groups->hasPages())
    <div class="card-footer bg-white">{{ $groups->links() }}</div>
    @endif
</div>
@endsection
