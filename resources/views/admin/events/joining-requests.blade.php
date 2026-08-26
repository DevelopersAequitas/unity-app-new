@extends('admin.layouts.app')

@section('title', 'Event Joining Requests')

@include('admin.partials.grid-head')

@php
    $getInitials = function (?string $name): string {
        if (! $name) return 'P';
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };

    $getAvatarBg = function (?string $name): string {
        if (! $name) return '#6366f1';
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };
@endphp

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0">Event Joining Requests</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage and review requests from members wanting to join events outside their assigned circle</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.events.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold surface hover:surface-2 transition text-slate-700 no-underline flex items-center gap-1.5">
                <i class="bi bi-calendar-event"></i> All Events
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-medium flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-600 hover:text-emerald-900" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
        <a href="{{ request()->fullUrlWithQuery(['status' => 'pending']) }}" class="kpi-card no-underline @if(($status ?? request('status', 'pending')) === 'pending') border-amber-500 ring-1 ring-amber-500 @endif">
            <div class="kpi-top">
                <div class="kpi-title">Pending</div>
                <div class="kpi-icon bg-amber-50 text-amber-600"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="kpi-num text-amber-600">{{ number_format($summary['pending'] ?? 0) }}</div>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['status' => 'approved']) }}" class="kpi-card no-underline @if(($status ?? request('status')) === 'approved') border-emerald-500 ring-1 ring-emerald-500 @endif">
            <div class="kpi-top">
                <div class="kpi-title">Approved</div>
                <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-check-circle"></i></div>
            </div>
            <div class="kpi-num text-emerald-600">{{ number_format($summary['approved'] ?? 0) }}</div>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['status' => 'rejected']) }}" class="kpi-card no-underline @if(($status ?? request('status')) === 'rejected') border-rose-500 ring-1 ring-rose-500 @endif">
            <div class="kpi-top">
                <div class="kpi-title">Rejected</div>
                <div class="kpi-icon bg-rose-50 text-rose-600"><i class="bi bi-x-circle"></i></div>
            </div>
            <div class="kpi-num text-rose-600">{{ number_format($summary['rejected'] ?? 0) }}</div>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['status' => 'checked_in']) }}" class="kpi-card no-underline @if(($status ?? request('status')) === 'checked_in') border-sky-500 ring-1 ring-sky-500 @endif">
            <div class="kpi-top">
                <div class="kpi-title">Checked In</div>
                <div class="kpi-icon bg-sky-50 text-sky-600"><i class="bi bi-person-check"></i></div>
            </div>
            <div class="kpi-num text-sky-600">{{ number_format($summary['checked_in'] ?? 0) }}</div>
        </a>

        <a href="{{ request()->fullUrlWithQuery(['status' => 'all']) }}" class="kpi-card no-underline @if(($status ?? request('status')) === 'all') border-indigo-500 ring-1 ring-indigo-500 @endif">
            <div class="kpi-top">
                <div class="kpi-title">Total Requests</div>
                <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-collection"></i></div>
            </div>
            <div class="kpi-num text-indigo-600">{{ number_format($summary['total'] ?? 0) }}</div>
        </a>
    </div>

    <!-- Search & Filters Toolbar -->
    <div class="p-3 rounded-lg border bs surface-2 mb-4">
        <form method="GET" action="{{ route('admin.event-joining-requests.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                <input type="text" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search member, email, company, event...">
            </div>
            <div class="w-40">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                <select class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring js-no-select2 js-no-searchable-select" name="status">
                    @foreach(['all' => 'All Statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'checked_in' => 'Checked In', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(($status ?? request('status', 'all')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-44">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Event</label>
                <select class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring js-no-select2 js-no-searchable-select" name="event_id">
                    <option value="">All Events</option>
                    @foreach($events as $event)
                        <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From Date</label>
                <input type="date" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" name="date_from" value="{{ request('date_from') }}">
            </div>
            <div class="w-36">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To Date</label>
                <input type="date" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" name="date_to" value="{{ request('date_to') }}">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm cursor-pointer">Filter</button>
                <a href="{{ route('admin.event-joining-requests.index') }}" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border bs surface t2 hover:t1 hover:surface-3 transition text-center no-underline shadow-sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- Table Wrapper -->
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-[1250px] w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width: 160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Requested By</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 160px;">Event Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event Type</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event Circle</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Event Date/Time</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Location/Mode</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 160px;">Reason</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 100px;">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 130px;">Requested At</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Admin Note</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="min-width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                @forelse($requests as $joinRequest)
                    @php
                        $user = $joinRequest->user;
                        $userName = $user ? ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) : '—';
                        $userCompany = $user->company_name ?? $user->company ?? $user->business_name ?? '—';
                        $userCity = $user->city ?? '—';
                        $userCircles = $user
                            ? $user->circleMemberships->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ')
                            : '';
                        $userCircleName = $userCircles !== '' ? $userCircles : '—';
                        $event = $joinRequest->event;
                        $occurrence = $joinRequest->occurrence;
                        $registration = $joinRequest->registration;
                        $modalId = 'joiningRequestModal'.$joinRequest->id;

                        // Clean and deduplicate repeated text in location
                        $rawLoc = $event?->location_text ?? '';
                        if ($rawLoc) {
                            $parts = array_filter(array_map('trim', explode(',', $rawLoc)));
                            $rawLoc = implode(', ', array_unique($parts));
                        }
                    @endphp
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface" style="min-width: 160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                            @if ($user)
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($userName) }}">
                                        {{ $getInitials($userName) }}
                                    </div>
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="text-indigo-600 font-semibold hover:underline no-underline text-xs">
                                        {{ $userName }}
                                    </a>
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $userCompany }}</td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $userCity }}</td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $userCircleName }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <div class="font-semibold t1">{{ $event?->title ?? '—' }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">{{ ucfirst(str_replace('_', ' ', $event?->event_type ?? 'Event')) }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-indigo-600 font-medium">
                            {{ $event?->circle?->name ?? 'Global Circle' }}
                        </td>
                        <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                            {{ optional($occurrence?->start_at)->format('d M Y, h:i A') }} @if($occurrence?->end_at) - {{ optional($occurrence?->end_at)->format('h:i A') }} @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t2">
                            @if($event?->mode || $rawLoc)
                                <div class="max-w-xs truncate" title="{{ $rawLoc }}">
                                    {{ ucfirst((string) ($event?->mode ?? 'offline')) }} @if($rawLoc) &bull; {{ $rawLoc }} @endif
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t2" style="max-width: 220px;">
                            @if($joinRequest->request_reason)
                                <span class="italic t1" title="{{ $joinRequest->request_reason }}">“{{ $joinRequest->request_reason }}”</span>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                            @if($joinRequest->status === 'pending')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending
                                </span>
                            @elseif($joinRequest->status === 'approved')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                                </span>
                            @elseif($joinRequest->status === 'rejected')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                                </span>
                            @elseif($joinRequest->status === 'checked_in')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>Checked In
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($joinRequest->status) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                            <div class="font-medium t1">{{ optional($joinRequest->created_at)->format('d M Y') }}</div>
                            <div class="text-[11px] t3">{{ optional($joinRequest->created_at)->format('h:i A') }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs t2">
                            @if($joinRequest->admin_note)
                                <div class="max-w-xs truncate" title="{{ $joinRequest->admin_note }}">
                                    {{ $joinRequest->admin_note }}
                                </div>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap" style="min-width: 120px;">
                            <button class="inline-flex items-center justify-center px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-semibold no-underline hover:bg-indigo-100 transition whitespace-nowrap cursor-pointer" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                                <i class="bi bi-eye me-1"></i> View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="text-center text-slate-500 py-8">
                            <i class="bi bi-inbox text-3xl text-slate-300 block mb-2"></i>
                            <span>No event joining requests found matching your filters.</span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t border-slate-200 bg-slate-50 flex justify-between items-center">
            {{ $requests->links() }}
        </div>
    </div>

    <!-- Details Modals -->
    @foreach($requests as $joinRequest)
        @php
            $user = $joinRequest->user;
            $userCircles = $user
                ? $user->circleMemberships->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ')
                : '';
            $userCircleName = $userCircles !== '' ? $userCircles : null;
            $event = $joinRequest->event;
            $occurrence = $joinRequest->occurrence;
            $registration = $joinRequest->registration;
            $modalId = 'joiningRequestModal'.$joinRequest->id;
        @endphp

        <!-- View Details Modal -->
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden">
                    <div class="modal-header bg-slate-900 text-white border-0 px-4 py-3">
                        <h5 class="modal-title text-base font-bold flex items-center gap-2">
                            <i class="bi bi-card-heading text-indigo-400"></i> Event Joining Request Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 bg-slate-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Request Info -->
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                <h6 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                                    <i class="bi bi-info-circle"></i> Request Info
                                </h6>
                                <dl class="grid grid-cols-3 gap-2 text-xs mb-0">
                                    <dt class="text-slate-500 font-medium">Status:</dt>
                                    <dd class="col-span-2 font-semibold capitalize text-slate-800">{{ $joinRequest->status }}</dd>

                                    <dt class="text-slate-500 font-medium">Reason:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $joinRequest->request_reason ?: 'None provided' }}</dd>

                                    <dt class="text-slate-500 font-medium">Admin Note:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $joinRequest->admin_note ?: '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">Requested At:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ optional($joinRequest->created_at)->format('d M Y h:i A') }}</dd>

                                    @if($joinRequest->approved_at)
                                        <dt class="text-slate-500 font-medium">Approved At:</dt>
                                        <dd class="col-span-2 text-emerald-700 font-medium">{{ optional($joinRequest->approved_at)->format('d M Y h:i A') }} (by {{ $joinRequest->approvedBy?->display_name ?? 'Admin' }})</dd>
                                    @endif

                                    @if($joinRequest->rejected_at)
                                        <dt class="text-slate-500 font-medium">Rejected At:</dt>
                                        <dd class="col-span-2 text-rose-700 font-medium">{{ optional($joinRequest->rejected_at)->format('d M Y h:i A') }} (by {{ $joinRequest->rejectedBy?->display_name ?? 'Admin' }})</dd>
                                    @endif
                                </dl>
                            </div>

                            <!-- Member Profile -->
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                <h6 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                                    <i class="bi bi-person"></i> Member Profile
                                </h6>
                                <dl class="grid grid-cols-3 gap-2 text-xs mb-0">
                                    <dt class="text-slate-500 font-medium">Name:</dt>
                                    <dd class="col-span-2 font-bold text-slate-900">{{ $user?->display_name ?: trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">Email:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $user?->email ?? '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">Phone:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $user?->phone ?? '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">Company:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $user?->company_name ?? '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">User Circle:</dt>
                                    <dd class="col-span-2 text-indigo-600 font-semibold">{{ $userCircleName ?? '—' }}</dd>
                                </dl>
                            </div>

                            <!-- Event Details -->
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                <h6 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                                    <i class="bi bi-calendar-event"></i> Event Details
                                </h6>
                                <dl class="grid grid-cols-3 gap-2 text-xs mb-0">
                                    <dt class="text-slate-500 font-medium">Title:</dt>
                                    <dd class="col-span-2 font-bold text-slate-900">{{ $event?->title ?? '—' }}</dd>

                                    <dt class="text-slate-500 font-medium">Event Circle:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $event?->circle?->name ?? 'Global Circle' }}</dd>

                                    <dt class="text-slate-500 font-medium">Date & Time:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ optional($occurrence?->start_at)->format('d M Y h:i A') }}</dd>

                                    <dt class="text-slate-500 font-medium">Mode & Venue:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ ucfirst((string) ($event?->mode ?? 'offline')) }} @if($event?->location_text) &bull; {{ $event->location_text }} @endif</dd>
                                </dl>
                            </div>

                            <!-- Registration Details -->
                            <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                <h6 class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-3 flex items-center gap-1.5">
                                    <i class="bi bi-ticket-perforated"></i> Registration Status
                                </h6>
                                <dl class="grid grid-cols-3 gap-2 text-xs mb-0">
                                    <dt class="text-slate-500 font-medium">Reg Status:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $registration ? 'Registered' : 'Not Registered Yet' }}</dd>

                                    <dt class="text-slate-500 font-medium">Payment:</dt>
                                    <dd class="col-span-2 text-slate-800 capitalize">{{ $registration?->payment_status ?? 'N/A' }}</dd>

                                    <dt class="text-slate-500 font-medium">Check-in:</dt>
                                    <dd class="col-span-2 text-slate-800">{{ $registration?->checked_in_at ? 'Checked In' : 'Not Checked In' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-slate-100 border-t border-slate-200 px-4 py-2.5">
                        <button type="button" class="px-4 py-1.5 rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 text-xs font-semibold transition" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        @if($joinRequest->status === 'pending')
            <!-- Approve Modal -->
            <div class="modal fade event-request-decision-modal" id="approve{{ $joinRequest->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden" method="POST" action="{{ route('admin.event-joining-requests.approve', $joinRequest->id) }}">
                        @csrf
                        <div class="modal-header bg-emerald-600 text-white border-0 px-4 py-3 flex items-center gap-2">
                            <i class="bi bi-check-circle-fill text-xl"></i>
                            <h5 class="modal-title text-base font-bold m-0">Approve Event Joining Request</h5>
                            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <p class="text-xs text-slate-600 mb-3">Are you sure you want to approve this member to register and attend this event?</p>
                            <label class="block text-xs font-bold text-slate-700 mb-1" for="approveNote{{ $joinRequest->id }}">Admin Note</label>
                            <textarea class="w-full p-2.5 text-xs rounded-xl border border-slate-300 focus-ring" id="approveNote{{ $joinRequest->id }}" name="admin_note" rows="3">Approved for cross-circle event registration.</textarea>
                        </div>
                        <div class="modal-footer bg-slate-50 border-t border-slate-200 px-4 py-2.5 flex justify-end gap-2">
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 bg-white text-xs font-semibold" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Confirm Approval</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade event-request-decision-modal" id="reject{{ $joinRequest->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden" method="POST" action="{{ route('admin.event-joining-requests.reject', $joinRequest->id) }}">
                        @csrf
                        <div class="modal-header bg-rose-600 text-white border-0 px-4 py-3 flex items-center gap-2">
                            <i class="bi bi-x-circle-fill text-xl"></i>
                            <h5 class="modal-title text-base font-bold m-0">Reject Event Joining Request</h5>
                            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-white">
                            <p class="text-xs text-slate-600 mb-3">Please state the reason for rejecting this member's joining request.</p>
                            <label class="block text-xs font-bold text-slate-700 mb-1" for="rejectNote{{ $joinRequest->id }}">Admin Note <span class="text-rose-500">*</span></label>
                            <textarea class="w-full p-2.5 text-xs rounded-xl border border-slate-300 focus-ring" id="rejectNote{{ $joinRequest->id }}" name="admin_note" rows="3" required placeholder="Reason for rejection..."></textarea>
                        </div>
                        <div class="modal-footer bg-slate-50 border-t border-slate-200 px-4 py-2.5 flex justify-end gap-2">
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 bg-white text-xs font-semibold" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

</div>
@endsection

