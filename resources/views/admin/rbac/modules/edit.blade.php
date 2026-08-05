@extends('admin.layouts.app')
@section('title', 'Edit Module — RBAC')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.rbac.modules.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <h1 class="h4 fw-bold mb-0">Edit Module: {{ $module->name }}</h1>
</div>

<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('admin.rbac.modules.update', $module) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $module->name) }}">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $module->slug) }}">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Bootstrap Icon Class</label>
                <input type="text" name="icon" class="form-control" value="{{ old('icon', $module->icon) }}">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $module->sort_order) }}" min="0">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $module->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Module</button>
        </form>
    </div>
</div>
@endsection
