@extends('admin.layouts.app')
@section('title', 'Edit Page — RBAC')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.rbac.pages.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <h1 class="h4 fw-bold mb-0">Edit Page: {{ $page->page_name }}</h1>
</div>
<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('admin.rbac.pages.update', $page) }}" method="POST">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Module <span class="text-danger">*</span></label>
                <select name="module_id" class="form-select @error('module_id') is-invalid @enderror">
                    <option value="">— Select Module —</option>
                    @foreach($modules as $module)
                        <option value="{{ $module->id }}" {{ old('module_id', $page->module_id) == $module->id ? 'selected' : '' }}>{{ $module->name }}</option>
                    @endforeach
                </select>
                @error('module_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Page Name <span class="text-danger">*</span></label>
                <input type="text" name="page_name" class="form-control @error('page_name') is-invalid @enderror" value="{{ old('page_name', $page->page_name) }}">
                @error('page_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Route Name <span class="text-danger">*</span></label>
                <input type="text" name="route_name" class="form-control @error('route_name') is-invalid @enderror" value="{{ old('route_name', $page->route_name) }}">
                @error('route_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $page->sort_order) }}" min="0">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $page->is_active) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Page</button>
        </form>
    </div>
</div>
@endsection
