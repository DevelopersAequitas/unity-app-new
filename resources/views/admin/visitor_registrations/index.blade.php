@extends('admin.layouts.app')

@section('title', 'Visitor Registrations')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayName = function (?string $display, ?string $first, ?string $last, ?string $name = null): string {
            if (! empty($name)) {
                return $name;
            }
            if ($display) {
                return $display;
            }
            $computed = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $computed !== '' ? $computed : '—';
        };

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

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };
    @endphp

    <form id="visitorRegistrationsFiltersForm" method="GET" action="{{ route('admin.visitor-registrations.index') }}"></form>
    <form id="bulkDeleteForm" method="POST" action="{{ route('admin.visitor-registrations.bulk-destroy') }}">
        @csrf
    </form>

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="mb-0 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Visitor Registrations</h2>
                <span id="grid-total" class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($registrations->total()) }}</span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition cursor-pointer" data-bs-toggle="modal" data-bs-target="#importVisitorModal">
                    Import Bulk CSV
                </button>
                <a href="{{ route('admin.visitor-registrations.export', request()->query()) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition no-underline">
                    Export Bulk CSV
                </a>
                <button type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition cursor-pointer" data-bs-toggle="modal" data-bs-target="#addVisitorModal">
                    Add Visitor
                </button>
                <button type="submit" form="bulkDeleteForm" id="bulkDeleteBtn" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition disabled:opacity-50 cursor-pointer" disabled
                    onclick="return confirm('Are you sure you want to delete the selected visitor registrations? This cannot be undone.')">
                    Delete Selected
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="visitorRegistrationsFiltersForm" value="{{ $filters['search'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search visitor/peer/event">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="visitorRegistrationsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all" @selected($filters['status'] === 'all')>All Statuses</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                    <select name="circle_id" form="visitorRegistrationsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="clearAdminFilters(event, 'visitorRegistrationsFiltersForm')" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center w-full">Clear</button>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-[1400px] w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center sticky left-0 z-10" style="width:40px; min-width:40px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.08);">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" title="Select All">
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-[40px] z-10" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Mobile</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Business Category</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1 text-center t3 sticky left-0 z-10 surface-2" style="width:40px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.08);">—</th>
                            <th class="px-2 py-1 sticky left-[40px] z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                <input type="text" name="peer_q" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Peer Search" value="{{ $filters['peer_q'] }}">
                            </th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1">
                                <input type="text" name="visitor_name" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Visitor Name" value="{{ $filters['visitor_name'] }}">
                            </th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1">
                                <input type="text" name="visitor_mobile" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Visitor Mobile" value="{{ $filters['visitor_mobile'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <input type="text" name="visitor_business" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Visitor Business" value="{{ $filters['visitor_business'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <input type="text" name="visitor_city" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Visitor City" value="{{ $filters['visitor_city'] }}">
                            </th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1">
                                <input type="text" name="event_name" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Event Name" value="{{ $filters['event_name'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <select name="status" form="visitorRegistrationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                                    <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                                </select>
                            </th>
                            <th class="px-2 py-1 text-center t3">—</th>
                            <th class="px-2 py-1">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'visitorRegistrationsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($registrations as $registration)
                            @php
                                $member = $registration->user;
                                $memberName = $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null, $member->name ?? null);
                                $memberCompany = $member->company_name ?? $member->company ?? $member->business_name ?? '—';
                                $memberCity = $member->city ?? '—';
                                $memberCircles = $member
                                    ? $member->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ')
                                    : '';
                                $memberCircle = $memberCircles !== '' ? $memberCircles : '—';
                                $visitorEmail = $registration->visitor_email ?? $registration->email ?? '—';
                                $visitorCategory = $registration->business_category ?? $registration->category ?? '—';
                                $eventName = $registration->event_name ?? ($registration->event_type ? ucfirst($registration->event_type) : '—');
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-center sticky left-0 z-10 surface" style="width:40px; min-width:40px; box-shadow: none;">
                                    <input type="checkbox" name="ids[]" value="{{ $registration->id }}" form="bulkDeleteForm" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 row-checkbox">
                                </td>
                                <td class="px-3 py-2.5 text-xs sticky left-[40px] z-10 surface" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                    @if ($member)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($memberName) }}">
                                                {{ $getInitials($memberName) }}
                                            </div>
                                            <a href="#"
                                               onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $member->id }}', event);"
                                               class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $memberName }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$memberCompany" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$memberCity" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$memberCircle" /></td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1"><x-admin-grid-text :text="$registration->visitor_full_name ?? '—'" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$visitorEmail" /></td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $registration->visitor_mobile ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$registration->visitor_business ?? '—'" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$registration->visitor_city ?? '—'" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$visitorCategory" /></td>
                                <td class="px-3 py-2.5 text-xs t1 font-medium"><x-admin-grid-text :text="$eventName" /></td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($registration->status === 'approved')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Approved</span>
                                    @elseif($registration->status === 'rejected')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">Rejected</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">Pending</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($registration->created_at ?? null) }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                         <a href="{{ route('admin.visitor-registrations.export-single', $registration->id) }}" class="p-1 rounded border bs t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center justify-center" title="Export Single CSV" aria-label="Export Single CSV">
                                             <i class="bi bi-download" aria-hidden="true"></i>
                                         </a>
                                        @if ($registration->status === 'pending')
                                            <form method="POST" action="{{ route('admin.visitor-registrations.approve', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Approve this visitor registration?')">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.visitor-registrations.reject', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Reject this visitor registration?')">Reject</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.visitor-registrations.destroy', $registration->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this visitor registration? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring" title="Delete">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-8 text-xs t3">No visitor registrations found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $registrations->links() }}
            </div>
        </div>
    </div>

    <!-- Add Visitor Modal -->
    <div class="modal fade" id="addVisitorModal" tabindex="-1" aria-labelledby="addVisitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow border-0 rounded-4">
                <form action="{{ route('admin.visitor-registrations.store') }}" method="POST">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark" id="addVisitorModalLabel">
                            <i class="bi bi-person-plus-fill me-2 text-primary"></i> Add Visitor
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="row g-3">
                            <!-- Peer selection (MANDATORY) -->
                            <div class="col-md-12">
                                <label for="user_id" class="form-label fw-medium small text-muted">Peer (Who invited this visitor?) <span class="text-danger">*</span></label>
                                <select name="user_id" id="user_id" class="form-select" required>
                                    <option value="">Select Peer...</option>
                                    @foreach($users as $user)
                                        @php
                                            $uName = $displayName($user->display_name ?? null, $user->first_name ?? null, $user->last_name ?? null, $user->name ?? null);
                                            $uCompany = $user->company_name ?? $user->company ?? 'No Company';
                                        @endphp
                                        <option value="{{ $user->id }}" @selected(old('user_id') === $user->id)>
                                            {{ $uName }} ({{ $uCompany }} - {{ $user->phone }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Visitor Name, Mobile, Email (MANDATORY) -->
                            <div class="col-md-4">
                                <label for="visitor_full_name" class="form-label fw-medium small text-muted">Visitor Name <span class="text-danger">*</span></label>
                                <input type="text" name="visitor_full_name" id="visitor_full_name" value="{{ old('visitor_full_name') }}" class="form-control" placeholder="Visitor Name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="visitor_mobile" class="form-label fw-medium small text-muted">Visitor Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="visitor_mobile" id="visitor_mobile" value="{{ old('visitor_mobile') }}" class="form-control" placeholder="Visitor Mobile" required>
                            </div>
                            <div class="col-md-4">
                                <label for="visitor_email" class="form-label fw-medium small text-muted">Visitor Email <span class="text-danger">*</span></label>
                                <input type="email" name="visitor_email" id="visitor_email" value="{{ old('visitor_email') }}" class="form-control" placeholder="Visitor Email" required>
                            </div>

                            <!-- Event Details (OPTIONAL) -->
                            <div class="col-md-4">
                                <label for="event_type" class="form-label fw-medium small text-muted">Event Type</label>
                                <select name="event_type" id="event_type" class="form-select">
                                    <option value="Physical" @selected(old('event_type', 'Physical') === 'Physical')>Physical</option>
                                    <option value="Virtual" @selected(old('event_type') === 'Virtual')>Virtual</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="event_name" class="form-label fw-medium small text-muted">Event Name</label>
                                <input type="text" name="event_name" id="event_name" value="{{ old('event_name') }}" class="form-control" placeholder="e.g. buildcon circle meet">
                            </div>
                            <div class="col-md-4">
                                <label for="event_date" class="form-label fw-medium small text-muted">Event Date</label>
                                <input type="date" name="event_date" id="event_date" value="{{ old('event_date', now()->format('Y-m-d')) }}" class="form-control">
                            </div>

                            <!-- Other Details (OPTIONAL) -->
                            <div class="col-md-6">
                                <label for="visitor_city" class="form-label fw-medium small text-muted">Visitor City</label>
                                <input type="text" name="visitor_city" id="visitor_city" value="{{ old('visitor_city') }}" class="form-control" placeholder="Visitor City">
                            </div>
                            <div class="col-md-6">
                                <label for="visitor_business" class="form-label fw-medium small text-muted">Visitor Business</label>
                                <input type="text" name="visitor_business" id="visitor_business" value="{{ old('visitor_business') }}" class="form-control" placeholder="e.g. interior designer">
                            </div>
                            <div class="col-md-6">
                                <label for="visitor_designation" class="form-label fw-medium small text-muted">Visitor Designation</label>
                                <input type="text" name="visitor_designation" id="visitor_designation" value="{{ old('visitor_designation') }}" class="form-control" placeholder="Designation">
                            </div>
                            <div class="col-md-12">
                                <label for="visitor_business_brief" class="form-label fw-medium small text-muted">Visitor Business Brief</label>
                                <textarea name="visitor_business_brief" id="visitor_business_brief" class="form-control" rows="2" placeholder="Brief description of visitor's business">{{ old('visitor_business_brief') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Visitor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Visitor Modal -->
    <div class="modal fade" id="importVisitorModal" tabindex="-1" aria-labelledby="importVisitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow border-0 rounded-4">
                <form action="{{ route('admin.visitor-registrations.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark" id="importVisitorModalLabel">
                            <i class="bi bi-file-earmark-spreadsheet-fill me-2 text-success"></i> Import Visitors in Bulk
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="csv_file" class="form-label fw-medium small text-muted">Select CSV File <span class="text-danger">*</span></label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv, text/csv, text/plain" required>
                                <div class="form-text mt-1">
                                    Upload a CSV file containing visitor records. 
                                    <a href="{{ route('admin.visitor-registrations.sample-csv') }}" class="fw-semibold text-primary text-decoration-none ms-1">
                                        <i class="bi bi-download me-0.5"></i> Download Sample CSV
                                    </a>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="default_user_id" class="form-label fw-medium small text-muted">Default Peer (Inviter)</label>
                                <select name="default_user_id" id="default_user_id" class="form-select">
                                    <option value="">Auto-resolve from CSV (peer_email or peer_phone)...</option>
                                    @foreach($users as $user)
                                        @php
                                            $uName = $displayName($user->display_name ?? null, $user->first_name ?? null, $user->last_name ?? null, $user->name ?? null);
                                            $uCompany = $user->company_name ?? $user->company ?? 'No Company';
                                        @endphp
                                        <option value="{{ $user->id }}">
                                            {{ $uName }} ({{ $uCompany }} - {{ $user->phone }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Used if a row lacks `peer_email` or `peer_phone`.</div>
                            </div>
                        </div>

                        <!-- Column Requirements -->
                        <div class="bg-light p-3 rounded-3 border mt-3">
                            <div class="fw-semibold text-dark mb-1 small"><i class="bi bi-info-circle me-1 text-primary"></i> Mandatory Columns in CSV:</div>
                            <ul class="mb-3 text-muted small ps-3">
                                <li><strong>visitor_name</strong> (or visitor_full_name)</li>
                                <li><strong>visitor_mobile</strong> (or mobile)</li>
                                <li><strong>visitor_email</strong> (or email)</li>
                                <li><strong>peer_email</strong> or <strong>peer_phone</strong> (or select Default Peer above)</li>
                            </ul>

                            <div class="fw-semibold text-dark mb-2 small"><i class="bi bi-table me-1 text-success"></i> CSV Template Structure Preview:</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm fs-8 text-muted mb-0 bg-white" style="font-size: 0.75rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>peer_email <span class="text-danger">*</span></th>
                                            <th>visitor_name <span class="text-danger">*</span></th>
                                            <th>visitor_mobile <span class="text-danger">*</span></th>
                                            <th>visitor_email <span class="text-danger">*</span></th>
                                            <th>event_type</th>
                                            <th>event_name</th>
                                            <th>event_date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>peer@example.com</td>
                                            <td>Arpan Pandya</td>
                                            <td>9876543210</td>
                                            <td>arpan@example.com</td>
                                            <td>Physical</td>
                                            <td>Buildcon Meeting</td>
                                            <td>{{ now()->format('Y-m-d') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i> Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const selectAll   = document.getElementById('selectAll');
        const bulkBtn     = document.getElementById('bulkDeleteBtn');
        const checkboxes  = () => document.querySelectorAll('.row-checkbox');

        function syncBtn() {
            const checked = document.querySelectorAll('.row-checkbox:checked').length;
            bulkBtn.disabled = checked === 0;
            if (bulkBtn.disabled) {
                bulkBtn.title = 'Select at least one row to delete';
            } else {
                bulkBtn.title = `Delete ${checked} selected record(s)`;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(cb => { cb.checked = selectAll.checked; });
                syncBtn();
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('row-checkbox')) {
                syncBtn();
                if (selectAll) {
                    const all = checkboxes();
                    selectAll.checked = all.length > 0 && [...all].every(cb => cb.checked);
                }
            }
        });

        syncBtn();
    })();
</script>
@endpush
