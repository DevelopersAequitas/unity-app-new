@extends('admin.layouts.app')
@section('title', 'Pages — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0">Admin Pages</h1>
        <p class="text-muted small mb-0">Pages mapped to routes inside each module.</p>
    </div>
    <a href="{{ route('admin.rbac.pages.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Page
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
                    <th>Page Name</th>
                    <th>Route Name</th>
                    <th>Sort</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pages as $page)
                <tr>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $page->module?->name ?? '—' }}</span></td>
                    <td class="fw-semibold">{{ $page->page_name }}</td>
                    <td><code class="small">{{ $page->route_name }}</code></td>
                    <td>{{ $page->sort_order }}</td>
                    <td>
                        @if($page->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.pages.edit', $page) }}" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.rbac.pages.destroy', $page) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this page?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No pages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pages->hasPages())
    <div class="card-footer bg-white">{{ $pages->links() }}</div>
    @endif
</div>
@endsection
