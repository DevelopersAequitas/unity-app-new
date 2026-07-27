@extends('admin.layouts.app')

@section('title', 'Introduction Requests')

@include('admin.partials.grid-head')

@section('content')
    <form id="introRequestsFiltersForm" method="GET" action="{{ route('admin.introduction-requests.index') }}"></form>

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Introduction Requests</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review and approve member introducer requests.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">
                Pending: {{ number_format($introductionRequests->total()) }}
            </span>
        </div>

        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div class="md:col-span-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="introRequestsFiltersForm" value="{{ $filters['search'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search requester or introducer name, email, company">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.introduction-requests.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Member</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Introducer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Requested At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($introductionRequests as $introRequest)
                            @php
                                $requester  = $introRequest->requester;
                                $introducer = $introRequest->introducer;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($requester)
                                        @include('admin.partials.peer_identity', ['user' => $requester])
                                        <div class="t3 text-[11px] mt-0.5">{{ $requester->email ?? '—' }}</div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($introducer)
                                        @include('admin.partials.peer_identity', ['user' => $introducer])
                                        <div class="t3 text-[11px] mt-0.5">{{ $introducer->email ?? '—' }}</div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $introRequest->requested_at?->format('d M Y, h:i A') ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <form method="POST" action="{{ route('admin.introduction-requests.approve', $introRequest->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Approve this introduction request?')">
                                                Approve
                                            </button>
                                        </form>

                                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $introRequest->id }}">
                                            Reject
                                        </button>
                                    </div>

                                    {{-- Reject Modal --}}
                                    <div class="modal fade" id="rejectModal{{ $introRequest->id }}" tabindex="-1" aria-labelledby="rejectModalLabel{{ $introRequest->id }}" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="{{ route('admin.introduction-requests.reject', $introRequest->id) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="rejectModalLabel{{ $introRequest->id }}">Reject Introduction Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-left">
                                                        <p class="text-muted small mb-3">Rejecting will not change the requester's introducer assignment.</p>
                                                        <div class="mb-3">
                                                            <label for="admin_note_{{ $introRequest->id }}" class="form-label">Admin Note <span class="text-muted">(optional)</span></label>
                                                            <textarea id="admin_note_{{ $introRequest->id }}" name="admin_note" class="form-control" rows="3" maxlength="1000" placeholder="Reason for rejection…"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-8 text-xs t3">No pending introduction requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $introductionRequests->links() }}
            </div>
        </div>
    </div>
@endsection

