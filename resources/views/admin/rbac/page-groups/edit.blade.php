@extends('admin.layouts.app')
@section('title', 'Edit Page Group — RBAC')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.rbac.page-groups.index') }}" class="btn btn-sm btn-outline-secondary mb-2"><i class="bi bi-arrow-left me-1"></i> Back</a>
    <h1 class="h4 fw-bold mb-0">Edit Group: {{ $pageGroup->name }}</h1>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.rbac.page-groups.update', $pageGroup) }}" method="POST">
            @csrf @method('PUT')
            @php $assignedIds = $pageGroup->pages->pluck('id')->all(); @endphp
            <div class="row">
                <div class="col-md-5">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $pageGroup->name) }}">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $pageGroup->description) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $pageGroup->sort_order) }}" min="0">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $pageGroup->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Group</button>
                </div>
                <div class="col-md-7">
                    <label class="form-label fw-semibold">Pages in this Group</label>
                    <div class="border rounded p-3" style="max-height:420px;overflow-y:auto">
                        @foreach($pages->groupBy(fn($p) => $p->module?->name ?? 'Other') as $moduleName => $modulePages)
                        <p class="text-muted small fw-semibold mb-1 mt-2">{{ $moduleName }}</p>
                        @foreach($modulePages as $page)
                        <div class="form-check">
                            <input type="checkbox" name="page_ids[]" id="page_{{ $page->id }}" value="{{ $page->id }}"
                                class="form-check-input" {{ in_array($page->id, old('page_ids', $assignedIds)) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="page_{{ $page->id }}">{{ $page->page_name }}</label>
                        </div>
                        @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
