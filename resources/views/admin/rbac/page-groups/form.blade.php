@extends('admin.layouts.app')
@section('title', ($group ? 'Edit' : 'Create') . ' Page Group — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-collection me-2"></i>{{ $group ? 'Edit' : 'Create' }} Page Group</h4>
    <a href="{{ route('admin.rbac.page-groups.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $group ? route('admin.rbac.page-groups.update', $group->id) : route('admin.rbac.page-groups.store') }}">
            @csrf
            @if($group) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Group Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $group?->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $group?->slug) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $group?->is_active ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $group?->description) }}</textarea>
                </div>
            </div>

            <hr>
            <h6>Select Pages</h6>
            <div class="row g-2">
                @foreach($pages as $moduleName => $modulePages)
                    <div class="col-12">
                        <strong class="text-primary">{{ $moduleName }}</strong>
                    </div>
                    @foreach($modulePages as $page)
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="page_ids[]" value="{{ $page->id }}"
                                       id="page_{{ $page->id }}"
                                       {{ in_array($page->id, $selectedPageIds ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label" for="page_{{ $page->id }}">
                                    {{ $page->name }}
                                    <small class="text-muted d-block">{{ $page->route_name }}</small>
                                </label>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>

            @if($errors->any())
                <div class="alert alert-danger mt-3">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ $group ? 'Update' : 'Create' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');

    if (nameInput && slugInput) {
        if (slugInput.value.trim() !== '') {
            slugInput.dataset.edited = 'true';
        }

        nameInput.addEventListener('input', function() {
            if (!slugInput.dataset.edited || slugInput.value.trim() === '') {
                slugInput.value = nameInput.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }
        });

        slugInput.addEventListener('input', function() {
            if (slugInput.value.trim() !== '') {
                slugInput.dataset.edited = 'true';
            } else {
                delete slugInput.dataset.edited;
            }
        });
    }
});
</script>
@endpush
