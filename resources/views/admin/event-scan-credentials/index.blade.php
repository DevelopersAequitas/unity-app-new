@extends('admin.layouts.app')

@section('title', 'Event Scan Credentials')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1 font-bold text-slate-800">Event Scan Credentials</h1>
            <p class="text-xs text-muted mb-0">Manage scanner app login credentials and multi-event scanner access</p>
        </div>
        <a href="{{ route('admin.event-scan-credentials.create') }}" class="btn btn-primary px-3 py-2 text-xs font-semibold shadow-sm rounded-3">
            <i class="bi bi-plus-lg me-1"></i>Create Credential
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show text-xs py-2 px-3 mb-3" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding:0.75rem;"></button>
        </div>
    @endif

    <!-- Search & Filter Card -->
    <form method="GET" action="{{ route('admin.event-scan-credentials.index') }}" class="card card-body border-0 shadow-sm rounded-3 mb-3 py-2 px-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input class="form-control border-start-0 bg-light text-xs" name="search" value="{{ request('search') }}" placeholder="Search person name, username, hotel, or event...">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-indigo text-xs px-3">Filter</button>
                <a href="{{ route('admin.event-scan-credentials.index') }}" class="btn btn-sm btn-outline-secondary text-xs px-3">Reset</a>
            </div>
        </div>
    </form>

    <!-- Table Card -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-xs">
                <thead class="bg-light text-muted uppercase tracking-wider font-semibold border-bottom">
                    <tr>
                        <th class="py-3 px-3">Person</th>
                        <th class="py-3 px-3">Username / Login ID</th>
                        <th class="py-3 px-3">Hotel / Venue</th>
                        <th class="py-3 px-3">Assigned Events</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Last Login</th>
                        <th class="py-3 px-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
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
                        <tr>
                            <td class="py-3 px-3">
                                <button type="button" class="btn p-0 border-0 text-start bg-transparent" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" title="Click to view full user details">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-indigo-600 text-white d-flex align-items-center justify-content-center font-bold text-xs shadow-sm" style="width: 34px; height: 34px; flex-shrink: 0; background: linear-gradient(135deg, #4f46e5, #7c3aed);">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-indigo-600 text-sm hover:underline cursor-pointer">
                                                {{ $credential->name }}
                                            </div>
                                            <div class="text-muted text-[11px]">
                                                Click to view details
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-[11px] border border-slate-200">
                                    {{ $credential->username }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-slate-700 font-medium">
                                <i class="bi bi-building text-slate-400 me-1"></i>{{ $credential->hotel_name }}
                            </td>
                            <td class="py-3 px-3" style="max-width: 260px;">
                                @if($assignedEvents->count() > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($assignedEvents->take(3) as $ev)
                                            <span class="badge" style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;" title="{{ $ev->title }}">
                                                {{ Str::limit($ev->title, 22) }}
                                            </span>
                                        @endforeach
                                        @if($assignedEvents->count() > 3)
                                            <span class="badge" style="background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;" title="Click user to see all {{ $assignedEvents->count() }} events">
                                                +{{ $assignedEvents->count() - 3 }} more
                                            </span>
                                        @endif
                                    </div>
                                @elseif($credential->event)
                                    <span class="badge" style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;">
                                        {{ $credential->event->title }}
                                    </span>
                                @else
                                    <span class="text-muted text-[11px]">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                @if($credential->is_active)
                                    <span class="badge" style="background-color: #dcfce7; color: #15803d; border: 1px solid #86efac; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;">
                                        Active
                                    </span>
                                @else
                                    <span class="badge" style="background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-slate-600 whitespace-nowrap">
                                @if($credential->last_login_at)
                                    <i class="bi bi-clock-history text-slate-400 me-1"></i>{{ $credential->last_login_at->format('d M Y, h:i A') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-end">
                                <div class="inline-flex items-center gap-1">
                                    <button class="btn btn-sm btn-outline-info text-[11px] py-1 px-2.5 rounded-2" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                        View
                                    </button>
                                    <a href="{{ route('admin.event-scan-credentials.edit', $credential->id) }}" class="btn btn-sm btn-outline-primary text-[11px] py-1 px-2.5 rounded-2">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.event-scan-credentials.toggle', $credential->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm {{ $credential->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} text-[11px] py-1 px-2.5 rounded-2">
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
                                    <div class="modal-header bg-dark text-white py-3 px-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-indigo-500 text-white d-flex align-items-center justify-content-center font-bold text-base shadow" style="width: 44px; height: 44px; background: linear-gradient(135deg, #6366f1, #a855f7);">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <h5 class="modal-title mb-0 fs-6 font-bold">{{ $credential->name }}</h5>
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
                                                                    <a href="{{ route('admin.events.show', $eventItem->id) }}" class="btn btn-xs btn-outline-indigo px-2 py-0.5 text-[10px]" target="_blank">View Event</a>
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
                                                            <a href="{{ route('admin.users.show', $peerUser->id) }}" class="btn btn-sm btn-indigo text-xs" target="_blank">
                                                                View Peer Profile <i class="bi bi-arrow-up-right me-1"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Modal Footer -->
                                    <div class="modal-footer bg-light py-2 px-4">
                                        <a href="{{ route('admin.event-scan-credentials.edit', $credential->id) }}" class="btn btn-sm btn-primary text-xs">
                                            <i class="bi bi-pencil me-1"></i>Edit Credential
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary text-xs" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-person-x text-3xl d-block mb-2 text-slate-300"></i>
                                No scanner credentials found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $credentials->links() }}</div>
</div>
@endsection
