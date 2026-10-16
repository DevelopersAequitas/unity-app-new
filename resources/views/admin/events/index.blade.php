@extends('admin.layouts.app')

@section('title', 'Events Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4 max-w-full min-w-0">

    @if(session('success'))
      <div class="px-3.5 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
          <span>{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-xs">&times;</button>
      </div>
    @endif

    @if(session('error'))
      <div class="px-3.5 py-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i>
          <span>{{ session('error') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 border-0 bg-transparent cursor-pointer text-xs">&times;</button>
      </div>
    @endif

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-2">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0 flex items-center gap-2">
          <i class="bi bi-calendar-event text-lg"></i>Events Management
        </h2>
        <p class="text-xs t3 m-0 mt-0.5">Manage directory details, event mode, attendee registrations, and scheduling</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.event-coupons.index') }}" class="px-3 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-2xs">
          <i class="bi bi-ticket-perforated text-indigo-600"></i> Event Coupons
        </a>
        <a href="{{ route('admin.events.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-2xs">
          <i class="bi bi-plus-lg"></i> Create Event
        </a>
      </div>
    </div>

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <a href="{{ route('admin.events.index') }}" class="kpi-card no-underline">
        <div class="kpi-top">
          <div class="kpi-title">Total Events</div>
          <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-calendar-event"></i></div>
        </div>
        <div class="kpi-num">{{ $events->total() }}</div>
      </a>
      <a href="{{ route('admin.events.index', array_merge(request()->query(), ['mode' => 'offline'])) }}" class="kpi-card no-underline @if(request('mode') === 'offline') border-emerald-500 ring-1 ring-emerald-500 @endif">
        <div class="kpi-top">
          <div class="kpi-title">Offline Events</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-geo-alt"></i></div>
        </div>
        <div class="kpi-num">{{ $events->getCollection()->filter(fn ($e) => strtolower((string) $e->mode) === 'offline')->count() }}</div>
      </a>
      <a href="{{ route('admin.events.total-attendance') }}" class="kpi-card no-underline">
        <div class="kpi-top">
          <div class="kpi-title">Total Attendance</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-person-check"></i></div>
        </div>
        <div class="kpi-num">{{ $events->getCollection()->sum('checked_in_count') }}</div>
      </a>
      <a href="{{ route('admin.events.total-registered') }}" class="kpi-card no-underline">
        <div class="kpi-top">
          <div class="kpi-title">Total Registered</div>
          <div class="kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people"></i></div>
        </div>
        <div class="kpi-num">{{ $events->getCollection()->sum('registered_count') }}</div>
      </a>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.events.index') }}" class="surface-2 rounded-xl border bs p-3.5 space-y-3">
      <div class="flex flex-wrap items-center gap-2.5">
        <div class="flex-1 min-w-[200px]">
          <input type="text" class="w-full px-3 py-2 text-xs rounded-lg border bs surface t1 focus-ring" name="search" value="{{ request('search') }}" placeholder="Search event title...">
        </div>
        <div class="w-auto min-w-[130px]">
          <select class="w-full px-3 py-2 text-xs rounded-lg border bs surface t1 focus-ring" name="event_type">
            <option value="">All Types</option>
            @foreach(['circle_meeting' => 'Circle Meeting', 'global_event' => 'Global Event', 'public_event' => 'City/Public Event'] as $type => $label)
              <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="w-auto min-w-[140px]">
          <select class="w-full px-3 py-2 text-xs rounded-lg border bs surface t1 focus-ring" name="circle_id">
            <option value="">All Circles</option>
            @foreach($circles as $circle)
              <option value="{{ $circle->id }}" @selected(request('circle_id') == $circle->id)>{{ $circle->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="w-auto min-w-[120px]">
          <select class="w-full px-3 py-2 text-xs rounded-lg border bs surface t1 focus-ring" name="mode">
            <option value="">All Modes</option>
            @foreach(['offline' => 'Offline', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $modeKey => $modeLabel)
              <option value="{{ $modeKey }}" @selected(request('mode') === $modeKey)>{{ $modeLabel }}</option>
            @endforeach
          </select>
        </div>
        <div class="w-auto min-w-[125px]">
          <select class="w-full px-3 py-2 text-xs rounded-lg border bs surface t1 focus-ring" name="is_active">
            <option value="">All Statuses</option>
            <option value="1" @selected(request('is_active') === '1')>Active Only</option>
            <option value="0" @selected(request('is_active') === '0')>Inactive Only</option>
          </select>
        </div>
        <div class="flex items-center gap-1.5 min-w-[160px]">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">From:</span>
          <input type="date" class="w-full px-2.5 py-1.5 text-xs rounded-lg border bs surface t1 focus-ring" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="flex items-center gap-1.5 min-w-[160px]">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">To:</span>
          <input type="date" class="w-full px-2.5 py-1.5 text-xs rounded-lg border bs surface t1 focus-ring" name="date_to" value="{{ request('date_to') }}">
        </div>
        <div class="flex items-center gap-2 ms-auto">
          <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold transition focus-ring border-0 shadow-2xs">Filter</button>
          <a href="{{ route('admin.events.index') }}" class="px-3.5 py-2 surface-2 hover:surface-3 t2 rounded-lg text-xs font-semibold border bs transition text-center no-underline">Clear</a>
        </div>
      </div>
    </form>

    <!-- Table Section -->
    <div class="surface rounded-xl border bs overflow-hidden shadow-2xs">
      <div class="overflow-x-auto relative w-full">
        <table class="w-full min-w-[1100px] border-collapse text-[13px] align-middle">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
              <th class="th-cell px-3 py-3 text-left sticky left-0 z-10 surface-2 whitespace-nowrap" style="min-width: 200px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Title</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 130px;">Type</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 160px;">Circle</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 90px;">Mode</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 155px;">Start Date</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 110px;">Recurrence</th>
              <th class="th-cell px-3 py-3 text-center whitespace-nowrap" style="min-width: 120px;">Total Recurrence</th>
              <th class="th-cell px-3 py-3 text-center whitespace-nowrap" style="min-width: 95px;">Checked-in</th>
              <th class="th-cell px-3 py-3 text-left whitespace-nowrap" style="min-width: 110px;">Status</th>
              <th class="th-cell px-3 py-3 text-right whitespace-nowrap" style="min-width: 280px;">Actions</th>
            </tr>
          </thead>
          <tbody id="grid-body" class="divide-y divide-gray-200/50">
            @forelse($events as $event)
              <tr class="hover:surface-2 transition border-b bs whitespace-nowrap">
                <td class="px-3 py-3 font-semibold text-slate-900 t1 sticky left-0 z-10 surface whitespace-nowrap" style="min-width:200px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                  <a href="{{ route('admin.events.show', $event->id) }}" class="text-indigo-600 hover:text-indigo-800 no-underline font-medium whitespace-nowrap">
                    {{ $event->title }}
                  </a>
                </td>
                <td class="px-3 py-3 text-xs whitespace-nowrap">
                  @php
                    $typeMap = [
                      'circle_meeting' => 'Circle Meeting',
                      'global_event' => 'Global Event',
                      'state_event' => 'State Event',
                      'public_event' => 'City/Public Event',
                      'meeting' => 'Meeting',
                      'physical' => 'Physical',
                      'virtual' => 'Virtual',
                    ];
                    $rawType = (string) ($event->event_type ?? '');
                    $typeLabel = $typeMap[$rawType] ?? ucwords(str_replace(['_', '-'], ' ', $rawType));
                  @endphp
                  <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 rounded-full whitespace-nowrap inline-flex items-center">
                    {{ $typeLabel ?: '—' }}
                  </span>
                </td>
                <td class="px-3 py-3 text-xs t2 whitespace-nowrap">
                  @if(!empty($event->circle?->id))
                    <a href="{{ route('admin.circles.show', $event->circle->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium no-underline whitespace-nowrap">
                      {{ $event->circle->name }}
                    </a>
                  @else
                    <span class="text-slate-400 whitespace-nowrap">{{ $event->circle?->name ?? '-' }}</span>
                  @endif
                </td>
                <td class="px-3 py-3 text-xs whitespace-nowrap">
                  @php $modeVal = strtolower((string) ($event->mode ?? 'offline')); @endphp
                  @if($modeVal === 'offline')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 rounded-full whitespace-nowrap">
                      Offline
                    </span>
                  @elseif($modeVal === 'online')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200 rounded-full whitespace-nowrap">
                      Online
                    </span>
                  @elseif($modeVal === 'hybrid')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-purple-50 text-purple-700 border-purple-200 rounded-full whitespace-nowrap">
                      Hybrid
                    </span>
                  @else
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-50 text-gray-700 border-gray-200 rounded-full whitespace-nowrap">
                      {{ ucfirst($modeVal) }}
                    </span>
                  @endif
                </td>
                <td class="px-3 py-3 text-xs t3 whitespace-nowrap font-mono">
                  <span class="utc-to-local" data-utc="{{ optional($event->start_at)->toIso8601String() }}">{{ optional($event->start_at)->format('d M Y, h:i A') ?? '-' }}</span>
                </td>
                <td class="px-3 py-3 text-xs font-mono t2 capitalize whitespace-nowrap">
                  {{ $event->recurrence_type ?? 'none' }}
                </td>
                <td class="px-3 py-3 text-center whitespace-nowrap">
                  <a href="{{ route('admin.events.show', $event->id) }}" class="font-bold text-indigo-600 hover:text-indigo-800 no-underline text-xs inline-block px-2 py-0.5 rounded bg-indigo-50/70" title="View occurrences for this event">
                    {{ $event->occurrences_count ?? 0 }}
                  </a>
                </td>
                <td class="px-3 py-3 text-center whitespace-nowrap">
                  <a href="{{ route('admin.events.total-attendance', ['event_id' => $event->id]) }}" class="font-bold text-emerald-600 hover:text-emerald-800 no-underline text-xs inline-block px-2 py-0.5 rounded bg-emerald-50/70" title="View attendance list for this event">
                    {{ $event->checked_in_count ?? 0 }}
                  </a>
                </td>
                <td class="px-3 py-3 text-xs whitespace-nowrap">
                  @php
                    $isActive = (bool) ($event->is_active ?? true);
                    $status = $event->computed_status;
                  @endphp
                  <div class="flex flex-col gap-1">
                    <span id="badge-active-{{ $event->id }}" class="chip px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1 whitespace-nowrap w-max {{ $isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                      <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                      <span id="badge-text-{{ $event->id }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                    </span>
                    @if($status === 'completed')
                      <span class="chip px-2 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-600 border-gray-200 rounded-full whitespace-nowrap w-max">Completed</span>
                    @elseif($status === 'cancelled')
                      <span class="chip px-2 py-0.5 text-[10px] font-medium bg-rose-50 text-rose-600 border-rose-200 rounded-full whitespace-nowrap w-max">Cancelled</span>
                    @else
                      <span class="chip px-2 py-0.5 text-[10px] font-medium bg-indigo-50 text-indigo-600 border-indigo-200 rounded-full whitespace-nowrap w-max">Scheduled</span>
                    @endif
                  </div>
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <form action="{{ route('admin.events.toggle-status', $event->id) }}" method="POST" class="inline-block m-0 p-0">
                      @csrf
                      <button type="button" 
                              id="btn-toggle-{{ $event->id }}"
                              onclick="toggleEventStatus('{{ $event->id }}', this)" 
                              class="px-2.5 py-1 rounded-md text-[11px] font-semibold border no-underline transition whitespace-nowrap inline-flex items-center gap-1 {{ $isActive ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' }}"
                              title="Click to toggle Active/Inactive status for all APIs">
                        <i class="bi {{ $isActive ? 'bi-toggle-on text-amber-600' : 'bi-toggle-off text-emerald-600' }} text-xs"></i>
                        <span>{{ $isActive ? 'Deactivate' : 'Activate' }}</span>
                      </button>
                    </form>
                    <a href="{{ route('admin.events.show', $event->id) }}" class="px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-[11px] font-semibold no-underline transition whitespace-nowrap">
                      View
                    </a>
                    <a href="{{ route('admin.events.edit', $event->id) }}" class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-semibold no-underline transition whitespace-nowrap">
                      Edit
                    </a>
                    <a href="{{ route('admin.events.total-registered', ['event_id' => $event->id]) }}" class="px-2.5 py-1 rounded-md bg-purple-50 text-purple-700 hover:bg-purple-100 text-[11px] font-semibold no-underline transition whitespace-nowrap">
                      Registrations
                    </a>
                    <a href="{{ route('admin.events.total-attendance', ['event_id' => $event->id]) }}" class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-semibold no-underline transition whitespace-nowrap">
                      Attendance
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center py-10 text-xs t3">
                  <i class="bi bi-calendar-x text-2xl d-block mb-1 text-slate-300"></i>
                  No events found matching your criteria.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($events->hasPages())
        <div class="p-3 border-t bs flex justify-between items-center">
          {{ $events->links() }}
        </div>
      @endif
    </div>

</div>

<script>
function toggleEventStatus(eventId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<span class="inline-block animate-spin" style="width: 10px; height: 10px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%;"></span>';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
      || document.querySelector('input[name="_token"]')?.value
      || '{{ csrf_token() }}';

    fetch(`/admin/events/${encodeURIComponent(eventId)}/toggle-status`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            const isActive = data.is_active;
            if (isActive) {
                btn.className = 'px-2.5 py-1 rounded-md text-[11px] font-semibold border no-underline transition whitespace-nowrap inline-flex items-center gap-1 bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100';
                btn.innerHTML = '<i class="bi bi-toggle-on text-amber-600 text-xs"></i><span>Deactivate</span>';
                btn.title = 'Click to deactivate event';
            } else {
                btn.className = 'px-2.5 py-1 rounded-md text-[11px] font-semibold border no-underline transition whitespace-nowrap inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';
                btn.innerHTML = '<i class="bi bi-toggle-off text-emerald-600 text-xs"></i><span>Activate</span>';
                btn.title = 'Click to activate event';
            }

            const badge = document.getElementById(`badge-active-${eventId}`);
            if (badge) {
                if (isActive) {
                    badge.className = 'chip px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1 whitespace-nowrap w-max bg-emerald-50 text-emerald-700 border-emerald-200';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span id="badge-text-' + eventId + '">Active</span>';
                } else {
                    badge.className = 'chip px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1 whitespace-nowrap w-max bg-rose-50 text-rose-700 border-rose-200';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span><span id="badge-text-' + eventId + '">Inactive</span>';
                }
            }
        } else {
            btn.innerHTML = originalContent;
            alert(data.message || 'Failed to update event status.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        btn.closest('form').submit();
    });
}
</script>
@endsection
