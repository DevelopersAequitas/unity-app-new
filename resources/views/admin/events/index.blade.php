@extends('admin.layouts.app')

@section('title', 'Events Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0">Events Management</h2>
        <p class="text-xs t3 m-0 mt-0.5">Manage directory details, event mode, attendee registrations, and scheduling</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.events.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-sm">
          ➕ Create Event
        </a>
      </div>
    </div>

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
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
        <div class="kpi-num">{{ $events->getCollection()->where('mode', 'offline')->count() }}</div>
      </a>
      @php
        $firstEventWithAtt = $events->firstWhere('checked_in_count', '>', 0) ?? $events->first();
        $totalAttendanceUrl = $firstEventWithAtt ? route('admin.events.attendance', $firstEventWithAtt->id) : route('admin.events.index');
      @endphp
      <a href="{{ $totalAttendanceUrl }}" class="kpi-card no-underline">
        <div class="kpi-top">
          <div class="kpi-title">Total Attendance</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-person-check"></i></div>
        </div>
        <div class="kpi-num">{{ $events->getCollection()->sum('checked_in_count') }}</div>
      </a>
      @php
        $firstEventWithReg = $events->firstWhere('registered_count', '>', 0) ?? $events->first();
        $totalRegisteredUrl = $firstEventWithReg ? route('admin.events.show', $firstEventWithReg->id).'#registrations-section' : route('admin.event-joining-requests.index');
      @endphp
      <a href="{{ $totalRegisteredUrl }}" class="kpi-card no-underline">
        <div class="kpi-top">
          <div class="kpi-title">Total Registered</div>
          <div class="kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people"></i></div>
        </div>
        <div class="kpi-num">{{ $events->getCollection()->sum('registered_count') }}</div>
      </a>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.events.index') }}" class="surface-2 rounded-xl border bs p-3 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-2.5 items-center">
        <div class="md:col-span-2">
          <input type="text" class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="search" value="{{ request('search') }}" placeholder="🔍 Search event title...">
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="event_type">
            <option value="">All Types</option>
            @foreach(['circle_meeting' => 'Circle Meeting', 'global_event' => 'Global Event', 'public_event' => 'City/Public Event'] as $type => $label)
              <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="circle_id">
            <option value="">All Circles</option>
            @foreach($circles as $circle)
              <option value="{{ $circle->id }}" @selected(request('circle_id') == $circle->id)>{{ $circle->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="mode">
            <option value="">All Modes</option>
            @foreach(['offline' => 'Offline', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $modeKey => $modeLabel)
              <option value="{{ $modeKey }}" @selected(request('mode') === $modeKey)>{{ $modeLabel }}</option>
            @endforeach
          </select>
        </div>
        <div class="flex items-center gap-1.5">
          <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium transition focus-ring">Filter</button>
          <a href="{{ route('admin.events.index') }}" class="py-1.5 px-3 surface hover:surface-3 t2 rounded-lg text-xs font-medium border bs transition text-center no-underline">Clear</a>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 mt-2 pt-2 border-t bs">
        <div class="flex items-center gap-2">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">From:</span>
          <input type="date" class="w-full px-2.5 py-1 text-xs rounded-lg border bs focus-ring" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">To:</span>
          <input type="date" class="w-full px-2.5 py-1 text-xs rounded-lg border bs focus-ring" name="date_to" value="{{ request('date_to') }}">
        </div>
      </div>
    </form>

    <!-- Table Section -->
    <div class="surface rounded-xl border bs overflow-hidden">
      <div class="overflow-x-auto relative">
        <table class="min-w-full border-collapse text-[13px]">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Title</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Type</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Mode</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Start Date</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recurrence</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Registered</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Checked-in</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="grid-body" class="divide-y divide-gray-200/50">
            @forelse($events as $event)
              <tr class="hover:surface-2 transition border-b bs">
                <td class="px-3 py-2.5 font-semibold text-slate-900 t1">
                  <a href="{{ route('admin.events.show', $event->id) }}" class="text-indigo-600 hover:text-indigo-800 no-underline font-medium">
                    {{ $event->title }}
                  </a>
                </td>
                <td class="px-3 py-2.5 text-xs">
                  <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                    {{ str_replace('_', ' ', ucfirst($event->event_type)) }}
                  </span>
                </td>
                <td class="px-3 py-2.5 text-xs t2">
                  {{ $event->circle?->name ?? '-' }}
                </td>
                <td class="px-3 py-2.5 text-xs">
                  @if($event->mode === 'offline')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                      Offline
                    </span>
                  @elseif($event->mode === 'online')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                      Online
                    </span>
                  @else
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-purple-50 text-purple-700 border-purple-200">
                      Hybrid
                    </span>
                  @endif
                </td>
                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                  {{ optional($event->start_at)->format('d M Y, h:i A') ?? '-' }}
                </td>
                <td class="px-3 py-2.5 text-xs font-mono t2">
                  {{ $event->recurrence_type ?? 'none' }}
                </td>
                <td class="px-3 py-2.5 text-center">
                  <a href="{{ route('admin.events.show', $event->id) }}#registrations-section" class="font-bold text-indigo-600 hover:text-indigo-700 no-underline text-xs" title="View event registrations table">
                    {{ $event->registered_count ?? 0 }}
                  </a>
                </td>
                <td class="px-3 py-2.5 text-center font-semibold text-xs t2">
                  {{ $event->checked_in_count ?? 0 }}
                </td>
                <td class="px-3 py-2.5 text-xs">
                  @php $status = $event->computed_status; @endphp
                  @if($status === 'completed')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 border-gray-200">Completed</span>
                  @elseif($status === 'cancelled')
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-600 border-rose-200">Cancelled</span>
                  @else
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-600 border-emerald-200">Scheduled</span>
                  @endif
                </td>
                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <a href="{{ route('admin.events.show', $event->id) }}" class="px-2 py-1 rounded bg-indigo-50 text-indigo-600 hover:bg-indigo-100 text-[11px] font-medium no-underline transition">
                      View
                    </a>
                    <a href="{{ route('admin.events.show', $event->id) }}#registrations-section" class="px-2 py-1 rounded bg-purple-50 text-purple-700 hover:bg-purple-100 text-[11px] font-medium no-underline transition">
                      Registrations
                    </a>
                    <a href="{{ route('admin.events.edit', $event->id) }}" class="px-2 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-medium no-underline transition">
                      Edit
                    </a>
                    <a href="{{ route('admin.events.attendance', $event->id) }}" class="px-2 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-medium no-underline transition">
                      Attendance
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center py-8 text-xs t3">
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
</div>
@endsection
