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
        <div class="alert alert-success mb-4 text-xs py-2 px-3 rounded-lg">{{ session('success') }}</div>
    @endif

    <!-- Search & Filter Card -->
    <form method="GET" action="{{ route('admin.event-scan-credentials.index') }}" class="border bs rounded-xl p-3.5 mb-4 surface-2">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search person name, username, hotel, or event...">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring">Filter</button>
                <a href="{{ route('admin.event-scan-credentials.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline inline-block">Clear</a>
            </div>
        </div>
    </form>

    <!-- Table Card -->
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Person</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Username / Login ID</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Hotel / Venue</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Assigned Events</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Last Login</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse($credentials as $credential)
                        @php
                            $assignedEvents = $credential->assignedEvents();
                            $peerUser = $credential->peerUser;
                            $modalId = 'personModal_'.$credential->id;
                            
                            $getInitials = function($name) {
                                $words = explode(' ', trim($name));
                                $initials = '';
                                foreach ($words as $w) {
                                    if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
                                }
                                return substr($initials, 0, 2) ?: 'SC';
                            };
                            $initials = $getInitials($credential->name);
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5">
                                <button type="button" class="btn p-0 border-0 text-start bg-transparent" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Click to view full user details">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full text-white flex items-center justify-center font-bold text-xs shrink-0" style="background: linear-gradient(135deg, #4f46e5, #7c3aed);">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-indigo-600 text-[12.5px] hover:underline cursor-pointer">
                                                {{ $credential->name }}
                                            </div>
                                            <div class="t3 text-[10px]">
                                                Click to view details
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-[11px] border border-slate-200">
                                    {{ $credential->username }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $credential->hotel_name }}</td>
                            <td class="px-3 py-2.5 text-xs" style="max-width: 260px;">
                                @if($assignedEvents->count() > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($assignedEvents->take(3) as $ev)
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200" title="{{ $ev->title }}">
                                                {{ Str::limit($ev->title, 22) }}
                                            </span>
                                        @endforeach
                                        @if($assignedEvents->count() > 3)
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200" title="Click user to see all {{ $assignedEvents->count() }} events">
                                                +{{ $assignedEvents->count() - 3 }} more
                                            </span>
                                        @endif
                                    </div>
                                @elseif($credential->event)
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                                        {{ $credential->event->title }}
                                    </span>
                                @else
                                    <span class="t3 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $credential->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200' }}">
                                    {{ $credential->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                @if($credential->last_login_at)
                                    {{ $credential->last_login_at->format('d M Y, h:i A') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button class="px-2 py-0.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition bg-transparent" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                        View
                                    </button>
                                    <a href="{{ route('admin.event-scan-credentials.edit', $credential->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Edit</a>
                                    <form action="{{ route('admin.event-scan-credentials.toggle', $credential->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="px-2 py-0.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition {{ $credential->is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-emerald-700 hover:bg-emerald-50' }}">
                                            {{ $credential->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Person Details Modal -->
                        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <!-- Modal Header -->
                                    <div class="modal-header bg-dark text-white py-3 px-4 border-0">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-indigo-500 text-white d-flex align-items-center justify-content-center font-bold text-base shadow" style="width: 44px; height: 44px; background: linear-gradient(135deg, #6366f1, #a855f7);">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <h5 class="modal-title mb-0 fs-6 font-bold text-white">{{ $credential->name }}</h5>
                                                <div class="small text-slate-300">Scanner App User Details</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <!-- Modal Body -->
                                    <div class="modal-body p-4">
                                        <div class="row g-3">
                                            <!-- Credential Credentials Box -->
                                            <div class="col-md-6">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="text-xs uppercase font-bold text-slate-500 mb-3 tracking-wider">Login Credentials</h6>
                                                    
                                                    <div class="mb-2.5">
                                                        <label class="text-[11px] text-muted d-block font-medium">Username / Login ID</label>
                                                        <span class="font-mono text-slate-800 font-bold bg-white px-2.5 py-1 rounded border d-inline-block text-xs">
                                                            {{ $credential->username }}
                                                        </span>
                                                    </div>

                                                    <div class="mb-2.5">
                                                        <label class="text-[11px] text-muted d-block font-medium">Current Password</label>
                                                        @if(!empty($credential->plain_password))
                                                            <div class="input-group input-group-sm max-w-xs">
                                                                <input type="password" class="form-control font-mono text-xs bg-white" value="{{ $credential->plain_password }}" id="pwd_modal_{{ $credential->id }}" readonly>
                                                                <button class="btn btn-outline-secondary text-xs" type="button" onclick="const input = document.getElementById('pwd_modal_{{ $credential->id }}'); const icon = this.querySelector('i'); if(input.type==='password'){ input.type='text'; icon.className='bi bi-eye-slash'; } else { input.type='password'; icon.className='bi bi-eye'; }">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <span class="text-muted text-xs italic">Hashed in database (set on edit page)</span>
                                                        @endif
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="text-[11px] text-muted d-block font-medium">Hotel / Venue</label>
                                                        <span class="text-slate-800 font-semibold text-xs"><i class="bi bi-building me-1 text-slate-400"></i>{{ $credential->hotel_name }}</span>
                                                    </div>

                                                    <div>
                                                        <label class="text-[11px] text-muted d-block font-medium">Status & Last Login</label>
                                                        <div class="d-flex align-items-center gap-2 mt-1">
                                                            <span class="badge {{ $credential->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                                {{ $credential->is_active ? 'Active' : 'Inactive' }}
                                                            </span>
                                                            <span class="text-xs text-muted">
                                                                Last login: {{ optional($credential->last_login_at)->format('d M Y, h:i A') ?? 'Never' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Assigned Events List Box -->
                                            <div class="col-md-6">
                                                <div class="p-3 rounded-3 bg-light border h-100">
                                                    <h6 class="text-xs uppercase font-bold text-slate-500 mb-3 tracking-wider">
                                                        Assigned Events ({{ $assignedEvents->count() }})
                                                    </h6>
                                                    @if($assignedEvents->count() > 0)
                                                        <div class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                                                            @foreach($assignedEvents as $eventItem)
                                                                <div class="p-2 rounded bg-white border d-flex align-items-center justify-content-between text-xs">
                                                                    <div>
                                                                        <div class="font-semibold text-slate-900">{{ $eventItem->title }}</div>
                                                                        <div class="text-[11px] text-muted">
                                                                            <i class="bi bi-calendar-event me-1"></i>{{ optional($eventItem->start_at)->format('d M Y, h:i A') ?? 'Scheduled' }}
                                                                        </div>
                                                                    </div>
                                                                    <a href="{{ route('admin.events.show', $eventItem->id) }}" class="btn btn-xs btn-outline-indigo px-2 py-0.5 text-[10px] no-underline" target="_blank">View Event</a>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="text-muted text-xs mb-0">No assigned events selected.</p>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Linked Peer User Profile Card -->
                                            @if($peerUser)
                                                <div class="col-12 mt-3">
                                                    <div class="p-3 rounded-3 border bg-indigo-50/50">
                                                        <h6 class="text-xs uppercase font-bold text-indigo-700 mb-2 tracking-wider">
                                                            <i class="bi bi-person-badge me-1"></i>Matched Peer Profile
                                                        </h6>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            @include('admin.partials.peer_identity', ['user' => $peerUser])
                                                            <a href="{{ route('admin.users.show', $peerUser->id) }}" class="btn btn-sm btn-indigo text-xs no-underline" target="_blank">
                                                                View Peer Profile <i class="bi bi-arrow-up-right me-1"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Modal Footer -->
                                    <div class="modal-footer bg-light py-2 px-4 border-0">
                                        <a href="{{ route('admin.event-scan-credentials.edit', $credential->id) }}" class="btn btn-sm btn-primary text-xs no-underline text-white">
                                            <i class="bi bi-pencil me-1"></i>Edit Credential
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary text-xs" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
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
