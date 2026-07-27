@extends('admin.layouts.app')

@section('title', 'Events Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Events Management</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage circle meetings, global events, registrations, and attendance metrics.</p>
        </div>
        <a href="{{ route('admin.events.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
            ➕ Create Event
        </a>
    </div>

    <form class="border bs rounded-xl p-3.5 mb-4 surface-2" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Search Title</label>
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search title">
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Event Type</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="event_type"><option value="">All Types</option>@foreach(['circle_meeting','global_event','public_event'] as $type)<option value="{{ $type }}" @selected(request('event_type')===$type)>{{ $type }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Circle</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="circle_id"><option value="">All Circles</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(request('circle_id')===$circle->id)>{{ $circle->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Mode</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="mode"><option value="">All Modes</option>@foreach(['offline','online','hybrid'] as $mode)<option value="{{ $mode }}" @selected(request('mode')===$mode)>{{ ucfirst($mode) }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">From Date</label>
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" type="date" name="date_from" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">To Date</label>
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" type="date" name="date_to" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 flex justify-end">
                <a class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline" href="{{ route('admin.events.index') }}">Clear</a>
            </div>
        </div>
    </form>

    <div class="rounded-xl border bs surface overflow-hidden">
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
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Checked-In</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                @forelse($events as $event)
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $event->title }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $event->event_type }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $event->circle?->name ?? '-' }}</td>
                        <td class="px-3 py-2.5 text-xs uppercase t3">{{ $event->mode }}</td>
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($event->start_at)->format('d M Y h:i A') }}</td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $event->recurrence_type ?? 'none' }}</td>
                        <td class="px-3 py-2.5 text-center">
                            <a href="{{ route('admin.events.show', $event->id) }}" target="_blank" class="font-bold text-indigo-600 hover:text-indigo-700 no-underline text-xs">{{ $event->registered_count ?? 0 }}</a>
                        </td>
                        <td class="px-3 py-2.5 text-center font-semibold text-xs t1">{{ $event->checked_in_count ?? 0 }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ $event->status ?? 'scheduled' }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline" href="{{ route('admin.events.show', $event->id) }}">View</a>
                                <a class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 transition no-underline" href="{{ route('admin.events.edit', $event->id) }}">Edit</a>
                                <a class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold no-underline" href="{{ route('admin.events.attendance', $event->id) }}">Attendance</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center py-8 text-xs t3">No events found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $events->links() }}
        </div>
    </div>
</div>
@endsection

