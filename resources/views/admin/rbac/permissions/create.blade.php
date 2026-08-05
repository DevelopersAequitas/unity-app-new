@extends('admin.layouts.app')
@section('title', 'Add Permission — RBAC')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.rbac.permissions.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <h1 class="h4 fw-bold mb-0">Add Permission</h1>
</div>
<div class="card border-0 shadow-sm" style="max-width:540px">
    <div class="card-body">
        <form action="{{ route('admin.rbac.permissions.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. Approve">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Key <span class="text-danger">*</span></label>
                <input type="text" name="key" class="form-control @error('key') is-invalid @enderror" value="{{ old('key') }}" placeholder="e.g. approve">
                @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="e.g. Approve pending items">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Permission</button>
        </form>
    </div>
</div>
@endsection
