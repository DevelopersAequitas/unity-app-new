@csrf

@push('styles')
<style>
    #circularTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #circularTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #circularTabs .nav-link.active {
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
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="circularTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="basic-tab" data-bs-toggle="pill" data-bs-target="#basic-section" type="button" role="tab" aria-controls="basic-section" aria-selected="true">
                    <i class="bi bi-info-circle me-1"></i>1. Basic Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="content-tab" data-bs-toggle="pill" data-bs-target="#content-section" type="button" role="tab" aria-controls="content-section" aria-selected="false">
                    <i class="bi bi-file-earmark-richtext me-1"></i>2. Content & Media
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="audience-tab" data-bs-toggle="pill" data-bs-target="#audience-section" type="button" role="tab" aria-controls="audience-section" aria-selected="false">
                    <i class="bi bi-people me-1"></i>3. Audience & Settings
                </button>
            </li>
        </ul>

        <div class="p-4">
            <div class="tab-content" id="circularTabsContent">
                <!-- Tab 1: Basic Info -->
                <div class="tab-pane fade show active" id="basic-section" role="tabpanel" aria-labelledby="basic-tab">
                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Basic Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Circular Title *</label>
                            <input name="title" class="form-control" value="{{ old('title', $circular->title) }}" required placeholder="e.g. Important Platform Upgrades">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Circular Category *</label>
                            <select name="category" class="form-select js-no-searchable-select" required>
                                @foreach($categories as $item)
                                    <option value="{{ $item }}" @selected(old('category', $circular->category)===$item)>
                                        {{ ucfirst(str_replace('_',' ',$item)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Priority *</label>
                            <select name="priority" class="form-select js-no-searchable-select" required>
                                @foreach($priorities as $item)
                                    <option value="{{ $item }}" @selected(old('priority', $circular->priority ?? 'normal')===$item)>
                                        {{ ucfirst($item) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Publish Date *</label>
                            <input type="datetime-local" name="publish_date" class="form-control" value="{{ old('publish_date', optional($circular->publish_date)->format('Y-m-d\TH:i')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expiry Date</label>
                            <input type="datetime-local" name="expiry_date" class="form-control" value="{{ old('expiry_date', optional($circular->expiry_date)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Short Summary</label>
                            <textarea name="summary" class="form-control" maxlength="500" rows="3" placeholder="Provide a brief summary of this circular (max 500 chars)...">{{ old('summary', $circular->summary) }}</textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <div></div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('content-tab')">
                                Next: Content & Media <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Content & Media -->
                <div class="tab-pane fade" id="content-section" role="tabpanel" aria-labelledby="content-tab">
                    <h5 class="form-section-title"><i class="bi bi-images text-primary me-2"></i>Media & Attachments</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Featured Image</label>
                            <input type="hidden" name="featured_image_file_id" id="featuredImageFileId" value="{{ old('featured_image_file_id') }}">
                            <input type="file" class="form-control js-upload" data-target="featuredImageFileId" accept="image/*">
                            @if($circular->featured_image_url)
                                <div class="mt-2">
                                    <a href="{{ $circular->featured_image_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>View Current Image
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Attachment File</label>
                            <input type="hidden" name="attachment_file_id" id="attachmentFileId" value="{{ old('attachment_file_id') }}">
                            <input type="file" class="form-control js-upload" data-target="attachmentFileId">
                            @if($circular->attachment_url)
                                <div class="mt-2">
                                    <a href="{{ $circular->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>View Current Attachment
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Body & Call to Actions</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Detailed Description</label>
                            <textarea name="content" class="form-control" rows="6" placeholder="Write the complete circular details here...">{{ old('content', $circular->content) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Video Link</label>
                            <input name="video_url" class="form-control" value="{{ old('video_url', $circular->video_url) }}" placeholder="https://youtube.com/watch?v=...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">CTA Label</label>
                            <input name="cta_label" class="form-control" value="{{ old('cta_label', $circular->cta_label) }}" placeholder="e.g. Register Now">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">CTA URL</label>
                            <input name="cta_url" class="form-control" value="{{ old('cta_url', $circular->cta_url) }}" placeholder="https://example.com/register">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('basic-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('audience-tab')">
                                Next: Audience & Settings <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Audience & Settings -->
                <div class="tab-pane fade" id="audience-section" role="tabpanel" aria-labelledby="audience-tab">
                    <h5 class="form-section-title"><i class="bi bi-people-fill text-primary me-2"></i>Target Audience</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Audience Type *</label>
                            <select name="audience_type" class="form-select js-no-searchable-select" required>
                                @foreach($audiences as $item)
                                    <option value="{{ $item }}" @selected(old('audience_type', $circular->audience_type ?? 'all_members')===$item)>
                                        {{ ucfirst(str_replace('_',' ',$item)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">City</label>
                            <select name="city_id" class="form-select">
                                <option value="">Select City</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" @selected(old('city_id', $circular->city_id)===(string)$city->id)>
                                        {{ $city->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Circle</label>
                            <select name="circle_id" class="form-select">
                                <option value="">Select Circle</option>
                                @foreach($circles as $circle)
                                    <option value="{{ $circle->id }}" @selected(old('circle_id', $circular->circle_id)===(string)$circle->id)>
                                        {{ $circle->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status *</label>
                            <select name="status" class="form-select js-no-searchable-select" required>
                                @foreach($statuses as $item)
                                    <option value="{{ $item }}" @selected(old('status', $circular->status ?? 'published')===$item)>
                                        {{ ucfirst($item) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-sliders text-primary me-2"></i>Preferences & Alerts</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" value="1" name="send_push_notification" id="send_push_notification" @checked(old('send_push_notification', $circular->send_push_notification ?? true))>
                                <label class="form-check-label fw-semibold text-dark" for="send_push_notification">Send Push Notification</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" value="1" name="allow_comments" id="allow_comments" @checked(old('allow_comments', $circular->allow_comments ?? false))>
                                <label class="form-check-label fw-semibold text-dark" for="allow_comments">Allow Comments</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check border rounded p-3 h-100">
                                <input class="form-check-input ms-0 me-2" type="checkbox" value="1" name="is_pinned" id="is_pinned" @checked(old('is_pinned', $circular->is_pinned ?? false))>
                                <label class="form-check-label fw-semibold text-dark" for="is_pinned">Pin Circular</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('content-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-circle me-1"></i>Save Circular
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

@push('scripts')
<script>
document.querySelectorAll('.js-upload').forEach((input) => {
    input.addEventListener('change', async (event) => {
        const file = event.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        const response = await fetch("{{ route('admin.files.upload') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: fd,
        });
        const payload = await response.json();
        const fileId = payload?.data?.id ?? null;
        if (fileId) {
            document.getElementById(event.target.dataset.target).value = fileId;
        }
    });
});
</script>
@endpush
