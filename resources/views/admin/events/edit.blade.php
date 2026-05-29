@extends('admin.layouts.app')

@section('title', 'Edit Event')

@section('content')
@php
    $eventTypes = [
        'circle_meeting' => 'Circle Meeting',
        'global_event' => 'Global Event',
        'public_event' => 'Public / Visitor Event',
        'training' => 'Training / Workshop',
    ];
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $weeks = [1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Last'];
    $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
    $metadata = (array) ($event->metadata ?? []);
    $startAt = old('start_at', optional($event->start_at)->format('Y-m-d\TH:i'));
    $endAt = old('end_at', optional($event->end_at)->format('Y-m-d\TH:i'));
    $startDate = $startAt ? \Illuminate\Support\Str::of($startAt)->before('T') : '';
    $startTime = $startAt && str_contains($startAt, 'T') ? \Illuminate\Support\Str::of($startAt)->after('T')->substr(0, 5) : '';
    $endDate = $endAt ? \Illuminate\Support\Str::of($endAt)->before('T') : '';
    $endTime = $endAt && str_contains($endAt, 'T') ? \Illuminate\Support\Str::of($endAt)->after('T')->substr(0, 5) : '';
    $monthlyPattern = old('monthly_pattern', $event->recurrence_week_of_month ? 'weekday' : 'fixed');
@endphp
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Edit Event</h1>
            <p class="text-muted mb-0">Update event details without changing attendance, registrations, or existing occurrences.</p>
        </div>
        <a href="{{ route('admin.events.show', $event->id) }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('admin.events.update', $event->id) }}" id="eventEditForm">
        @csrf
        @method('PUT')
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="card mb-3">
            <div class="card-header fw-semibold">A. Basic Event Details</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">Event Title</label><input class="form-control" name="title" value="{{ old('title', $event->title) }}" required></div>
                <div class="col-md-3"><label class="form-label">Event Type</label><select class="form-select" name="event_type" required>@foreach($eventTypes as $value => $label)<option value="{{ $value }}" @selected(old('event_type', $event->event_type)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="event_category" value="{{ old('event_category', $event->event_category) }}"></div>
                <div class="col-md-4"><label class="form-label">Circle</label><select class="form-select" name="circle_id"><option value="">No specific circle</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(old('circle_id', $event->circle_id)===$circle->id)>{{ $circle->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Event Mode</label><select class="form-select" name="mode" id="modeSelect">@foreach(['offline' => 'Offline / Venue', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $value => $label)<option value="{{ $value }}" @selected(old('mode', $event->mode ?: 'offline')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3">{{ old('description', $event->description) }}</textarea></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">B. Date & Time</div>
            <div class="card-body row g-3">
                <div class="col-md-3"><label class="form-label">Start Date</label><input class="form-control" type="date" id="startDate" value="{{ $startDate }}" required></div>
                <div class="col-md-3"><label class="form-label">Start Time</label><input class="form-control" type="time" id="startTime" value="{{ $startTime }}" required></div>
                <div class="col-md-3"><label class="form-label">End Date</label><input class="form-control" type="date" id="endDate" value="{{ $endDate }}"></div>
                <div class="col-md-3"><label class="form-label">End Time</label><input class="form-control" type="time" id="endTime" value="{{ $endTime }}"></div>
                <input type="hidden" name="start_at" id="startAtHidden" value="{{ $startAt }}">
                <input type="hidden" name="end_at" id="endAtHidden" value="{{ $endAt }}">
                <div class="col-12"><div class="text-danger small d-none" id="dateTimeError">End date/time must be after the start date/time.</div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">C. Location / Online Details</div>
            <div class="card-body row g-3">
                <div class="col-md-4 physical-location-fields"><label class="form-label">Venue Name</label><input class="form-control" name="venue_name" value="{{ old('venue_name', $metadata['venue_name'] ?? '') }}"></div>
                <div class="col-md-8 physical-location-fields"><label class="form-label">Address Line</label><input class="form-control" name="address_line" value="{{ old('address_line', $metadata['address_line'] ?? '') }}"></div>
                <div class="col-md-3 physical-location-fields"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city', $metadata['city'] ?? '') }}"></div>
                <div class="col-md-3 physical-location-fields"><label class="form-label">State</label><input class="form-control" name="state" value="{{ old('state', $metadata['state'] ?? '') }}"></div>
                <div class="col-md-6 physical-location-fields"><label class="form-label">Google Maps URL</label><input class="form-control" name="google_maps_url" value="{{ old('google_maps_url', $metadata['google_maps_url'] ?? '') }}"></div>
                <div class="col-12"><label class="form-label">Location Text</label><input class="form-control" name="location_text" value="{{ old('location_text', $event->location_text) }}"></div>
                <div class="col-12 online-fields"><label class="form-label">Online Meeting URL</label><input class="form-control" name="online_meeting_url" value="{{ old('online_meeting_url', $event->online_meeting_url) }}"></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">D. Recurrence</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Recurrence Type</label><select class="form-select" name="recurrence_type" id="recurrenceType">@foreach(['none'=>'None','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $value => $label)<option value="{{ $value }}" @selected(old('recurrence_type', $event->recurrence_type ?: 'none')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 recurrence-common"><label class="form-label">Repeat Every</label><div class="input-group"><input class="form-control" type="number" min="1" name="recurrence_interval" id="recurrenceInterval" value="{{ old('recurrence_interval', $event->recurrence_interval ?: 1) }}"><span class="input-group-text" id="intervalUnit">week(s)</span></div></div>
                <div class="col-md-4 recurrence-common"><label class="form-label">Repeat Until</label><input class="form-control" type="date" name="recurrence_ends_at" id="recurrenceEndsAt" value="{{ old('recurrence_ends_at', optional($event->recurrence_ends_at)->format('Y-m-d')) }}"></div>
                <div class="col-md-4 weekly-fields recurrence-fields"><label class="form-label">Repeat on</label><select class="form-select" name="recurrence_day_of_week" id="dayOfWeek">@foreach($days as $value => $label)<option value="{{ $value }}" @selected((int) old('recurrence_day_of_week', $event->recurrence_day_of_week ?: 1)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-12 monthly-fields recurrence-fields"><label class="form-label d-block">Monthly pattern</label><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="monthly_pattern" id="monthlyFixed" value="fixed" @checked($monthlyPattern === 'fixed')><label class="form-check-label" for="monthlyFixed">On a fixed day of month</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="monthly_pattern" id="monthlyWeekday" value="weekday" @checked($monthlyPattern === 'weekday')><label class="form-check-label" for="monthlyWeekday">On a week/day pattern</label></div></div>
                <div class="col-md-4 monthly-fixed-fields recurrence-fields"><label class="form-label">Day of month</label><select class="form-select" name="recurrence_day_of_month" id="dayOfMonth"><option value="">Select day</option>@for($i=1;$i<=31;$i++)<option value="{{ $i }}" @selected((int) old('recurrence_day_of_month', $event->recurrence_day_of_month)===$i)>{{ $i }}</option>@endfor</select></div>
                <div class="col-md-4 monthly-weekday-fields recurrence-fields"><label class="form-label">Week of month</label><select class="form-select" name="recurrence_week_of_month" id="weekOfMonth"><option value="">Select week</option>@foreach($weeks as $value => $label)<option value="{{ $value }}" @selected((int) old('recurrence_week_of_month', $event->recurrence_week_of_month)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 monthly-weekday-fields recurrence-fields"><label class="form-label">Day</label><select class="form-select" id="monthlyDayOfWeek">@foreach($days as $value => $label)<option value="{{ $value }}" @selected((int) old('recurrence_day_of_week', $event->recurrence_day_of_week ?: 1)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 yearly-fields recurrence-fields"><label class="form-label">Month</label><select class="form-select" name="recurrence_month" id="recurrenceMonth"><option value="">Select month</option>@foreach($months as $value => $label)<option value="{{ $value }}" @selected((int) old('recurrence_month', $event->recurrence_month)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 yearly-fields recurrence-fields"><label class="form-label">Day of month</label><select class="form-select" id="yearlyDayOfMonth">@for($i=1;$i<=31;$i++)<option value="{{ $i }}" @selected((int) old('recurrence_day_of_month', $event->recurrence_day_of_month ?: 1)===$i)>{{ $i }}</option>@endfor</select></div>
                <div class="col-12"><div class="alert alert-info mb-0" id="recurrencePreview">This is a one-time event.</div></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-semibold">E. Registration & QR Settings</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Registration Limit</label><input class="form-control" type="number" name="registration_limit" value="{{ old('registration_limit', $event->registration_limit) }}"></div>
                <div class="col-md-4"><label class="form-label">Ticket Price</label><input class="form-control" type="number" step="0.01" name="ticket_price" value="{{ old('ticket_price', $event->ticket_price) }}"></div>
                <div class="col-md-4"><label class="form-label">Zoho Form URL</label><input class="form-control" name="zoho_form_url" value="{{ old('zoho_form_url', $event->zoho_form_url ?? ($metadata['zoho_form_url'] ?? '')) }}"></div>
                @foreach([
                    'qr_checkin_enabled' => ['QR Check-in', 'Members will get a QR code after registration. Scan it at the event entry.'],
                    'visitor_registration_enabled' => ['Visitor Registration', 'Allow non-members/visitors to register for this event.'],
                    'member_registration_enabled' => ['Member Registration', 'Allow Unity members to register from the app.'],
                    'is_paid' => ['Paid', 'Enable this if the event requires payment.'],
                ] as $name => [$label, $help])
                    <div class="col-md-6"><div class="form-check border rounded p-3 h-100"><input type="hidden" name="{{ $name }}" value="0"><input class="form-check-input ms-0 me-2" type="checkbox" name="{{ $name }}" value="1" id="{{ $name }}" @checked((bool) old($name, $event->{$name}))><label class="form-check-label fw-semibold" for="{{ $name }}">{{ $label }}</label><div class="small text-muted mt-1">{{ $help }}</div></div></div>
                @endforeach
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4"><a href="{{ route('admin.events.show', $event->id) }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary btn-lg">Update Event</button></div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('eventEditForm');
    const mode = document.getElementById('modeSelect');
    const recurrenceType = document.getElementById('recurrenceType');
    const interval = document.getElementById('recurrenceInterval');
    const intervalUnit = document.getElementById('intervalUnit');
    const preview = document.getElementById('recurrencePreview');
    const days = @json($days);
    const months = @json($months);

    const toggle = (selector, show) => document.querySelectorAll(selector).forEach(el => el.classList.toggle('d-none', !show));
    const ordinal = n => ({1:'First',2:'Second',3:'Third',4:'Fourth',5:'Last'}[n] || n);
    const untilText = () => document.getElementById('recurrenceEndsAt').value ? ` until ${new Date(document.getElementById('recurrenceEndsAt').value + 'T00:00:00').toLocaleDateString(undefined, {day:'2-digit', month:'short', year:'numeric'})}` : '';

    function syncDateTimes() {
        const sd = document.getElementById('startDate').value, st = document.getElementById('startTime').value;
        const ed = document.getElementById('endDate').value, et = document.getElementById('endTime').value;
        document.getElementById('startAtHidden').value = sd && st ? `${sd}T${st}` : '';
        document.getElementById('endAtHidden').value = ed && et ? `${ed}T${et}` : '';
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
        intervalUnit.textContent = type === 'weekly' ? 'week(s)' : type === 'monthly' ? 'month(s)' : 'year(s)';
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
        if (type === 'yearly') {
            toggle('.yearly-fields', true);
            document.getElementById('dayOfMonth').value = document.getElementById('yearlyDayOfMonth').value;
        }
        updatePreview();
    }

    function updatePreview() {
        const type = recurrenceType.value;
        const every = interval.value || 1;
        if (type === 'none') { preview.textContent = 'This is a one-time event.'; return; }
        if (type === 'weekly') preview.textContent = `This event will repeat every ${every} week(s) on ${days[document.getElementById('dayOfWeek').value]}${untilText()}.`;
        if (type === 'monthly') {
            if (document.getElementById('monthlyFixed').checked) preview.textContent = `This event will repeat every ${every} month(s) on day ${document.getElementById('dayOfMonth').value || '—'}${untilText()}.`;
            else preview.textContent = `This event will repeat every ${every} month(s) on the ${ordinal(document.getElementById('weekOfMonth').value)} ${days[document.getElementById('monthlyDayOfWeek').value]}${untilText()}.`;
        }
        if (type === 'yearly') preview.textContent = `This event will repeat every ${every} year(s) on ${months[document.getElementById('recurrenceMonth').value] || '—'} ${document.getElementById('yearlyDayOfMonth').value}${untilText()}.`;
    }

    document.querySelectorAll('input,select').forEach(el => el.addEventListener('change', () => { syncDateTimes(); updateMode(); updateRecurrence(); }));
    document.getElementById('monthlyDayOfWeek').addEventListener('change', e => document.getElementById('dayOfWeek').value = e.target.value);
    document.getElementById('yearlyDayOfMonth').addEventListener('change', e => document.getElementById('dayOfMonth').value = e.target.value);

    form.addEventListener('submit', (e) => {
        syncDateTimes();
        const start = document.getElementById('startAtHidden').value ? new Date(document.getElementById('startAtHidden').value) : null;
        const end = document.getElementById('endAtHidden').value ? new Date(document.getElementById('endAtHidden').value) : null;
        const invalid = start && end && end <= start;
        document.getElementById('dateTimeError').classList.toggle('d-none', !invalid);
        if (invalid) e.preventDefault();
    });

    updateMode(); updateRecurrence(); syncDateTimes();
});
</script>
@endsection
