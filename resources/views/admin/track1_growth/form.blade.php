@extends('admin.layouts.app')

@section('title', ($mode === 'edit' ? 'Edit' : 'Create').' Track 1 Growth Honour')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow text-primary"></i> {{ $mode === 'edit' ? 'Edit' : 'Create' }} Growth Honour
            </h4>
            <p class="text-muted small mb-0">Configure dynamic threshold and description for Track 1 — Growth.</p>
        </div>
        <a href="{{ route('admin.track1-growth.index') }}" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Track 1 Honours
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following errors:</h6>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form method="POST" action="{{ $mode === 'edit' ? route('admin.track1-growth.update', $badge->id) : route('admin.track1-growth.store') }}" enctype="multipart/form-data">
                @csrf
                @if($mode === 'edit')
                    @method('PUT')
                @endif

                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="badgeTitle" class="form-label fw-semibold">Honour Title <span class="text-danger">*</span></label>
                            <input type="text" id="badgeTitle" name="title" class="form-control form-control-md" placeholder="e.g. Connector, Catalyst, Rainmaker" value="{{ old('title', $badge->title) }}" required>
                            <div class="form-text small">Official title of the honour (e.g. Connector, Vanguard, Global Icon).</div>
                        </div>

                        <div class="mb-3">
                            <label for="badgeDescription" class="form-label fw-semibold">What It Means / Description</label>
                            <textarea id="badgeDescription" name="description" class="form-control" rows="4" placeholder="e.g. You opened the first door. Someone's business is different because you made one introduction.">{{ old('description', $badge->description) }}</textarea>
                            <div class="form-text small">Meaningful description displayed on the timeline and user profile.</div>
                        </div>
                    </div>

                    <div class="col-md-4 border-start ps-4">
                        <div class="mb-3">
                            <label for="requiredCount" class="form-label fw-semibold">Required Introduced Members <span class="text-danger">*</span></label>
                            <input type="number" id="requiredCount" name="required_count" class="form-control form-control-md" min="0" value="{{ old('required_count', $badge->required_count ?? 1) }}" required>
                            <div class="form-text small">Lifetime cumulative introduced paid members count required.</div>
                        </div>

                        <div class="mb-3">
                            <label for="sortOrder" class="form-label fw-semibold">Sort Order <span class="text-danger">*</span></label>
                            <input type="number" id="sortOrder" name="sort_order" class="form-control form-control-md" min="0" value="{{ old('sort_order', $badge->sort_order ?? 0) }}" required>
                            <div class="form-text small">Numeric order (1 to 12).</div>
                        </div>

                        <div class="mb-3">
                            <label for="badgeImage" class="form-label fw-semibold">Badge Icon / Image</label>
                            @if($badge->badge_image_url)
                                <div class="mb-2">
                                    <img src="{{ $badge->badge_image_url }}" alt="{{ $badge->title }}" class="rounded border p-1 bg-white" style="width: 64px; height: 64px; object-fit: contain;">
                                </div>
                            @endif
                            <input type="file" id="badgeImage" name="badge_image" class="form-control form-control-sm" accept="image/*">
                        </div>

                        <div class="form-check form-switch mt-4">
                            <input type="checkbox" id="isActive" name="is_active" class="form-check-input" value="1" @checked(old('is_active', $badge->is_active ?? true))>
                            <label for="isActive" class="form-check-label fw-semibold">Active Status</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.track1-growth.index') }}" class="btn btn-outline-secondary fw-semibold">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check-lg me-1"></i> {{ $mode === 'edit' ? 'Update Honour' : 'Create Honour' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
