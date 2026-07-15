@extends('admin.layouts.app')

@section('title', isset($event) ? 'Edit Event' : 'Create Event')

@push('styles')
<style>
    #eventTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #eventTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #eventTabs .nav-link.active {
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
    .event-form-card { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
    .event-form-card__header { background: #fff; border-bottom: 1px solid #eef2f7; padding: .9rem 1rem; color: #111827; }
    .event-form-card__body { padding: 1rem; }
    .event-form-card .form-label { margin-bottom: .35rem; font-weight: 600; color: #374151; }
    .event-form-card .form-control,
    .event-form-card .form-select,
    .event-form-card .input-group-text { min-height: 42px; }
    .event-form-card textarea.form-control { min-height: 110px; }
    .event-form-card .invalid-feedback,
    .event-form-card .text-danger.small { font-size: .8125rem; }
    .event-form-card .repeat-row { padding: .75rem; border: 1px solid #eef2f7; border-radius: 12px; background: #fff; }
    .event-form-card .btn-outline-danger { border-color: #dc3545; color: #dc3545; }
    .event-form-card .btn-outline-primary { border-color: #0d6efd; color: #0d6efd; }

    .create-event-form .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .create-event-form .add-row-btn {
        min-width: 120px;
        height: 38px;
        padding: 0 14px;
        white-space: nowrap !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        line-height: 1;
    }
    .create-event-form .add-row-btn.btn-outline-primary {
        color: #0d6efd !important;
        background-color: #fff !important;
        border-color: #0d6efd !important;
    }
    .create-event-form .dynamic-row {
        display: grid !important;
        gap: 8px;
        align-items: end;
        margin-left: 0;
        margin-right: 0;
    }
    .create-event-form .dynamic-row > * {
        width: auto !important;
        max-width: none !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .create-event-form .agenda-row { grid-template-columns: 180px minmax(0, 1fr) 110px; }
    .create-event-form .speaker-row { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 1.2fr) 80px minmax(0, 1.3fr) 110px; }
    .create-event-form .gain-row { grid-template-columns: minmax(0, 1fr) 110px; }
    .create-event-form .dynamic-row input,
    .create-event-form .dynamic-row select { height: 42px; }
    .create-event-form .remove-row-btn {
        min-width: 110px;
        width: 100%;
        height: 42px;
        padding: 0 16px;
        white-space: nowrap !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
        line-height: 1;
        color: #dc3545 !important;
        background: #fff !important;
        border-color: #dc3545 !important;
    }
    .event-form-card .form-check { padding-left: 2.5rem !important; }
    .event-form-card .form-check-input { margin-left: -1.5rem !important; }
    @media (max-width: 991px) {
        .create-event-form .agenda-row,
        .create-event-form .speaker-row,
        .create-event-form .gain-row { grid-template-columns: 1fr; }
        .create-event-form .remove-row-btn,
        .create-event-form .add-row-btn { width: 100%; min-width: 110px; }
        .create-event-form .section-header { align-items: flex-start; flex-wrap: wrap; }
    }
    @media (max-width: 767.98px) {
        .event-form-card__header .btn { width: 100%; }
        .d-flex.justify-content-end.gap-2.mb-4 { flex-direction: column-reverse; }
        .d-flex.justify-content-end.gap-2.mb-4 .btn { width: 100%; }
    }
    .multi-select-dropdown { position: relative; width: 100%; }
    .multi-select-toggle { min-height: 38px; display: flex; align-items: center; justify-content: space-between; gap: .5rem; overflow: hidden; }
    .multi-select-placeholder { display: flex; align-items: center; gap: .35rem; flex-wrap: nowrap; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .multi-select-caret { color: #6c757d; flex: 0 0 auto; }
    .multi-select-menu { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 1055; width: 100%; padding: .75rem; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .12); }
    .circle-options { max-height: 260px; overflow-y: auto; }
    .multi-select-option { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .45rem .5rem; border-radius: .375rem; cursor: pointer; font-size: .925rem; }
    .multi-select-option:hover { background: #f8f9fa; }
    .multi-select-option input { flex: 0 0 auto; }
    .multi-select-chip { display: inline-flex; align-items: center; max-width: 160px; padding: .15rem .45rem; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: .78rem; font-weight: 600; }
    .multi-select-chip-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .multi-select-chip-remove { border: 0; background: transparent; color: inherit; padding: 0 0 0 .35rem; line-height: 1; font-weight: 700; }
</style>
@endpush

@section('content')
@php
    $event = $event ?? null;
    $eventTypes = [
        'circle_meeting' => 'Circle Meeting',
        'global_event' => 'Global Event',
        'state_event' => 'State Event',
        'public_event' => 'City Event',
    ];
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $weeks = [1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Last'];
    $isEdit = isset($event);
    $metadata = $isEdit ? ($event->metadata ?? []) : [];
    $metadata = is_string($metadata) ? (json_decode($metadata, true) ?: []) : ((array) $metadata);
    $agendaRows = old('agenda', $isEdit ? ($event->agenda ?: []) : []);
    $agendaRows = count($agendaRows ?: []) ? $agendaRows : [['time' => '', 'title' => '']];
    $speakerRows = old('speakers', $isEdit ? ($event->speakers ?: []) : []);
    $speakerRows = count($speakerRows ?: []) ? $speakerRows : [['name' => '', 'designation' => '', 'company' => '', 'initials' => '', 'photo_url' => '']];
    $gainRows = old('what_youll_gain', data_get($metadata, 'what_youll_gain', []));
    $gainRows = count($gainRows ?: []) ? $gainRows : [''];
    $organizer = data_get($metadata, 'organizer', []);
    $selectedCircleIds = collect(old('circle_ids', $isEdit ? $event->circles->pluck('id')->all() : []))->map(fn ($id) => (string) $id)->all();
    $stateOptions = $circles->map(fn ($circle) => $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? null)->filter()->unique()->sort()->values();
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-calendar-event text-primary me-2"></i>{{ $isEdit ? 'Edit Event' : 'Create Event' }}</h4>
        <p class="text-muted small mb-0">Use this guided form to publish an event and generate upcoming occurrences automatically.</p>
    </div>
    <a href="{{ $isEdit ? route('admin.events.show', $event) : route('admin.events.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="eventTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="basic-tab" data-bs-toggle="pill" data-bs-target="#basic-section" type="button" role="tab" aria-controls="basic-section" aria-selected="true">
                    <i class="bi bi-info-circle me-1"></i>1. Basic & Banner
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="schedule-tab" data-bs-toggle="pill" data-bs-target="#schedule-section" type="button" role="tab" aria-controls="schedule-section" aria-selected="false">
                    <i class="bi bi-calendar3 me-1"></i>2. Date, Recurrence & Venue
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="agenda-tab" data-bs-toggle="pill" data-bs-target="#agenda-section" type="button" role="tab" aria-controls="agenda-section" aria-selected="false">
                    <i class="bi bi-list-task me-1"></i>3. Agenda & Speakers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="registration-tab" data-bs-toggle="pill" data-bs-target="#registration-section" type="button" role="tab" aria-controls="registration-section" aria-selected="false">
                    <i class="bi bi-qr-code-scan me-1"></i>4. Registration & Settings
                </button>
            </li>
        </ul>

        <form method="POST" action="{{ $isEdit ? route('admin.events.update', $event->id) : route('admin.events.store') }}" id="eventCreateForm" class="create-event-form p-4" enctype="multipart/form-data">
            @csrf
            @if($isEdit) @method('PUT') @endif
            
            @if($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0 small">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="tab-content" id="eventTabsContent">
                <!-- Tab 1: Basic Info & Banner -->
                <div class="tab-pane fade show active" id="basic-section" role="tabpanel" aria-labelledby="basic-tab">
                    <h5 class="form-section-title"><i class="bi bi-card-text text-primary me-2"></i>Event Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Event Title</label>
                            <input class="form-control @error('title') is-invalid @enderror" name="title" value="{{ old('title', $event->title ?? '') }}" required placeholder="e.g. Winners Circle Weekly Meeting">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Event Type</label>
                            <select class="form-select @error('event_type') is-invalid @enderror js-no-searchable-select" name="event_type" required>
                                @foreach($eventTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('event_type', $event->event_type ?? null)===$value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('event_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Category</label>
                            <input class="form-control @error('event_category') is-invalid @enderror" name="event_category" value="{{ old('event_category', $event->event_category ?? '') }}" placeholder="training, workshop, networking">
                            @error('event_category')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 single-circle-field">
                            <label class="form-label fw-semibold">Circle</label>
                            <select class="form-select" name="circle_id" id="singleCircleSelect">
                                <option value="">No specific circle</option>
                                @foreach($circles as $circle)
                                    <option value="{{ $circle->id }}" @selected(old('circle_id', $event->circle_id ?? null)===$circle->id)>{{ $circle->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 state-event-field d-none">
                            <label class="form-label fw-semibold">State</label>
                            <select class="form-select" name="state_name" id="stateNameSelect">
                                <option value="">Select state</option>
                                @foreach($stateOptions as $state)
                                    <option value="{{ $state }}" @selected(old('state_name', $event->state_name ?? data_get($metadata, 'state')) === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Required for State Event.</div>
                        </div>
                        <div class="col-md-8 multi-circle-field d-none">
                            <label class="form-label fw-semibold">Selected Circles</label>
                            <div class="multi-select-dropdown" id="circleMultiSelect">
                                <button type="button" class="form-control text-start multi-select-toggle" id="circleMultiSelectToggle">
                                    <span class="multi-select-placeholder">Select circles</span>
                                    <span class="multi-select-caret">▾</span>
                                </button>
                                <div class="multi-select-menu d-none" id="circleMultiSelectMenu">
                                    <input type="text" class="form-control mb-2 circle-search" id="circleSearchInput" placeholder="Search circles...">
                                    <div class="circle-options" id="circleOptionsList">
                                        @foreach($circles as $circle)
                                            @php($circleState = $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? '')
                                            <label class="multi-select-option" data-state="{{ $circleState }}" data-label="{{ \Illuminate\Support\Str::lower($circle->name.' '.$circleState) }}">
                                                <input type="checkbox" name="circle_ids[]" value="{{ $circle->id }}" @checked(in_array((string) $circle->id, $selectedCircleIds, true))>
                                                <span>{{ $circle->name }}{{ $circleState ? ' — '.$circleState : '' }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="text-muted small d-none" id="circleNoResults">No circles found.</div>
                                </div>
                            </div>
                            <div class="text-danger small d-none" id="multiCircleError">Please select at least one circle.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Event Mode</label>
                            <select class="form-select js-no-searchable-select" name="mode" id="modeSelect">
                                @foreach(['offline' => 'Offline / Venue', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('mode', $event->mode ?? 'offline')===$value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="What should attendees know about this event?">{{ old('description', $event->description ?? '') }}</textarea>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-image text-primary me-2"></i>Event Banner</h5>
                    <div class="row g-3">
                        @if($isEdit && !empty($event->banner_url))
                            <div class="col-12"><img src="{{ $event->banner_url }}" alt="Current event banner" class="img-fluid rounded border mb-2" style="max-height: 140px;"></div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Upload Banner Image</label>
                            <input class="form-control @error('banner') is-invalid @enderror" type="file" name="banner" accept="image/*">
                            <div class="form-text">Optional. Maximum 5MB.</div>
                            @error('banner')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Banner URL</label>
                            <input class="form-control @error('banner_url') is-invalid @enderror" name="banner_url" value="{{ old('banner_url', $event->banner_url ?? '') }}" placeholder="https://... or /api/v1/files/...">
                            @error('banner_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <div></div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('schedule-tab')">
                                Next: Date, Recurrence & Location <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Date, Recurrence & Location -->
                <div class="tab-pane fade" id="schedule-section" role="tabpanel" aria-labelledby="schedule-tab">
                    <h5 class="form-section-title"><i class="bi bi-clock text-primary me-2"></i>Date & Time</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input class="form-control @error('start_at') is-invalid @enderror" type="date" id="startDate" required placeholder="dd-mm-yyyy">
                            @error('start_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input class="form-control" type="time" id="startTime" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <input class="form-control @error('end_at') is-invalid @enderror" type="date" id="endDate" placeholder="dd-mm-yyyy">
                            @error('end_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Time</label>
                            <input class="form-control" type="time" id="endTime">
                        </div>
                        <input type="hidden" name="start_at" id="startAtHidden" value="{{ old('start_at', optional($event->start_at ?? null)->format('Y-m-d\TH:i')) }}">
                        <input type="hidden" name="end_at" id="endAtHidden" value="{{ old('end_at', optional($event->end_at ?? null)->format('Y-m-d\TH:i')) }}">
                        <div class="col-12"><div class="text-danger small d-none" id="dateTimeError">End date/time must be after the start date/time.</div></div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-arrow-repeat text-primary me-2"></i>Recurrence Settings</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Repeat</label>
                            <select class="form-select js-no-searchable-select" name="recurrence_type" id="recurrenceType">
                                <option value="none" @selected(old('recurrence_type', $event->recurrence_type ?? 'none') === 'none')>One-time Event</option>
                                <option value="daily" @selected(old('recurrence_type', $event->recurrence_type ?? 'none') === 'daily')>Daily</option>
                                <option value="weekly" @selected(old('recurrence_type', $event->recurrence_type ?? 'none') === 'weekly')>Weekly</option>
                                <option value="monthly" @selected(old('recurrence_type', $event->recurrence_type ?? 'none') === 'monthly')>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-4 recurrence-common d-none">
                            <label class="form-label fw-semibold">Repeat every</label>
                            <div class="input-group">
                                <input class="form-control" type="number" min="1" name="recurrence_interval" id="recurrenceInterval" value="{{ old('recurrence_interval', $event->recurrence_interval ?? 1) }}">
                                <span class="input-group-text" id="intervalUnit">week(s)</span>
                            </div>
                        </div>
                        <div class="col-md-4 recurrence-common d-none">
                            <label class="form-label fw-semibold">Repeat Until</label>
                            <input class="form-control" type="date" name="recurrence_ends_at" id="recurrenceEndsAt" value="{{ old('recurrence_ends_at', optional($event->recurrence_ends_at ?? null)->format('Y-m-d')) }}">
                        </div>

                        <div class="col-md-4 weekly-fields recurrence-fields">
                            <label class="form-label fw-semibold">Repeat on</label>
                            <select class="form-select" name="recurrence_day_of_week" id="dayOfWeek">
                                @foreach($days as $value => $label)
                                    <option value="{{ $value }}" @selected((int) old('recurrence_day_of_week', $event->recurrence_day_of_week ?? 1) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 monthly-fields recurrence-fields">
                            <label class="form-label d-block fw-semibold">Monthly pattern</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="monthly_pattern" id="monthlyFixed" value="fixed" checked>
                                <label class="form-check-label" for="monthlyFixed">On a fixed day of month</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="monthly_pattern" id="monthlyWeekday" value="weekday">
                                <label class="form-check-label" for="monthlyWeekday">On a week/day pattern</label>
                            </div>
                        </div>
                        <div class="col-md-4 monthly-fixed-fields recurrence-fields">
                            <label class="form-label fw-semibold">Day of month</label>
                            <select class="form-select" name="recurrence_day_of_month" id="dayOfMonth">
                                <option value="">Select day</option>
                                @for($i=1;$i<=31;$i++)
                                    <option value="{{ $i }}" @selected((int) old('recurrence_day_of_month', $event->recurrence_day_of_month ?? 0) === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4 monthly-weekday-fields recurrence-fields">
                            <label class="form-label fw-semibold">Week of month</label>
                            <select class="form-select" name="recurrence_week_of_month" id="weekOfMonth">
                                <option value="">Select week</option>
                                @foreach($weeks as $value => $label)
                                    <option value="{{ $value }}" @selected((int) old('recurrence_week_of_month', $event->recurrence_week_of_month ?? 0) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 monthly-weekday-fields recurrence-fields">
                            <label class="form-label fw-semibold">Day</label>
                            <select class="form-select" id="monthlyDayOfWeek">
                                @foreach($days as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12"><div class="alert alert-info mb-0 small" id="recurrencePreview">This is a one-time event.</div></div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-geo-alt text-primary me-2"></i>Location / Online Details</h5>
                    <div class="row g-3">
                        <div class="col-md-4 physical-location-fields">
                            <label class="form-label fw-semibold">Venue Name</label>
                            <input class="form-control" name="venue_name" value="{{ old('venue_name', data_get($metadata, 'venue_name')) }}" placeholder="Hotel / Hall / Office">
                        </div>
                        <div class="col-md-8 physical-location-fields">
                            <label class="form-label fw-semibold">Address Line</label>
                            <input class="form-control" name="address_line" value="{{ old('address_line', data_get($metadata, 'address_line')) }}" placeholder="Street address">
                        </div>
                        <div class="col-md-3 physical-location-fields">
                            <label class="form-label fw-semibold">City</label>
                            <input class="form-control" name="city" value="{{ old('city', data_get($metadata, 'city')) }}">
                        </div>
                        <div class="col-md-3 physical-location-fields">
                            <label class="form-label fw-semibold">State</label>
                            <input class="form-control" name="state" value="{{ old('state', data_get($metadata, 'state')) }}">
                        </div>
                        <div class="col-md-6 physical-location-fields">
                            <label class="form-label fw-semibold">Google Map URL</label>
                            <input class="form-control" name="google_maps_url" value="{{ old('google_maps_url', data_get($metadata, 'google_maps_url')) }}" placeholder="https://maps.google.com/...">
                        </div>
                        <div class="col-12 online-fields">
                            <label class="form-label fw-semibold">Online Meeting URL</label>
                            <input class="form-control" name="online_meeting_url" value="{{ old('online_meeting_url', $event->online_meeting_url ?? '') }}" placeholder="https://meet.google.com/... or Zoom link">
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
                            <button type="button" class="btn btn-primary" onclick="switchTab('agenda-tab')">
                                Next: Agenda & Speakers <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Agenda & Speakers -->
                <div class="tab-pane fade" id="agenda-section" role="tabpanel" aria-labelledby="agenda-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-calendar2-range text-primary me-2"></i>Event Agenda</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" id="addAgendaRow">
                            <i class="bi bi-plus-lg me-1"></i>Add Agenda Row
                        </button>
                    </div>
                    <div class="mb-4" id="agendaRows">
                        @foreach($agendaRows as $index => $row)
                            <div class="row g-2 align-items-end agenda-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Time</label>
                                    <input type="time" class="form-control" name="agenda[{{ $index }}][time]" value="{{ $row['time'] ?? '' }}">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold">Title</label>
                                    <input type="text" class="form-control" name="agenda[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" placeholder="Registration & Networking">
                                </div>
                                <div class="remove-row-col">
                                    <button type="button" class="btn btn-outline-danger remove-agenda-row remove-row remove-row-btn w-100 text-nowrap">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-people text-primary me-2"></i>Featured Speakers</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" id="addSpeakerRow">
                            <i class="bi bi-plus-lg me-1"></i>Add Speaker Row
                        </button>
                    </div>
                    <div class="mb-4" id="speakerRows">
                        @foreach($speakerRows as $index => $row)
                            <div class="row g-2 align-items-end speaker-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Name</label>
                                    <input class="form-control" name="speakers[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Designation</label>
                                    <input class="form-control" name="speakers[{{ $index }}][designation]" value="{{ $row['designation'] ?? '' }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Company</label>
                                    <input class="form-control" name="speakers[{{ $index }}][company]" value="{{ $row['company'] ?? '' }}">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small fw-semibold">Initials</label>
                                    <input class="form-control" name="speakers[{{ $index }}][initials]" value="{{ $row['initials'] ?? '' }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Photo URL</label>
                                    <input class="form-control" name="speakers[{{ $index }}][photo_url]" value="{{ $row['photo_url'] ?? '' }}" placeholder="https://.../speaker.jpg">
                                </div>
                                <div class="remove-row-col">
                                    <button type="button" class="btn btn-outline-danger remove-row remove-row-btn w-100 text-nowrap">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-award text-primary me-2"></i>What You'll Gain</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary px-3" id="addGainRow">
                            <i class="bi bi-plus-lg me-1"></i>Add Benefit Row
                        </button>
                    </div>
                    <div class="mb-4" id="gainRows">
                        @foreach($gainRows as $index => $gain)
                            <div class="row g-2 align-items-end gain-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle">
                                <div class="gain-input-col">
                                    <label class="form-label small fw-semibold">Benefit</label>
                                    <input class="form-control" name="what_youll_gain[{{ $index }}]" value="{{ $gain }}" placeholder="Network with 100+ curated MSME leaders">
                                </div>
                                <div class="remove-row-col">
                                    <button type="button" class="btn btn-outline-danger remove-row remove-row-btn w-100 text-nowrap">
                                        <i class="bi bi-trash me-1"></i>Remove
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('schedule-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('registration-tab')">
                                Next: Registration & Settings <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Registration & Organizer -->
                <div class="tab-pane fade" id="registration-section" role="tabpanel" aria-labelledby="registration-tab">
                    <h5 class="form-section-title"><i class="bi bi-qr-code text-primary me-2"></i>Registration & Tickets</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Registration Limit</label>
                            <input class="form-control @error('registration_limit') is-invalid @enderror" type="number" name="registration_limit" value="{{ old('registration_limit', $event->registration_limit ?? '') }}" placeholder="Leave blank for unlimited">
                            @error('registration_limit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ticket Price</label>
                            <input class="form-control @error('ticket_price') is-invalid @enderror" type="number" step="0.01" name="ticket_price" value="{{ old('ticket_price', $event->ticket_price ?? '') }}">
                            <div class="form-text">Paid events use Zoho. QR code is generated after successful payment.</div>
                            @error('ticket_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Zoho Form URL</label>
                            <input class="form-control" name="zoho_form_url" value="{{ old('zoho_form_url', $event->zoho_form_url ?? data_get($metadata, 'zoho_form_url')) }}">
                        </div>
                        @foreach([
                            'qr_checkin_enabled' => ['QR Check-in', 'Members get a QR code after registration. Scan it at event entry.'],
                            'visitor_registration_enabled' => ['Visitor Registration', 'Allow non-members/visitors to register for this event.'],
                            'member_registration_enabled' => ['Member Registration', 'Allow Unity members to register from the app.'],
                            'is_paid' => ['Paid Event', 'Enable this if the event requires payment.'],
                        ] as $name => [$label, $help])
                            <div class="col-md-6">
                                <div class="form-check border rounded p-3 h-100">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="{{ $name }}" value="1" id="{{ $name }}" @checked(old($name, $event->{$name} ?? ($name !== 'is_paid')))>
                                    <label class="form-check-label fw-semibold text-dark" for="{{ $name }}">{{ $label }}</label>
                                    <div class="small text-muted mt-1">{{ $help }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-person-lines-fill text-primary me-2"></i>Organizer Details</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Organizer Name</label>
                            <input class="form-control" name="organizer_name" value="{{ old('organizer_name', data_get($organizer, 'name')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Organizer Phone</label>
                            <input class="form-control" name="organizer_phone" value="{{ old('organizer_phone', data_get($organizer, 'phone')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Organizer Email</label>
                            <input class="form-control" type="email" name="organizer_email" value="{{ old('organizer_email', data_get($organizer, 'email')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Organizer Website</label>
                            <input class="form-control" name="organizer_website" value="{{ old('organizer_website', data_get($organizer, 'website')) }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('agenda-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">{{ $isEdit ? 'Save Changes' : 'Create Event' }}</button>
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

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('eventCreateForm');
        const eventType = document.querySelector('[name="event_type"]');
        const singleCircleField = document.querySelector('.single-circle-field');
        const stateEventField = document.querySelector('.state-event-field');
        const multiCircleField = document.querySelector('.multi-circle-field');
        const singleCircleSelect = document.getElementById('singleCircleSelect');
        const circleMultiSelect = document.getElementById('circleMultiSelect');
        const circleMultiSelectToggle = document.getElementById('circleMultiSelectToggle');
        const circleMultiSelectMenu = document.getElementById('circleMultiSelectMenu');
        const circleSearchInput = document.getElementById('circleSearchInput');
        const circleOptionsList = document.getElementById('circleOptionsList');
        const circleNoResults = document.getElementById('circleNoResults');
        const circleCheckboxes = Array.from(document.querySelectorAll('input[name="circle_ids[]"]'));
        const stateNameSelect = document.getElementById('stateNameSelect');
        const mode = document.getElementById('modeSelect');
        const recurrenceType = document.getElementById('recurrenceType');
        const interval = document.getElementById('recurrenceInterval');
        const intervalUnit = document.getElementById('intervalUnit');
        const preview = document.getElementById('recurrencePreview');
        const days = @json($days);
        const weeks = @json($weeks);

        function initDateTimeFields() {
            const startAt = document.getElementById('startAtHidden').value;
            const endAt = document.getElementById('endAtHidden').value;
            if (startAt) { const [d, t] = startAt.split('T'); document.getElementById('startDate').value = d || ''; document.getElementById('startTime').value = (t || '').slice(0,5); }
            if (endAt) { const [d, t] = endAt.split('T'); document.getElementById('endDate').value = d || ''; document.getElementById('endTime').value = (t || '').slice(0,5); }
        }

        function addRepeatRow(containerId, html) {
            const container = document.getElementById(containerId);
            const index = container.querySelectorAll('.repeat-row').length;
            container.insertAdjacentHTML('beforeend', html.replaceAll('__INDEX__', index));
        }

        document.getElementById('addAgendaRow').addEventListener('click', () => addRepeatRow('agendaRows', '<div class="row g-2 align-items-end agenda-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle"><div class="col-md-3"><label class="form-label small fw-semibold">Time</label><input type="time" class="form-control" name="agenda[__INDEX__][time]"></div><div class="col-md-7"><label class="form-label small fw-semibold">Title</label><input type="text" class="form-control" name="agenda[__INDEX__][title]" placeholder="Registration & Networking"></div><div class="remove-row-col"><button type="button" class="btn btn-outline-danger remove-agenda-row remove-row remove-row-btn w-100 text-nowrap"><i class="bi bi-trash me-1"></i>Remove</button></div></div>'));
        document.getElementById('addSpeakerRow').addEventListener('click', () => addRepeatRow('speakerRows', '<div class="row g-2 align-items-end speaker-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle"><div class="col-md-3"><label class="form-label small fw-semibold">Name</label><input class="form-control" name="speakers[__INDEX__][name]"></div><div class="col-md-2"><label class="form-label small fw-semibold">Designation</label><input class="form-control" name="speakers[__INDEX__][designation]"></div><div class="col-md-2"><label class="form-label small fw-semibold">Company</label><input class="form-control" name="speakers[__INDEX__][company]"></div><div class="col-md-1"><label class="form-label small fw-semibold">Initials</label><input class="form-control" name="speakers[__INDEX__][initials]"></div><div class="col-md-3"><label class="form-label small fw-semibold">Photo URL</label><input class="form-control" name="speakers[__INDEX__][photo_url]" placeholder="https://.../speaker.jpg"></div><div class="remove-row-col"><button type="button" class="btn btn-outline-danger remove-row remove-row-btn w-100 text-nowrap"><i class="bi bi-trash me-1"></i>Remove</button></div></div>'));
        document.getElementById('addGainRow').addEventListener('click', () => addRepeatRow('gainRows', '<div class="row g-2 align-items-end gain-row dynamic-row mb-2 repeat-row border rounded-3 p-3 bg-light-subtle"><div class="gain-input-col"><label class="form-label small fw-semibold">Benefit</label><input class="form-control" name="what_youll_gain[__INDEX__]" placeholder="Network with 100+ curated MSME leaders"></div><div class="remove-row-col"><button type="button" class="btn btn-outline-danger remove-row remove-row-btn w-100 text-nowrap"><i class="bi bi-trash me-1"></i>Remove</button></div></div>'));
        document.addEventListener('click', (event) => { if (event.target.classList.contains('remove-row')) event.target.closest('.repeat-row')?.remove(); });

        const toggle = (selector, show) => document.querySelectorAll(selector).forEach(el => el.classList.toggle('d-none', !show));
        const ordinal = n => ({1:'First',2:'Second',3:'Third',4:'Fourth',5:'Last'}[n] || n);
        const untilText = () => document.getElementById('recurrenceEndsAt').value ? ` until ${new Date(document.getElementById('recurrenceEndsAt').value + 'T00:00:00').toLocaleDateString(undefined, {day:'2-digit', month:'short', year:'numeric'})}` : '';

        function syncDateTimes() {
            const sd = document.getElementById('startDate').value, st = document.getElementById('startTime').value;
            const ed = document.getElementById('endDate').value, et = document.getElementById('endTime').value;
            document.getElementById('startAtHidden').value = sd && st ? `${sd}T${st}` : '';
            document.getElementById('endAtHidden').value = ed && et ? `${ed}T${et}` : '';
        }

        function selectedCircleCheckboxes() {
            return circleCheckboxes.filter(checkbox => checkbox.checked && !checkbox.disabled && !checkbox.closest('.multi-select-option')?.classList.contains('d-none'));
        }

        function circleLabel(checkbox) {
            return checkbox.closest('.multi-select-option')?.querySelector('span')?.textContent?.trim() || '';
        }

        function updateSelectedCircleText() {
            const selected = selectedCircleCheckboxes();
            const placeholder = circleMultiSelectToggle.querySelector('.multi-select-placeholder');
            placeholder.innerHTML = '';

            if (selected.length === 0) {
                placeholder.textContent = 'Select circles';
                singleCircleSelect.disabled = eventType.value === 'global_event' || eventType.value === 'state_event';
                return;
            }

            selected.slice(0, 2).forEach(checkbox => {
                const chip = document.createElement('span');
                chip.className = 'multi-select-chip';
                chip.innerHTML = `<span class="multi-select-chip-text"></span><button type="button" class="multi-select-chip-remove" aria-label="Remove selected circle">×</button>`;
                chip.querySelector('.multi-select-chip-text').textContent = circleLabel(checkbox).split(' — ')[0];
                chip.querySelector('.multi-select-chip-remove').addEventListener('click', (event) => {
                    event.stopPropagation();
                    checkbox.checked = false;
                    updateSelectedCircleText();
                });
                placeholder.appendChild(chip);
            });

            if (selected.length > 2) {
                const more = document.createElement('span');
                more.className = 'text-muted small';
                more.textContent = `+${selected.length - 2} more`;
                placeholder.appendChild(more);
            }

            if (eventType.value === 'global_event' || eventType.value === 'state_event') {
                singleCircleSelect.disabled = false;
                singleCircleSelect.value = selected[0].value;
                singleCircleSelect.disabled = true;
            }
        }

        function filterCircleOptions() {
            const term = circleSearchInput.value.trim().toLowerCase();
            let visibleCount = 0;
            circleCheckboxes.forEach(checkbox => {
                const option = checkbox.closest('.multi-select-option');
                const matchesState = eventType.value !== 'state_event' || !stateNameSelect.value || option.dataset.state === stateNameSelect.value;
                const matchesSearch = term === '' || (option.dataset.label || '').includes(term);
                const visible = matchesState && matchesSearch;
                option.classList.toggle('d-none', !visible);
                checkbox.disabled = !(eventType.value === 'global_event' || eventType.value === 'state_event') || !matchesState;
                if (!matchesState) checkbox.checked = false;
                if (visible) visibleCount += 1;
            });
            circleNoResults.classList.toggle('d-none', visibleCount > 0);
            updateSelectedCircleText();
        }

        function updateEventType() {
            const type = eventType.value;
            const isMulti = type === 'global_event' || type === 'state_event';
            singleCircleField.classList.toggle('d-none', isMulti);
            multiCircleField.classList.toggle('d-none', !isMulti);
            stateEventField.classList.toggle('d-none', type !== 'state_event');
            singleCircleSelect.disabled = isMulti;
            stateNameSelect.disabled = type !== 'state_event';
            if (!isMulti) {
                circleMultiSelectMenu.classList.add('d-none');
                circleCheckboxes.forEach(checkbox => { checkbox.disabled = true; });
            }
            filterCircleOptions();
        }

        function updateMode() {
            const value = mode.value;
            toggle('.physical-location-fields', value === 'offline' || value === 'hybrid');
            toggle('.online-fields', value === 'online' || value === 'hybrid');
        }

        function updateRecurrence() {
            const type = recurrenceType.value;
            toggle('.recurrence-common', type !== 'none');
            toggle('.recurrence-fields', false);
            intervalUnit.textContent = type === 'daily' ? 'day(s)' : type === 'weekly' ? 'week(s)' : 'month(s)';
            if (type === 'weekly') toggle('.weekly-fields', true);
            if (type === 'monthly') {
                toggle('.monthly-fields', true);
                const fixed = document.getElementById('monthlyFixed').checked;
                toggle('.monthly-fixed-fields', fixed);
                toggle('.monthly-weekday-fields', !fixed);
                document.getElementById('monthlyDayOfWeek').disabled = fixed;
                document.getElementById('dayOfWeek').disabled = fixed;
                if (!fixed) document.getElementById('dayOfWeek').value = document.getElementById('monthlyDayOfWeek').value;
            }

            updatePreview();
        }

        function updatePreview() {
            const type = recurrenceType.value;
            const every = interval.value || 1;
            if (type === 'none') { preview.textContent = 'This is a one-time event.'; return; }
            if (type === 'daily') preview.textContent = `This event will repeat every ${every} day(s)${untilText()}.`;
            if (type === 'weekly') preview.textContent = `This event will repeat every ${every} week(s) on ${days[document.getElementById('dayOfWeek').value]}${untilText()}.`;
            if (type === 'monthly') {
                if (document.getElementById('monthlyFixed').checked) preview.textContent = `This event will repeat every ${every} month(s) on day ${document.getElementById('dayOfMonth').value || '—'}${untilText()}.`;
                else preview.textContent = `This event will repeat every ${every} month(s) on the ${ordinal(document.getElementById('weekOfMonth').value)} ${days[document.getElementById('monthlyDayOfWeek').value]}${untilText()}.`;
            }
        }

        document.querySelectorAll('input,select').forEach(el => el.addEventListener('change', () => { syncDateTimes(); updateEventType(); updateMode(); updateRecurrence(); }));
        circleMultiSelectToggle.addEventListener('click', () => { if (!multiCircleField.classList.contains('d-none')) circleMultiSelectMenu.classList.toggle('d-none'); });
        circleCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateSelectedCircleText));
        circleSearchInput.addEventListener('input', filterCircleOptions);
        stateNameSelect.addEventListener('change', () => { circleCheckboxes.forEach(checkbox => { checkbox.checked = false; }); circleSearchInput.value = ''; filterCircleOptions(); });
        eventType.addEventListener('change', () => { circleSearchInput.value = ''; filterCircleOptions(); });
        document.addEventListener('click', (event) => { if (!circleMultiSelect.contains(event.target)) circleMultiSelectMenu.classList.add('d-none'); });
        document.getElementById('monthlyDayOfWeek').addEventListener('change', e => document.getElementById('dayOfWeek').value = e.target.value);

        form.addEventListener('submit', (e) => {
            syncDateTimes();
            const start = document.getElementById('startAtHidden').value ? new Date(document.getElementById('startAtHidden').value) : null;
            const end = document.getElementById('endAtHidden').value ? new Date(document.getElementById('endAtHidden').value) : null;
            if ((eventType.value === 'global_event' || eventType.value === 'state_event') && selectedCircleCheckboxes().length === 0) {
                document.getElementById('multiCircleError').classList.remove('d-none');
                e.preventDefault();
                return;
            }
            document.getElementById('multiCircleError').classList.add('d-none');
            const invalid = start && end && end <= start;
            document.getElementById('dateTimeError').classList.toggle('d-none', !invalid);
            if (invalid) e.preventDefault();
        });

        initDateTimeFields();
        updateEventType(); updateSelectedCircleText(); updateMode(); updateRecurrence();
    });
</script>
@endpush
