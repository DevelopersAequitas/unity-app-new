@extends('admin.layouts.app')

@section('title', 'Create Circle')

@push('styles')
<style>
    #createCircleTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #createCircleTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #createCircleTabs .nav-link.active {
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

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Create Circle</h4>
        <p class="text-muted small mb-0">Add a new community circle to the platform</p>
    </div>
    <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger mb-4">
        <strong>There were some problems with your input.</strong>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $defaultFounder = $defaultFounder ?? null;
    $allUsers = $allUsers ?? collect();
    $types = $types ?? [];
    $statuses = $statuses ?? [];
    $meetingModes = $meetingModes ?? [];
    $meetingFrequencies = $meetingFrequencies ?? [];
    $circleStages = $circleStages ?? [];
    $countries = $countries ?? ['India'];
    $selectedCountry = $selectedCountry ?? 'India';
    $cities = $cities ?? collect();

    $industryTagsValue = old('industry_tags');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }

    $founderId = old('circle_founder_user_id', $defaultFounder?->id);
@endphp

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="createCircleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="basic-info-tab" data-bs-toggle="pill" data-bs-target="#basic-info" type="button" role="tab" aria-controls="basic-info" aria-selected="true">
                    <i class="bi bi-info-circle me-1"></i>1. Basic Info
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="details-tab" data-bs-toggle="pill" data-bs-target="#details" type="button" role="tab" aria-controls="details" aria-selected="false">
                    <i class="bi bi-file-text me-1"></i>2. Description & Tags
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="settings-tab" data-bs-toggle="pill" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">
                    <i class="bi bi-sliders me-1"></i>3. Leadership & Settings
                </button>
            </li>
        </ul>


        <form id="createCircleForm" action="{{ route('admin.circles.store') }}" method="POST" class="p-4" novalidate>
            @csrf

            <div class="tab-content" id="createCircleTabsContent">
                <!-- Tab 1: Basic Info -->
                <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="basic-info-tab">
                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Basic Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Circle Founder</label>
                            <select name="circle_founder_user_id" class="form-select" required>
                                <option value="">Select a member</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) $founderId === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select js-no-searchable-select" required>
                                <option value="" disabled @selected(old('type') === null)>Select type</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select js-no-searchable-select">
                                <option value="">Pending (default)</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 mt-4">
                            <label class="form-label fw-semibold">Circle Categories</label>
                            <div class="p-3 border rounded bg-light-subtle">
                                @include('admin.circles.partials.categories-selector')
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Cover Image</label>
                            <input type="hidden" name="cover_file_id" id="coverFileId" value="{{ old('cover_file_id') }}">
                            
                            <div id="coverPreviewBlock" class="mb-3 d-none align-items-center gap-3 border rounded-3 p-3 bg-light">
                                <img id="coverPreviewImage" src="#" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 90px; max-width: 160px; object-fit: cover;">
                                <div>
                                    <div class="d-flex gap-2 mb-1">
                                        <a id="coverPreviewLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <button type="button" id="coverRemoveBtn" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                    <div class="text-muted small">File ID: <span id="coverFileIdLabel">None</span></div>
                                </div>
                            </div>

                            <input type="file" class="form-control" id="coverFileInput" accept="image/*">
                            <div class="form-text" id="coverUploadStatus">Upload a cover image file up to 10MB.</div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Circle Image</label>
                            <input type="hidden" name="circle_image_file_id" id="circleImageFileId" value="{{ old('circle_image_file_id') }}">
                            
                            <div id="circleImagePreviewBlock" class="mb-3 d-none align-items-center gap-3 border rounded-3 p-3 bg-light">
                                <img id="circleImagePreviewImage" src="#" alt="Circle image preview" class="rounded border shadow-sm" style="max-height: 90px; max-width: 160px; object-fit: cover;">
                                <div>
                                    <div class="d-flex gap-2 mb-1">
                                        <a id="circleImagePreviewLink" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <button type="button" id="circleImageRemoveBtn" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                    </div>
                                    <div class="text-muted small">File ID: <span id="circleImageFileIdLabel">None</span></div>
                                </div>
                            </div>

                            <input type="file" class="form-control" id="circleImageFileInput" accept="image/*">
                            <div class="form-text" id="circleImageUploadStatus">Upload a circle image file up to 10MB.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <div></div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('details-tab')">
                                Next: Description & Tags <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Description & Tags -->
                <div class="tab-pane fade" id="details" role="tabpanel" aria-labelledby="details-tab">
                    <h5 class="form-section-title"><i class="bi bi-justify-left text-primary me-2"></i>Description & Metadata</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide a brief description of the circle...">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Purpose</label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="What is the key purpose or mission of this circle?">{{ old('purpose') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Announcement</label>
                            <textarea name="announcement" class="form-control" rows="3" placeholder="Active announcements for members...">{{ old('announcement') }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Industry Tags</label>
                            <input type="text" name="industry_tags" class="form-control" value="{{ $industryTagsValue }}" placeholder="e.g. Finance, SaaS, Retail, Healthcare">
                            <div class="form-text">Separate tags with commas.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('basic-info-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('settings-tab')">
                                Next: Leadership & Settings <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Settings & Location -->
                <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                    <h5 class="form-section-title"><i class="bi bi-shield-lock text-primary me-2"></i>Leadership Assignments</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Director</label>
                            <select name="circle_director_user_id" class="form-select">
                                <option value="">Select director</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('circle_director_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Industry Director</label>
                            <select name="industry_director_user_id" class="form-select">
                                <option value="">Select industry director</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('industry_director_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">DED</label>
                            <select name="ded_user_id" class="form-select">
                                <option value="">Select DED</option>
                                @foreach ($allUsers as $user)
                                    @php
                                        $label = trim((string) ($user->display_name ?? ''));
                                        if ($label === '') {
                                            $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                        }
                                        if ($label === '') {
                                            $label = (string) ($user->email ?? 'User');
                                        }
                                    @endphp
                                    <option value="{{ $user->id }}" @selected((string) old('ded_user_id') === (string) $user->id)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">EED</label>
                            <select name="eed_user_id" class="form-select">
                                <option value="">Select EED</option>
                                @foreach ($allUsers as $user)
                                    @php
                                        $label = trim((string) ($user->display_name ?? ''));
                                        if ($label === '') {
                                            $label = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
                                        }
                                        if ($label === '') {
                                            $label = (string) ($user->email ?? 'User');
                                        }
                                    @endphp
                                    <option value="{{ $user->id }}" @selected((string) old('eed_user_id') === (string) $user->id)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Committee Leadership -->
                    <h6 class="fw-bold text-secondary mt-3 mb-2">Committee Leadership</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Business Growth Committee Chair</label>
                            <select name="business_growth_committee_chair_user_id" class="form-select">
                                <option value="">Select Business Growth Committee Chair</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('business_growth_committee_chair_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Growth Committee Chair</label>
                            <select name="membership_growth_committee_chair_user_id" class="form-select">
                                <option value="">Select Membership Growth Committee Chair</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('membership_growth_committee_chair_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Events & Impacts Committee Chair</label>
                            <select name="events_impacts_committee_chair_user_id" class="form-select">
                                <option value="">Select Events & Impacts Committee Chair</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('events_impacts_committee_chair_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-geo-alt text-primary me-2"></i>Physical Location</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Country</label>
                            <select name="country" id="countrySelect" class="form-select js-no-searchable-select" required>
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}" @selected(old('country', $selectedCountry) === $country)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">City</label>
                            <select name="city_id" id="citySelect" class="form-select" required>
                                <option value="" disabled @selected(old('city_id') === null)>Select city</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}" @selected((string) old('city_id') === (string) $city->id)>
                                        {{ $city->name }}{{ !empty($city->state) ? ', ' . $city->state : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Derived Country</label>
                            <input type="text" class="form-control bg-light" value="{{ old('country', $selectedCountry) }}" readonly>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-sliders text-primary me-2"></i>Meeting Settings</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Mode</label>
                            <select name="meeting_mode" id="meetingModeSelect" class="form-select js-no-searchable-select">
                                <option value="">Select mode</option>
                                @foreach ($meetingModes as $mode)
                                    <option value="{{ $mode }}" @selected(old('meeting_mode') === $mode)>{{ ucfirst($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Frequency</label>
                            <select name="meeting_frequency" class="form-select js-no-searchable-select">
                                <option value="">Select frequency</option>
                                @foreach ($meetingFrequencies as $frequency)
                                    <option value="{{ $frequency }}" @selected(old('meeting_frequency') === $frequency)>{{ ucfirst($frequency) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Stage</label>
                            <select name="circle_stage" class="form-select js-no-searchable-select">
                                <option value="">Select stage</option>
                                @foreach ($circleStages as $stage)
                                    <option value="{{ $stage }}" @selected(old('circle_stage') === $stage)>{{ $stage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Launch Date</label>
                            <input type="date" name="launch_date" class="form-control" value="{{ old('launch_date') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Calendar Timezone</label>
                            <input type="text" class="form-control bg-light" value="{{ config('app.timezone', 'UTC') }}" readonly>
                            <input type="hidden" name="calendar_timezone" value="{{ config('app.timezone', 'UTC') }}">
                        </div>
                    </div>

                    <!-- Dynamic Online Details Section -->
                    <div id="onlineDetailsSection" class="p-3 border rounded-3 bg-light-subtle mb-3 d-none">
                        <h6 class="fw-semibold text-primary mb-3">
                            <i class="bi bi-camera-video me-1"></i>Online Meeting Details
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Online Meeting Link</label>
                                <input type="url" name="meeting_link" class="form-control" value="{{ old('meeting_link') }}" placeholder="https://zoom.us/j/... or Google Meet / Teams link">
                                <div class="form-text">Provide the meeting join URL for circle members.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Meeting Passcode / ID</label>
                                <input type="text" name="meeting_passcode" class="form-control" value="{{ old('meeting_passcode') }}" placeholder="e.g. 123456 or Passcode">
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Offline Details Section -->
                    <div id="offlineDetailsSection" class="p-3 border rounded-3 bg-light-subtle mb-3 d-none">
                        <h6 class="fw-semibold text-success mb-3">
                            <i class="bi bi-geo-alt me-1"></i>Offline / Venue Details
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Physical Meeting Address / Venue</label>
                                <textarea name="meeting_venue" class="form-control" rows="2" placeholder="Enter physical meeting venue name, hall/room number, and street address...">{{ old('meeting_venue') }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Landmark / Instructions</label>
                                <input type="text" name="meeting_landmark" class="form-control" value="{{ old('meeting_landmark') }}" placeholder="e.g. Near City Center Mall, 3rd Floor">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('details-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-danger">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">Create Circle</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function switchTab(tabId) {
        const tabEl = document.getElementById(tabId);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    document.getElementById('countrySelect')?.addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('country', event.target.value);
        window.location.href = url.toString();
    });

    @include('admin.circles.partials.categories-selector-script')

    function initCitySelect2() {
        if (window.jQuery && window.jQuery.fn.select2) {
            const $citySelect = $('#citySelect');
            if ($citySelect.hasClass('select2-hidden-accessible')) {
                $citySelect.select2('destroy');
            }
            $citySelect.select2({
                placeholder: "Select city",
                allowClear: true,
                width: '100%'
            });
        }
    }

    // Initialize on page load
    initCitySelect2();

    // Toggle meeting details sections based on selected mode
    function toggleMeetingDetails() {
        const modeSelect = document.getElementById('meetingModeSelect');
        if (!modeSelect) return;
        const mode = (modeSelect.value || '').toLowerCase();
        const onlineSec = document.getElementById('onlineDetailsSection');
        const offlineSec = document.getElementById('offlineDetailsSection');

        if (!onlineSec || !offlineSec) return;

        if (mode === 'online') {
            onlineSec.classList.remove('d-none');
            offlineSec.classList.add('d-none');
        } else if (mode === 'offline') {
            onlineSec.classList.add('d-none');
            offlineSec.classList.remove('d-none');
        } else if (mode === 'hybrid') {
            onlineSec.classList.remove('d-none');
            offlineSec.classList.remove('d-none');
        } else {
            onlineSec.classList.add('d-none');
            offlineSec.classList.add('d-none');
        }
    }

    document.getElementById('meetingModeSelect')?.addEventListener('change', toggleMeetingDetails);
    toggleMeetingDetails();

    // Re-initialize when settings tab is shown to fix Select2 width calculations inside hidden tabs
    document.getElementById('settings-tab')?.addEventListener('shown.bs.tab', function () {
        initCitySelect2();
        toggleMeetingDetails();
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const uploadUrl = @json(route('admin.files.upload'));

    // Cover File Upload
    document.getElementById('coverFileInput')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const statusEl = document.getElementById('coverUploadStatus');
        statusEl.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
            });

            if (!response.ok) {
                statusEl.textContent = 'Upload failed. Please try again.';
                return;
            }

            const payload = await response.json();
            const fileId = payload?.data?.id ?? payload?.data?.[0]?.id;
            if (!fileId) {
                statusEl.textContent = 'Upload failed. Missing file id.';
                return;
            }

            document.getElementById('coverFileId').value = fileId;
            const previewLink = document.getElementById('coverPreviewLink');
            const previewImage = document.getElementById('coverPreviewImage');
            const previewBlock = document.getElementById('coverPreviewBlock');
            const fileIdLabel = document.getElementById('coverFileIdLabel');
            
            if (previewLink) {
                previewLink.href = `/api/v1/files/${fileId}`;
            }
            if (previewImage) {
                previewImage.src = `/api/v1/files/${fileId}`;
            }
            if (fileIdLabel) {
                fileIdLabel.textContent = fileId;
            }
            if (previewBlock) {
                previewBlock.classList.remove('d-none');
                previewBlock.classList.add('d-flex');
            }
            statusEl.textContent = 'Upload successful.';
        } catch (error) {
            statusEl.textContent = 'Upload failed. Please try again.';
        }
    });

    // Cover File Remove
    document.getElementById('coverRemoveBtn')?.addEventListener('click', () => {
        document.getElementById('coverFileId').value = '';
        const previewBlock = document.getElementById('coverPreviewBlock');
        if (previewBlock) {
            previewBlock.classList.remove('d-flex');
            previewBlock.classList.add('d-none');
        }
        const fileInput = document.getElementById('coverFileInput');
        if (fileInput) fileInput.value = '';
        const statusEl = document.getElementById('coverUploadStatus');
        if (statusEl) statusEl.textContent = 'Image removed.';
    });

    // Circle Image File Upload
    document.getElementById('circleImageFileInput')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const statusEl = document.getElementById('circleImageUploadStatus');
        statusEl.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
            });

            if (!response.ok) {
                statusEl.textContent = 'Upload failed. Please try again.';
                return;
            }

            const payload = await response.json();
            const fileId = payload?.data?.id ?? payload?.data?.[0]?.id;
            if (!fileId) {
                statusEl.textContent = 'Upload failed. Missing file id.';
                return;
            }

            document.getElementById('circleImageFileId').value = fileId;
            const previewLink = document.getElementById('circleImagePreviewLink');
            const previewImage = document.getElementById('circleImagePreviewImage');
            const previewBlock = document.getElementById('circleImagePreviewBlock');
            const fileIdLabel = document.getElementById('circleImageFileIdLabel');
            
            if (previewLink) {
                previewLink.href = `/api/v1/files/${fileId}`;
            }
            if (previewImage) {
                previewImage.src = `/api/v1/files/${fileId}`;
            }
            if (fileIdLabel) {
                fileIdLabel.textContent = fileId;
            }
            if (previewBlock) {
                previewBlock.classList.remove('d-none');
                previewBlock.classList.add('d-flex');
            }
            statusEl.textContent = 'Upload successful.';
        } catch (error) {
            statusEl.textContent = 'Upload failed. Please try again.';
        }
    });

    // Circle Image File Remove
    document.getElementById('circleImageRemoveBtn')?.addEventListener('click', () => {
        document.getElementById('circleImageFileId').value = '';
        const previewBlock = document.getElementById('circleImagePreviewBlock');
        if (previewBlock) {
            previewBlock.classList.remove('d-flex');
            previewBlock.classList.add('d-none');
        }
        const fileInput = document.getElementById('circleImageFileInput');
        if (fileInput) fileInput.value = '';
        const statusEl = document.getElementById('circleImageUploadStatus');
        if (statusEl) statusEl.textContent = 'Image removed.';
    });
</script>
@endpush
