@extends('admin.layouts.app')
@section('title', 'Pages — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark me-2"></i>Admin Pages</h4>
    <div class="d-flex gap-2 align-items-center">
        @include('admin.rbac.partials.header_nav')
        <a href="{{ route('admin.rbac.pages.create') }}" class="btn btn-primary btn-sm ms-2"><i class="bi bi-plus-lg me-1"></i>Add Page</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row align-items-center g-2">
            <div class="col-auto"><label class="form-label mb-0">Filter by Module:</label></div>
            <div class="col-md-3">
                <select name="module" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Modules</option>
                    @foreach($modules as $mod)
                        <option value="{{ $mod->id }}" {{ $selectedModule === $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0" style="font-size: 0.85rem;">
            <thead class="table-light">
                <tr>
                    <th>Page Name</th>
                    <th>Module</th>
                    <th>Route Name</th>
                    <th class="text-center">Order</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pages as $page)
                <tr>
                    <td><strong>{{ $page->name }}</strong></td>
                    <td><span class="badge bg-info">{{ $page->module?->name }}</span></td>
                    <td><code>{{ $page->route_name }}</code></td>
                    <td class="text-center">{{ $page->sort_order }}</td>
                    <td class="text-center">
                        <span class="badge {{ $page->is_active ? 'bg-success' : 'bg-danger' }}">{{ $page->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.pages.edit', $page->id) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.rbac.pages.destroy', $page->id) }}" class="d-inline" onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4 text-muted">No pages found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pages->hasPages())
    <div class="card-footer">{{ $pages->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
