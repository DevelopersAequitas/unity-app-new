@extends('admin.layouts.app')
@section('title', 'Permissions — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Permissions</h1>
        <p class="text-muted small mb-0">Atomic actions: View, Create, Edit, Delete, Approve…</p>
    </div>
    <a href="{{ route('admin.rbac.permissions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Permission
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
                    <th>Name</th>
                    <th>Key</th>
                    <th>Description</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $perm)
                <tr>
                    <td><span class="badge bg-secondary">{{ $perm->sort_order }}</span></td>
                    <td class="fw-semibold">{{ $perm->name }}</td>
                    <td><code>{{ $perm->key }}</code></td>
                    <td class="text-muted small">{{ $perm->description }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.permissions.edit', $perm) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.rbac.permissions.destroy', $perm) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this permission?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No permissions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
