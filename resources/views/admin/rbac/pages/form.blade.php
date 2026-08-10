@extends('admin.layouts.app')
@section('title', ($page ? 'Edit' : 'Create') . ' Page — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark me-2"></i>{{ $page ? 'Edit' : 'Create' }} Page</h4>
    <a href="{{ route('admin.rbac.pages.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $page ? route('admin.rbac.pages.update', $page->id) : route('admin.rbac.pages.store') }}">
            @csrf
            @if($page) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Module</label>
                    <select name="module_id" class="form-select" required>
                        @foreach($modules as $mod)
                            <option value="{{ $mod->id }}" {{ old('module_id', $page?->module_id) === $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Page Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $page?->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Route Name</label>
                    <input type="text" name="route_name" class="form-control" value="{{ old('route_name', $page?->route_name) }}" required placeholder="admin.users.index">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $page?->slug) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Page URL</label>
                    <input type="text" name="page_url" class="form-control" value="{{ old('page_url', $page?->page_url) }}" placeholder="/admin/users">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $page?->sort_order ?? 0) }}" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $page?->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $page?->description) }}</textarea>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $page ? 'Update' : 'Create' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
