@extends('admin.layouts.app')

@section('title', 'Inactive Registrations')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $computed = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $computed !== '' ? $computed : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };
    @endphp

    <form id="pendingRegistrationsFiltersForm" method="GET" action="{{ route('admin.pending-registrations.index') }}"></form>

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ session('error') }}</div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-amber-500 uppercase tracking-wider m-0">Inactive Registrations</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review, approve, or reject user registration applications.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                Total: {{ number_format($registrations->total()) }}
            </span>
        </div>

        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-2.5 items-end">
                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="pendingRegistrationsFiltersForm" value="{{ $filters['search'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search name, email, phone, city...">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="pendingRegistrationsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Date</label>
                    <input type="date" name="date" form="pendingRegistrationsFiltersForm" value="{{ $filters['date'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.pending-registrations.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Registered At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Mobile</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company & Designation</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($registrations as $registration)
                            @php
                                $fullName = $displayName($registration->display_name ?? null, $registration->first_name ?? null, $registration->last_name ?? null);
                                $company = $registration->company_name ?? '—';
                                $designation = $registration->designation ?? '—';
                                $city = $registration->city_of_residence ?: ($registration->city ?: '—');
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($registration->created_at ?? null) }}</td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $fullName }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $registration->email }}</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $registration->phone ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $city }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="font-medium t1">{{ $company }}</div>
                                    <div class="t3 text-[11px]">{{ $designation }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($registration->status === 'inactive')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Inactive</span>
                                    @elseif ($registration->status === 'rejected')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">Rejected</span>
                                    @elseif ($registration->status === 'active')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ ucfirst($registration->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition" data-bs-toggle="modal" data-bs-target="#regDetailsModal-{{ $registration->id }}">
                                            Details
                                        </button>
                                        @if ($registration->status === 'inactive')
                                            <form method="POST" action="{{ route('admin.pending-registrations.approve', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Approve this registration request?')">
                                                    Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.pending-registrations.reject', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Reject this registration request?')">
                                                    Reject
                                                </button>
                                            </form>
                                        @elseif ($registration->status === 'rejected')
                                            <form method="POST" action="{{ route('admin.pending-registrations.approve', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition focus-ring" onclick="return confirm('Approve this previously rejected user?')">
                                                    Approve
                                                </button>
                                            </form>
                                        @elseif ($registration->status === 'active')
                                            <form method="POST" action="{{ route('admin.pending-registrations.reject', $registration->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Reject this active user?')">
                                                    Reject
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                    <!-- Registration Details Modal -->
                                    <div class="modal fade" id="regDetailsModal-{{ $registration->id }}" tabindex="-1" aria-labelledby="regDetailsModalLabel-{{ $registration->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="regDetailsModalLabel-{{ $registration->id }}">Registration Details: {{ $fullName }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-left">
                                                    <div class="row g-3">
                                                        <div class="col-md-6"><strong>First Name:</strong> <span class="text-muted">{{ $registration->first_name }}</span></div>
                                                        <div class="col-md-6"><strong>Last Name:</strong> <span class="text-muted">{{ $registration->last_name ?? '—' }}</span></div>
                                                        <div class="col-md-6"><strong>Email:</strong> <span class="text-muted">{{ $registration->email }}</span></div>
                                                        <div class="col-md-6"><strong>Mobile:</strong> <span class="text-muted">{{ $registration->phone ?? '—' }}</span></div>
                                                        <div class="col-md-6"><strong>Company Name:</strong> <span class="text-muted">{{ $registration->company_name ?? '—' }}</span></div>
                                                        <div class="col-md-6"><strong>Designation:</strong> <span class="text-muted">{{ $registration->designation ?? '—' }}</span></div>
                                                        <div class="col-md-6"><strong>City:</strong> <span class="text-muted">{{ $city }}</span></div>
                                                        <div class="col-md-6">
                                                            <strong>Website:</strong> 
                                                            @if($registration->website)
                                                                <a href="{{ $registration->website }}" target="_blank">{{ $registration->website }}</a>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </div>
                                                        <div class="col-md-12">
                                                            <strong>List in Community Directory?</strong> <span class="text-muted">{{ $registration->community_directory_listing ?? 'No' }}</span>
                                                        </div>

                                                        <div class="col-12"><hr class="my-2"></div>

                                                        <div class="col-12">
                                                            <strong>Sustainability Contribution:</strong>
                                                            <div class="p-2 bg-light border rounded mt-1 text-muted" style="white-space: pre-wrap;">{{ $registration->sustainability_contribution ?: 'No contribution specified.' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No inactive or rejected registration requests found.</td>
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
@endsection

