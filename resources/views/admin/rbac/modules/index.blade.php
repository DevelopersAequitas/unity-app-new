@extends('admin.layouts.app')
@section('title', 'Modules — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Sidebar Modules</h1>
        <p class="text-muted small mb-0">Manage the top-level sidebar menu items.</p>
    </div>
    <a href="{{ route('admin.rbac.modules.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Module
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
                    <th>Sort</th>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($modules as $module)
                <tr>
                    <td><span class="badge bg-secondary">{{ $module->sort_order }}</span></td>
                    <td><i class="bi {{ $module->icon }} fs-5"></i></td>
                    <td class="fw-semibold">{{ $module->name }}</td>
                    <td><code>{{ $module->slug }}</code></td>
                    <td>
                        @if($module->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.modules.edit', $module) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.rbac.modules.destroy', $module) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this module?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No modules yet. <a href="{{ route('admin.rbac.modules.create') }}">Add one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($modules->hasPages())
    <div class="card-footer bg-white">{{ $modules->links() }}</div>
    @endif
</div>
@endsection
