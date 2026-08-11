@extends('admin.layouts.app')

@section('title', $mode === 'create' ? 'Create Milestone Badge' : 'Edit Milestone Badge')

@section('content')
<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.milestone-badges.index') }}" class="btn btn-sm btn-light border text-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <h4 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-award-fill text-primary me-2"></i>{{ $mode === 'create' ? 'Create Milestone Badge' : 'Edit Milestone Badge' }}
                </h4>
            </div>
            <p class="text-muted small mb-0 ms-1">Configure badge threshold, type, title, description, and badge icon.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.milestone-badges.index') }}" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following validation errors:</h6>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('admin.milestone-badges.store') : route('admin.milestone-badges.update', $badge->id) }}" enctype="multipart/form-data">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <div class="row g-4">
            {{-- Main Configuration Card --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-sliders text-primary me-2"></i>Badge Details
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Badge Type --}}
                            <div class="col-md-6">
                                <label for="badgeType" class="form-label fw-semibold text-dark small">
                                    Badge Category / Type <span class="text-danger">*</span>
                                </label>
                                <select id="badgeType" name="type" class="form-select form-select-md" required>
                                    <option value="">Select Category</option>
                                    <option value="life_impact" @selected(old('type', $badge->type) === 'life_impact')>❤️ Life Impact Badges</option>
                                    <option value="coins" @selected(old('type', $badge->type) === 'coins')>🪙 Coin Badges</option>
                                    <option value="member_introduction" @selected(old('type', $badge->type) === 'member_introduction')>👥 Member Introduction Badges</option>
                                </select>
                                <div class="form-text text-muted small">Choose which user metric activates this badge.</div>
                            </div>

                            {{-- Title --}}
                            <div class="col-md-6">
                                <label for="badgeTitle" class="form-label fw-semibold text-dark small">
                                    Badge Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="badgeTitle" name="title" class="form-control form-control-md" placeholder="e.g. Change Maker, Coin Starter, Connector" value="{{ old('title', $badge->title) }}" required>
                                <div class="form-text text-muted small">Displayed on mobile app & user profile.</div>
                            </div>

                            {{-- Required Count --}}
                            <div class="col-md-6">
                                <label for="requiredCount" class="form-label fw-semibold text-dark small">
                                    Required Count / Threshold <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-bar-chart-line"></i></span>
                                    <input type="number" id="requiredCount" name="required_count" min="0" class="form-control form-control-md" placeholder="e.g. 10, 5000, 5" value="{{ old('required_count', $badge->required_count) }}" required>
                                </div>
                                <div class="form-text text-muted small">Minimum value user must reach to unlock.</div>
                            </div>

                            {{-- Sort Order --}}
                            <div class="col-md-6">
                                <label for="sortOrder" class="form-label fw-semibold text-dark small">
                                    Display Sort Order <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-sort-numeric-down"></i></span>
                                    <input type="number" id="sortOrder" name="sort_order" min="0" class="form-control form-control-md" placeholder="0" value="{{ old('sort_order', $badge->sort_order ?? 0) }}" required>
                                </div>
                                <div class="form-text text-muted small">Lower numbers appear first.</div>
                            </div>

                            {{-- Description --}}
                            <div class="col-12">
                                <label for="badgeDescription" class="form-label fw-semibold text-dark small">Description / Meaning</label>
                                <textarea id="badgeDescription" name="description" rows="4" class="form-control" placeholder="Describe the accomplishment or milestone requirement...">{{ old('description', $badge->description) }}</textarea>
                                <div class="form-text text-muted small">Shown to users when viewing badge details.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar Settings (Media & Status) --}}
            <div class="col-lg-4">
                {{-- Status & Visibility --}}
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-toggle-on text-primary me-2"></i>Status & Visibility
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="isActiveSwitch" name="is_active" value="1" style="width: 2.5em; height: 1.25em;" @checked(old('is_active', $badge->is_active ?? true))>
                            <label class="form-check-label fw-semibold text-dark ms-2" for="isActiveSwitch">
                                Active Badge
                            </label>
                        </div>
                        <p class="text-muted small mb-0">When active, user progress is evaluated automatically and badges are awarded upon reaching the required count.</p>
                    </div>
                </div>

                {{-- Image Upload Card --}}
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-image text-primary me-2"></i>Badge Image / Icon
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        @if($badge->badge_image_url)
                            <div class="text-center p-3 mb-3 bg-light rounded-3 border">
                                <img src="{{ $badge->badge_image_url }}" alt="Badge Image" class="img-fluid rounded" style="max-height: 120px; object-fit: contain;" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%236c757d\' stroke-width=\'2\'><circle cx=\'12\' cy=\'8\' r=\'6\'/><path d=\'M15.477 12.89 17 22l-5-3-5 3 1.523-9.11\'/></svg>';">
                                <div class="small text-muted mt-2">Current Badge Image</div>
                            </div>
                        @else
                            <div class="text-center p-4 mb-3 bg-light rounded-3 border border-dashed">
                                <i class="bi bi-award text-secondary display-5"></i>
                                <div class="small text-muted mt-2">No image uploaded yet</div>
                            </div>
                        @endif

                        <label for="badgeImage" class="form-label fw-semibold text-dark small">Upload New Image</label>
                        <input type="file" id="badgeImage" name="badge_image" accept="image/*" class="form-control form-control-sm">
                        <div class="form-text text-muted small mt-2">Supports JPG, PNG, WEBP, or SVG (Max 5MB). High resolution icon recommended.</div>
                    </div>
                </div>
            </div>

            {{-- Form Submit Footer --}}
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <a href="{{ route('admin.milestone-badges.index') }}" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-x-lg me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-5 fw-semibold shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> {{ $mode === 'create' ? 'Create Badge' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
