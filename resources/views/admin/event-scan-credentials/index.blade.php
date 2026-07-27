@extends('admin.layouts.app')

@section('title', 'Event Scan Credentials')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Event Scan Credentials</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage scanner personnel access codes and event scanning credentials.</p>
        </div>
        <a href="{{ route('admin.event-scan-credentials.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
            ➕ Create Credential
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <form method="GET" class="border bs rounded-xl p-3.5 mb-4 surface-2">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search name, username, hotel, event">
            </div>
            <div class="col-md-2">
                <a href="{{ route('admin.event-scan-credentials.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline inline-block">Clear</a>
            </div>
        </div>
    </form>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Person</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Username</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Hotel</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Last Login</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse($credentials as $credential)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $credential->name }}</td>
                            <td class="px-3 py-2.5 text-xs font-mono text-indigo-600">{{ $credential->username }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $credential->hotel_name }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $credential->event?->title ?? $credential->event_name ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $credential->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200' }}">
                                    {{ $credential->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($credential->last_login_at)->toDateTimeString() ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.event-scan-credentials.edit', $credential->id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 transition no-underline">Edit</a>
                                    <form action="{{ route('admin.event-scan-credentials.toggle', $credential->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold {{ $credential->is_active ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                                            {{ $credential->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-8 text-xs t3">No scanner credentials found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $credentials->links() }}
        </div>
    </div>
</div>
@endsection

