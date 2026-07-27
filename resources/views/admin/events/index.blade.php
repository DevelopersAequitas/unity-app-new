@extends('admin.layouts.app')

@section('title', 'Events Management')

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    corePlugins: {
      preflight: false,
    }
  }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  #grid-root-container {
    --bg:#0A0E17; --surface:#10141F; --surface-2:#141926; --surface-3:#1A2030;
    --border:#232A3B; --border-soft:#1B2130;
    --text-1:#EEF0F5; --text-2:#9096A8; --text-3:#5C6478;
    --accent:#6366F1; --accent-2:#8B5CF6; --accent-soft:#6366F11A;
    --success:#10B981; --success-soft:#10B9811A;
    --warning:#F59E0B; --warning-soft:#F59E0B1A;
    --danger:#F43F5E; --danger-soft:#F43F5E1A;
    --info:#0EA5E9; --info-soft:#0EA5E91A;
    background-color: var(--bg);
    color: var(--text-1);
    font-family: 'Inter', sans-serif;
  }
  #grid-root-container.light {
    --bg:#F8FAFC; --surface:#FFFFFF; --surface-2:#F1F5F9; --surface-3:#E2E8F0;
    --border:#E2E8F0; --border-soft:#F1F5F9;
    --text-1:#0F172A; --text-2:#475569; --text-3:#94A3B8;
  }
  
  #grid-root-container .font-display { font-family: 'Lexend', sans-serif; }
  #grid-root-container .font-mono { font-family: 'JetBrains Mono', monospace; }
  #grid-root-container .t1 { color: var(--text-1); }
  #grid-root-container .t2 { color: var(--text-2); }
  #grid-root-container .t3 { color: var(--text-3); }
  #grid-root-container .bg-accent, .bg-accent { background-color: var(--accent) !important; }
  #grid-root-container .text-accent, .text-accent { color: var(--accent) !important; }
  #grid-root-container .surface { background-color: var(--surface) !important; }
  #grid-root-container .surface-2 { background-color: var(--surface-2) !important; }
  #grid-root-container .surface-3 { background-color: var(--surface-3) !important; }
  #grid-root-container .border { border-color: var(--border); }
  #grid-root-container .bs { border-color: var(--border-soft); }
  
  #grid-root-container table { border-color: var(--border-soft) !important; }
  #grid-root-container th { border-color: var(--border-soft) !important; }
  #grid-root-container td { border-color: var(--border-soft) !important; }
  
  #grid-root-container input[type="text"], 
  #grid-root-container input[type="email"], 
  #grid-root-container input[type="date"], 
  #grid-root-container select, 
  #grid-root-container textarea {
    background-color: var(--surface-2) !important;
    border-color: var(--border) !important;
    color: var(--text-1) !important;
  }
  
  .kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; transition: .15s; display: block; width: 100%; text-align: left; }
  .kpi-card:hover { border-color: var(--accent); transform: translateY(-1px); }
  .kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
  .kpi-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex: none; }
  .kpi-num { font-family: 'Lexend', sans-serif; font-weight: 700; font-size: 20px; line-height: 1.1; color: var(--text-1); font-variant-numeric: tabular-nums; }
  .kpi-title { font-size: 11px; font-weight: 600; color: var(--text-2); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }

  #grid-root-container .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.25;
  }
  #grid-root-container .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }

  .focus-ring:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

  <!-- Advanced Grid root container -->
  <div id="grid-root-container" class="light rounded-xl border bs p-4 relative">

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
      <a href="{{ route('admin.events.index') }}" class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Events</div>
          <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-calendar-event"></i></div>
        </div>
        <div class="kpi-num">{{ $events->total() }}</div>
      </a>
      <a href="{{ route('admin.events.index', array_merge(request()->query(), ['mode' => 'offline'])) }}" class="kpi-card @if(request('mode') === 'offline') border-emerald-500 ring-1 ring-emerald-500 @endif">
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
      <a href="{{ $totalAttendanceUrl }}" class="kpi-card">
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
      <a href="{{ $totalRegisteredUrl }}" class="kpi-card">
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
          <a href="{{ route('admin.events.index') }}" class="py-1.5 px-3 surface hover:surface-3 t2 rounded-lg text-xs font-medium border bs transition text-center no-underline">Reset</a>
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
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="surface-2 border-b bs t2 text-[11px] uppercase tracking-wider font-semibold">
              <th class="py-3 px-3">Title</th>
              <th class="py-3 px-3">Type</th>
              <th class="py-3 px-3">Circle</th>
              <th class="py-3 px-3">Mode</th>
              <th class="py-3 px-3">Start Date</th>
              <th class="py-3 px-3">Recurrence</th>
              <th class="py-3 px-3 text-center">Registered</th>
              <th class="py-3 px-3 text-center">Checked-in</th>
              <th class="py-3 px-3">Status</th>
              <th class="py-3 px-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y bs t1">
            @forelse($events as $event)
              <tr class="hover:surface-2 transition">
                <td class="py-3 px-3 font-semibold text-slate-900 t1">
                  <a href="{{ route('admin.events.show', $event->id) }}" class="text-indigo-600 hover:text-indigo-800 no-underline font-medium">
                    {{ $event->title }}
                  </a>
                </td>
                <td class="py-3 px-3">
                  <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                    {{ str_replace('_', ' ', ucfirst($event->event_type)) }}
                  </span>
                </td>
                <td class="py-3 px-3 t2">
                  {{ $event->circle?->name ?? '-' }}
                </td>
                <td class="py-3 px-3">
                  @if($event->mode === 'offline')
                    <span class="badge bg-emerald-50 text-emerald-700 border border-emerald-200">
                      <span class="badge-dot bg-emerald-500"></span> Offline
                    </span>
                  @elseif($event->mode === 'online')
                    <span class="badge bg-indigo-50 text-indigo-700 border border-indigo-200">
                      <span class="badge-dot bg-indigo-500"></span> Online
                    </span>
                  @else
                    <span class="badge bg-purple-50 text-purple-700 border border-purple-200">
                      <span class="badge-dot bg-purple-500"></span> Hybrid
                    </span>
                  @endif
                </td>
                <td class="py-3 px-3 t2 whitespace-nowrap">
                  <i class="bi bi-calendar-event text-slate-400 me-1"></i>
                  {{ optional($event->start_at)->format('d M Y, h:i A') ?? '-' }}
                </td>
                <td class="py-3 px-3">
                  <span class="capitalize px-2 py-0.5 text-[11px] rounded bg-slate-100 t2 font-mono">
                    {{ $event->recurrence_type ?? 'none' }}
                  </span>
                </td>
                <td class="py-3 px-3 text-center">
                  <a href="{{ route('admin.events.show', $event->id) }}#registrations-section" class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-600 hover:bg-indigo-100 no-underline" title="View event registrations table">
                    {{ $event->registered_count ?? 0 }}
                  </a>
                </td>
                <td class="py-3 px-3 text-center font-semibold t2">
                  {{ $event->checked_in_count ?? 0 }}
                </td>
                <td class="py-3 px-3">
                  @php $status = $event->computed_status; @endphp
                  @if($status === 'completed')
                    <span class="badge bg-slate-100 text-slate-600 border border-slate-200">Completed</span>
                  @elseif($status === 'cancelled')
                    <span class="badge bg-rose-50 text-rose-600 border border-rose-200">Cancelled</span>
                  @else
                    <span class="badge bg-emerald-50 text-emerald-600 border border-emerald-200">Scheduled</span>
                  @endif
                </td>
                <td class="py-3 px-3 text-right">
                  <div class="inline-flex items-center gap-1">
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
                <td colspan="10" class="text-center py-8 text-slate-400">
                  <i class="bi bi-calendar-x text-2xl d-block mb-2"></i>
                  No events found matching your criteria.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($events->hasPages())
        <div class="px-4 py-3 border-t bs surface-2">
          {{ $events->links() }}
        </div>
      @endif
    </div>

  </div>
</div>
@endsection
