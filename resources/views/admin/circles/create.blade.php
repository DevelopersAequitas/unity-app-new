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
    <a href="{{ route('admin.circles.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Circles
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

    $founderId = old('founder_user_id', $defaultFounder?->id);
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

        <form action="{{ route('admin.circles.store') }}" method="POST" class="p-4">
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
                            <select name="founder_user_id" class="form-select" required>
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
                    </div>
                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-primary" onclick="switchTab('details-tab')">
                            Next: Description & Tags <i class="bi bi-arrow-right ms-1"></i>
                        </button>
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
                        <button type="button" class="btn btn-primary" onclick="switchTab('settings-tab')">
                            Next: Leadership & Settings <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Tab 3: Settings & Location -->
                <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                    <h5 class="form-section-title"><i class="bi bi-shield-lock text-primary me-2"></i>Leadership Assignments</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Director</label>
                            <select name="director_user_id" class="form-select">
                                <option value="">Select director</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('director_user_id') === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
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
                            <select name="city_id" class="form-select" required>
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
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Mode</label>
                            <select name="meeting_mode" class="form-select js-no-searchable-select">
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
                            <input type="text" class="form-control bg-light" value="Asia/Kolkata" readonly>
                            <input type="hidden" name="calendar_timezone" value="Asia/Kolkata">
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
</script>
@endpush
