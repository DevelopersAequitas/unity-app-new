@push('styles')
<style>
    #adTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #adTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #adTabs .nav-link.active {
        background-color: var(--primary);
        color: #ffffff;
        box-shadow: var(--shadow-sm);
    }
    .form-section-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
</style>
@endpush

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="adTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="basic-tab" data-bs-toggle="pill" data-bs-target="#basic-section" type="button" role="tab" aria-controls="basic-section" aria-selected="true">
                    <i class="bi bi-info-circle me-1"></i>1. Basic & Placement
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="media-tab" data-bs-toggle="pill" data-bs-target="#media-section" type="button" role="tab" aria-controls="media-section" aria-selected="false">
                    <i class="bi bi-images me-1"></i>2. Redirects & Media
                </button>
            </li>
        </ul>

        <div class="p-4">
            <div class="tab-content" id="adTabsContent">
                <!-- Tab 1: Basic & Placement -->
                <div class="tab-pane fade show active" id="basic-section" role="tabpanel" aria-labelledby="basic-tab">
                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Ad Identification</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $ad->title) }}" required maxlength="255" placeholder="e.g. Annual Summit Banner">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subtitle</label>
                            <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $ad->subtitle) }}" maxlength="255" placeholder="e.g. Join the gathering of top MSME leaders">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide details about this campaign...">{{ old('description', $ad->description) }}</textarea>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Placement Configuration</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Placement <span class="text-danger">*</span></label>
                            <select name="placement" class="form-select js-no-searchable-select" required>
                                @foreach($placements as $placement)
                                    <option value="{{ $placement }}" @selected(old('placement', $ad->placement) === $placement)>{{ ucfirst($placement) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Page Name</label>
                            <input type="text" name="page_name" class="form-control" value="{{ old('page_name', $ad->page_name) }}" maxlength="100" placeholder="e.g. dashboard, timeline">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Timeline Position</label>
                            <input type="number" min="1" name="timeline_position" class="form-control" value="{{ old('timeline_position', $ad->timeline_position) }}" placeholder="e.g. 3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $ad->sort_order ?? 0) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch mt-3 border rounded p-3 w-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $ad->is_active ?? true))>
                                <label class="form-check-label fw-semibold text-dark" for="is_active">Publish Status (Active)</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-primary" onclick="switchTab('media-tab')">
                            Next: Redirects & Media <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Tab 2: Redirects & Media -->
                <div class="tab-pane fade" id="media-section" role="tabpanel" aria-labelledby="media-tab">
                    <h5 class="form-section-title"><i class="bi bi-link-45deg text-primary me-2"></i>Actions & Timelines</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Redirect URL</label>
                            <input type="url" name="redirect_url" class="form-control" value="{{ old('redirect_url', $ad->redirect_url) }}" maxlength="500" placeholder="https://example.com/target">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Button Text</label>
                            <input type="text" name="button_text" class="form-control" value="{{ old('button_text', $ad->button_text) }}" maxlength="100" placeholder="e.g. Learn More">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Starts At</label>
                            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($ad->starts_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ends At</label>
                            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', optional($ad->ends_at)->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-image text-primary me-2"></i>Creative Asset</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Upload Image</label>
                            <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp">
                            @if($ad->image_url)
                                <div class="mt-3 p-2 border rounded bg-light d-inline-block">
                                    <img src="{{ $ad->image_url }}" alt="Ad image" class="img-thumbnail border-0" style="max-height:100px; width:auto; object-fit:contain;">
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('basic-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-circle me-1"></i>Save Advertisement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        const tabEl = document.getElementById(tabId);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
</script>
