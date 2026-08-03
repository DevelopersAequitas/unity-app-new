@extends('admin.layouts.app')

@section('title', 'Edit Circle')

@push('styles')
<style>
    #editCircleTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #editCircleTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #editCircleTabs .nav-link.active {
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
    .meeting-row {
        background-color: var(--bg-muted);
        border: 1px solid var(--border) !important;
        transition: all var(--duration-fast) var(--ease-smooth);
    }
    .meeting-row:hover {
        border-color: var(--primary-light) !important;
        box-shadow: var(--shadow-sm);
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-circle-fill text-primary me-2"></i>Edit Circle</h4>
        <p class="text-muted small mb-0">Update circle details, settings, and meeting schedule</p>
    </div>
    <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
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
    $industryTagsValue = old('industry_tags', $circle->industry_tags ? implode(', ', $circle->industry_tags) : '');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }
    $founderId = old('circle_founder_user_id', $defaultFounder?->id);
    $calendar = is_array($circle->calendar) ? $circle->calendar : [];
    $meetingScheduleFrequency = old('meeting_schedule_frequency');
    $meetingScheduleTimes = old('meeting_schedule_default_meet_time');
    $meetingScheduleDays = old('meeting_schedule_day_of_week');

    $calendarMeetings = [];

    if (is_array($meetingScheduleFrequency) || is_array($meetingScheduleTimes) || is_array($meetingScheduleDays)) {
        $max = max(count((array) $meetingScheduleFrequency), count((array) $meetingScheduleTimes), count((array) $meetingScheduleDays));
        for ($i = 0; $i < $max; $i++) {
            $calendarMeetings[] = [
                'frequency' => (string) (($meetingScheduleFrequency[$i] ?? '') ?: ''),
                'default_meet_time' => (string) (($meetingScheduleTimes[$i] ?? '') ?: ''),
                'day_of_week' => (string) (($meetingScheduleDays[$i] ?? '') ?: ''),
            ];
        }
    } else {
        $calendarMeetings = is_array(data_get($calendar, 'meeting_schedule')) && data_get($calendar, 'meeting_schedule') !== []
            ? array_values(data_get($calendar, 'meeting_schedule'))
            : [[
                'frequency' => '',
                'default_meet_time' => '',
                'day_of_week' => '',
            ]];
    }
@endphp

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="editCircleTabs" role="tablist">
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
                <button class="nav-link py-2 px-3 fw-semibold" id="leadership-tab" data-bs-toggle="pill" data-bs-target="#leadership" type="button" role="tab" aria-controls="leadership" aria-selected="false">
                    <i class="bi bi-people me-1"></i>3. Leadership & Location
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="schedule-tab" data-bs-toggle="pill" data-bs-target="#schedule" type="button" role="tab" aria-controls="schedule" aria-selected="false">
                    <i class="bi bi-calendar-event me-1"></i>4. Meeting Settings
                </button>
            </li>
        </ul>


        <form id="editCircleForm" action="{{ route('admin.circles.update', $circle) }}" method="POST" class="p-4" novalidate>
            @csrf
            @method('PUT')

            <div class="tab-content" id="editCircleTabsContent">
                <!-- Tab 1: Basic Info -->
                <div class="tab-pane fade show active" id="basic-info" role="tabpanel" aria-labelledby="basic-info-tab">
                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Basic Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $circle->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Circle Founder</label>
                            <select name="circle_founder_user_id" class="form-select" required>
                                <option value="">Select a member</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) $founderId === (string) $user->id)>{{ $user->adminNameCompanyCityLabel() }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Defaults to the logged-in admin user.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select js-no-searchable-select" required>
                                <option value="" disabled>Select type</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('type', $circle->type) === $type)>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select js-no-searchable-select" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $circle->status) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Package</label>
                            <select name="circle_package" class="form-select js-no-searchable-select">
                                <option value="">Select package</option>
                                @foreach ($circlePackages as $package)
                                    @php
                                        $packageValue = $package['addon_code'] ?: $package['addon_id'];
                                    @endphp
                                    <option value="{{ $packageValue }}" @selected(old('circle_package', $circle->zoho_addon_code ?: $circle->zoho_addon_id) === $packageValue)>
                                        {{ $package['name'] }} ({{ $package['addon_code'] }}) - {{ $package['amount'] }} {{ $package['currency_code'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 mt-4">
                            <label class="form-label fw-semibold">Cover Image</label>
                            <input type="hidden" name="cover_file_id" id="coverFileId" value="{{ old('cover_file_id', $circle->cover_file_id) }}">
                            
                            <div id="coverPreviewBlock" class="mb-3 {{ $circle->cover_file_id ? 'd-flex' : 'd-none' }} align-items-center gap-3 border rounded-3 p-3 bg-light">
                                <img id="coverPreviewImage" src="{{ $circle->cover_file_id ? url('/api/v1/files/' . $circle->cover_file_id) : '#' }}" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 90px; max-width: 160px; object-fit: cover;">
                                <div>
                                    <a id="coverPreviewLink" href="{{ $circle->cover_file_id ? url('/api/v1/files/' . $circle->cover_file_id) : '#' }}" target="_blank" class="btn btn-sm btn-outline-primary mb-1">
                                        <i class="bi bi-eye me-1"></i>View Full Image
                                    </a>
                                    <div class="text-muted small">File ID: <span id="coverFileIdLabel">{{ $circle->cover_file_id ?: 'None' }}</span></div>
                                </div>
                            </div>

                            <input type="file" class="form-control" id="coverFileInput" accept="image/*">
                            <div class="form-text" id="coverUploadStatus">Upload an image file up to 10MB.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="{{ route('admin.circles.show', $circle) }}" class="btn btn-outline-secondary">Cancel</a>
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
                    <h5 class="form-section-title"><i class="bi bi-justify-left text-primary me-2"></i>Description & Categorization</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide a brief description of the circle...">{{ old('description', $circle->description) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Purpose</label>
                            <textarea name="purpose" class="form-control" rows="3" placeholder="What is the key purpose or mission of this circle?">{{ old('purpose', $circle->purpose) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Announcement</label>
                            <textarea name="announcement" class="form-control" rows="3" placeholder="Active announcements for members...">{{ old('announcement', $circle->announcement) }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Industry Tags</label>
                            <input type="text" name="industry_tags" class="form-control" value="{{ $industryTagsValue }}" placeholder="e.g. Finance, SaaS, Retail, Healthcare">
                            <div class="form-text">Separate tags with commas.</div>
                        </div>
                        <div class="col-md-12 mt-3">
                            <label class="form-label fw-semibold">Circle Categories</label>
                            <div class="p-3 border rounded bg-light-subtle">
                                @include('admin.circles.partials.categories-selector')
                            </div>
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
                            <button type="button" class="btn btn-primary" onclick="switchTab('leadership-tab')">
                                Next: Leadership & Location <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Leadership & Location -->
                <div class="tab-pane fade" id="leadership" role="tabpanel" aria-labelledby="leadership-tab">
                    <h5 class="form-section-title"><i class="bi bi-shield-lock text-primary me-2"></i>Circle Leadership</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Circle Director</label>
                            <select name="circle_director_user_id" class="form-select">
                                <option value="">Select director</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('circle_director_user_id', $circle->circle_director_user_id) === $user->id)>{{ $user->adminNameCompanyCityLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Industry Director</label>
                            <select name="industry_director_user_id" class="form-select">
                                <option value="">Select industry director</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('industry_director_user_id', $circle->industry_director_user_id) === $user->id)>{{ $user->adminNameCompanyCityLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">DED</label>
                            <select name="ded_user_id" class="form-select">
                                <option value="">Select DED</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('ded_user_id', $circle->ded_user_id) === $user->id)>{{ $user->adminDisplayInlineLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">EED</label>
                            <select name="eed_user_id" class="form-select">
                                <option value="">Select EED</option>
                                @foreach ($allUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('eed_user_id', $circle->eed_user_id) === $user->id)>{{ $user->adminDisplayInlineLabel() }}</option>
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
                                    <option value="{{ $user->id }}" @selected((string) old('business_growth_committee_chair_user_id', data_get($circle->calendar, 'leadership.business_growth_committee_chair_user_id') ?? data_get($circle->calendar, 'leadership_team.business_growth_committee_chair.id')) === (string) $user->id)>
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
                                    <option value="{{ $user->id }}" @selected((string) old('membership_growth_committee_chair_user_id', data_get($circle->calendar, 'leadership.membership_growth_committee_chair_user_id') ?? data_get($circle->calendar, 'leadership_team.membership_growth_committee_chair.id')) === (string) $user->id)>
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
                                    <option value="{{ $user->id }}" @selected((string) old('events_impacts_committee_chair_user_id', data_get($circle->calendar, 'leadership.events_impacts_committee_chair_user_id') ?? data_get($circle->calendar, 'leadership_team.events_impacts_committee_chair.id')) === (string) $user->id)>
                                        {{ $user->adminNameCompanyCityLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-geo-alt text-primary me-2"></i>Geographic Location</h5>
                    <div class="row g-3">
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
                                <option value="" disabled>Select city</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}" @selected(old('city_id', $circle->city_id) == $city->id)>
                                        {{ $city->name }}{{ $city->state ? ', ' . $city->state : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Derived Country</label>
                            <input type="text" class="form-control bg-light" value="{{ old('country', $selectedCountry) }}" readonly>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('details-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('schedule-tab')">
                                Next: Meeting Settings <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Meeting Settings & Schedule -->
                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                    <h5 class="form-section-title"><i class="bi bi-sliders text-primary me-2"></i>Meeting Configuration</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Mode</label>
                            <select name="meeting_mode" class="form-select js-no-searchable-select">
                                <option value="">Select mode</option>
                                @foreach ($meetingModes as $mode)
                                    <option value="{{ $mode }}" @selected(old('meeting_mode', $circle->meeting_mode) === $mode)>{{ ucfirst($mode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Meeting Frequency</label>
                            <select name="meeting_frequency" class="form-select js-no-searchable-select">
                                <option value="">Select frequency</option>
                                @foreach ($meetingFrequencies as $frequency)
                                    <option value="{{ $frequency }}" @selected(old('meeting_frequency', $circle->meeting_frequency) === $frequency)>{{ ucfirst($frequency) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Stage</label>
                            <select name="circle_stage" class="form-select js-no-searchable-select">
                                <option value="">Select stage</option>
                                @foreach ($circleStages as $stage)
                                    <option value="{{ $stage }}" @selected(old('circle_stage', $circle->circle_stage) === $stage)>{{ $stage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Launch Date</label>
                            <input type="date" name="launch_date" class="form-control" value="{{ old('launch_date', $circle->launch_date) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Calendar Timezone</label>
                            <input type="text" class="form-control bg-light" value="{{ config('app.timezone', 'UTC') }}" readonly>
                            <input type="hidden" name="calendar_timezone" value="{{ config('app.timezone', 'UTC') }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Weekly/Monthly Meeting Schedule</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" id="addMeetingBtn">
                            <i class="bi bi-plus-lg me-1"></i>Add Meeting
                        </button>
                    </div>

                    <div id="meetingRows">
                        @php
                            $meetings = $calendarMeetings;
                        @endphp

                        @forelse($meetings as $rowIndex => $meeting)
                            @php
                                $rowFrequency = strtolower((string) data_get($meeting, 'frequency', ''));
                                $rowDay = strtolower((string) data_get($meeting, 'day_of_week', ''));
                                $rowTime = (string) data_get($meeting, 'default_meet_time', '');
                            @endphp

                            <div class="border rounded-3 p-3 meeting-row mb-3" data-index="{{ $rowIndex }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Frequency</label>
                                        <select name="meeting_schedule_frequency[{{ $rowIndex }}]" class="form-select js-meeting-frequency">
                                            <option value="">Select Frequency</option>
                                            <option value="weekly" {{ $rowFrequency === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                            <option value="monthly" {{ $rowFrequency === 'monthly' ? 'selected' : '' }}>Monthly</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 js-meeting-day-wrap">
                                        <label class="form-label small fw-semibold">Day of Week</label>
                                        <select name="meeting_schedule_day_of_week[{{ $rowIndex }}]" class="form-select js-meeting-day">
                                            <option value="">Select Day</option>
                                            <option value="monday" {{ $rowDay === 'monday' ? 'selected' : '' }}>Monday</option>
                                            <option value="tuesday" {{ $rowDay === 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                                            <option value="wednesday" {{ $rowDay === 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                                            <option value="thursday" {{ $rowDay === 'thursday' ? 'selected' : '' }}>Thursday</option>
                                            <option value="friday" {{ $rowDay === 'friday' ? 'selected' : '' }}>Friday</option>
                                            <option value="saturday" {{ $rowDay === 'saturday' ? 'selected' : '' }}>Saturday</option>
                                            <option value="sunday" {{ $rowDay === 'sunday' ? 'selected' : '' }}>Sunday</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 js-meeting-time-wrap">
                                        <label class="form-label small fw-semibold">Default Meet Time</label>
                                        <input
                                            type="time"
                                            name="meeting_schedule_default_meet_time[{{ $rowIndex }}]"
                                            class="form-control js-meeting-time"
                                            value="{{ $rowTime }}"
                                        >
                                    </div>

                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger w-100 js-remove-meeting" {{ $rowIndex === 0 && count($meetings) === 1 ? 'disabled' : '' }}>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted">Preview: <span class="js-meeting-preview fw-semibold">—</span></div>
                            </div>
                        @empty
                            <div class="border rounded-3 p-3 meeting-row mb-3" data-index="0">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Frequency</label>
                                        <select name="meeting_schedule_frequency[0]" class="form-select js-meeting-frequency">
                                            <option value="">Select Frequency</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 js-meeting-day-wrap">
                                        <label class="form-label small fw-semibold">Day of Week</label>
                                        <select name="meeting_schedule_day_of_week[0]" class="form-select js-meeting-day">
                                            <option value="">Select Day</option>
                                            <option value="monday">Monday</option>
                                            <option value="tuesday">Tuesday</option>
                                            <option value="wednesday">Wednesday</option>
                                            <option value="thursday">Thursday</option>
                                            <option value="friday">Friday</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 js-meeting-time-wrap">
                                        <label class="form-label small fw-semibold">Default Meet Time</label>
                                        <input
                                            type="time"
                                            name="meeting_schedule_default_meet_time[0]"
                                            class="form-control js-meeting-time"
                                            value=""
                                        >
                                    </div>

                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger w-100 js-remove-meeting" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted">Preview: <span class="js-meeting-preview fw-semibold">—</span></div>
                            </div>
                        @endforelse
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('leadership-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-check-circle me-1"></i>Save Changes
                        </button>
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

    // Re-initialize when leadership tab is shown to fix Select2 width calculations inside hidden tabs
    document.getElementById('leadership-tab')?.addEventListener('shown.bs.tab', function () {
        initCitySelect2();
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const uploadUrl = @json(route('admin.files.upload'));

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

    const meetingRows = document.getElementById('meetingRows');
    const addMeetingBtn = document.getElementById('addMeetingBtn');

    const title = (value) => value ? value.charAt(0).toUpperCase() + value.slice(1) : '';

    const updateMeetingRowState = (row) => {
        const frequency = row.querySelector('.js-meeting-frequency')?.value || '';
        const day = row.querySelector('.js-meeting-day')?.value || '';
        const time = row.querySelector('.js-meeting-time')?.value || '';
        row.querySelector('.js-meeting-time-wrap')?.classList.toggle('d-none', !frequency);
        row.querySelector('.js-meeting-day-wrap')?.classList.toggle('d-none', !frequency);

        let preview = '—';
        if (frequency && day && time) {
            preview = `${title(day)} at ${time} (${title(frequency)})`;
        }

        const previewEl = row.querySelector('.js-meeting-preview');
        if (previewEl) {
            previewEl.textContent = preview;
        }
    };

    const bindMeetingRow = (row) => {
        row.querySelectorAll('.js-meeting-frequency, .js-meeting-day, .js-meeting-time')
            .forEach((input) => input.addEventListener('change', () => updateMeetingRowState(row)));

        row.querySelector('.js-meeting-time')?.addEventListener('input', () => updateMeetingRowState(row));
        row.querySelector('.js-remove-meeting')?.addEventListener('click', () => {
            if (meetingRows.querySelectorAll('.meeting-row').length > 1) {
                row.remove();
                reindexMeetingRows();
            }
        });

        updateMeetingRowState(row);
    };

    const reindexMeetingRows = () => {
        const rows = meetingRows.querySelectorAll('.meeting-row');
        rows.forEach((row, index) => {
            row.dataset.index = String(index);
            row.querySelectorAll('select, input').forEach((el) => {
                const name = el.getAttribute('name');
                if (!name) return;
                el.setAttribute('name', name.replace(/\[\d+\]/, `[${index}]`));
            });

            const removeBtn = row.querySelector('.js-remove-meeting');
            if (removeBtn) {
                removeBtn.disabled = index === 0 && rows.length === 1;
            }
        });
    };

    const createMeetingRow = () => {
        const index = meetingRows.querySelectorAll('.meeting-row').length;
        const template = meetingRows.querySelector('.meeting-row');
        if (!template) return;

        const clone = template.cloneNode(true);
        clone.dataset.index = String(index);
        clone.querySelectorAll('select, input').forEach((el) => {
            const name = el.getAttribute('name');
            if (!name) return;
            el.setAttribute('name', name);
            if (el.tagName === 'SELECT') el.value = '';
            if (el.tagName === 'INPUT' && el.type !== 'hidden') el.value = '';
        });
        clone.querySelector('.js-meeting-preview').textContent = '—';
        meetingRows.appendChild(clone);
        bindMeetingRow(clone);
        reindexMeetingRows();
    };

    meetingRows?.querySelectorAll('.meeting-row').forEach((row) => bindMeetingRow(row));
    addMeetingBtn?.addEventListener('click', createMeetingRow);
</script>
@endpush
