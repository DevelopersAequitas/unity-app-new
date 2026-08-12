@extends('admin.layouts.app')
@section('title', 'Page Groups — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-collection me-2"></i>Page Groups</h4>
    <div class="d-flex gap-2 align-items-center">
        @include('admin.rbac.partials.header_nav')
        <a href="{{ route('admin.rbac.page-groups.create') }}" class="btn btn-primary btn-sm ms-2"><i class="bi bi-plus-lg me-1"></i>Create Group</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    @foreach($groups as $group)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">{{ $group->name }}</h6>
                <p class="card-text text-muted small">{{ $group->description }}</p>
                <span class="badge bg-primary">{{ $group->pages_count }} pages</span>
                @if(!$group->is_active) <span class="badge bg-danger">Inactive</span> @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.rbac.page-groups.edit', $group->id) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
                <form method="POST" action="{{ route('admin.rbac.page-groups.destroy', $group->id) }}" onsubmit="return confirm('Delete?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
