@extends('admin.layouts.app')
@section('title', ($module ? 'Edit' : 'Create') . ' Module — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box me-2"></i>{{ $module ? 'Edit' : 'Create' }} Module</h4>
    <a href="{{ route('admin.rbac.modules.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $module ? route('admin.rbac.modules.update', $module->id) : route('admin.rbac.modules.store') }}">
            @csrf
            @if($module) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $module?->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $module?->slug) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $module?->sort_order ?? 0) }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $module?->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $module ? 'Update' : 'Create' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
