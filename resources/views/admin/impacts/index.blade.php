@extends('admin.layouts.app')

@section('title', 'Impact')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayUser = function ($user): string {
            if (! $user) {
                return '-';
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : ((string) ($user->email ?? '-'));
        };

        $statusBadge = function (?string $status): string {
            return match ($status) {
                'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                default => 'bg-amber-50 text-amber-700 border-amber-200',
            };
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Impact</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage community impact actions, create impact logs, and review submissions.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">Total: {{ number_format($impacts->total()) }}</span>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-4">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Manage Impact Actions --}}
        <div class="rounded-xl border bs surface overflow-hidden space-y-3">
            <div class="px-4 py-3 surface-2 border-b bs">
                <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Manage Impact Actions</h6>
            </div>
            <div class="p-4 space-y-4">
                <form method="POST" action="{{ route('admin.impacts.actions.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="block text-[11px] t3 mb-1 font-medium">Action Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ old('name') }}" required maxlength="255" placeholder="Enter action name">
                    </div>
                    <div class="col-md-3">
                        <label class="block text-[11px] t3 mb-1 font-medium">Impact Score <span class="text-rose-500">*</span></label>
                        <input type="number" name="impact_score" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" min="1" value="{{ old('impact_score', 1) }}" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring">Add Action</button>
                    </div>
                </form>

                <div class="overflow-x-auto relative rounded-lg border bs">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Action Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impact Score</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/50">
                        @forelse($impactActionItems as $actionItem)
                            <tr class="hover:surface-2 transition border-b bs impact-action-row" data-action-index="{{ $loop->index }}">
                                <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $actionItem->name }}</td>
                                <td class="px-3 py-2.5 font-semibold text-indigo-600 text-xs">{{ max(1, (int) ($actionItem->impact_score ?? 1)) }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $actionItem->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200' }}">
                                        {{ $actionItem->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    @if(!empty($actionItem->id))
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 surface-2 transition" type="button" data-bs-toggle="collapse" data-bs-target="#editImpactAction{{ $actionItem->id }}">Edit</button>
                                            <form method="POST" action="{{ route('admin.impacts.actions.destroy', $actionItem->id) }}" class="inline" onsubmit="return confirm('Delete this impact action?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold">Delete</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if(!empty($actionItem->id))
                                <tr class="collapse" id="editImpactAction{{ $actionItem->id }}">
                                    <td colspan="4" class="p-3 surface-2 border-b bs">
                                        <form method="POST" action="{{ route('admin.impacts.actions.update', $actionItem->id) }}" class="row g-2 align-items-end">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-md-5">
                                                <label class="block text-[11px] t3 mb-1 font-medium">Action Name</label>
                                                <input type="text" name="name" class="w-full px-2.5 py-1 rounded-md border bs surface text-xs t1 outline-none focus-ring" value="{{ $actionItem->name }}" required maxlength="255">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="block text-[11px] t3 mb-1 font-medium">Impact Score</label>
                                                <input type="number" name="impact_score" min="1" class="w-full px-2.5 py-1 rounded-md border bs surface text-xs t1 outline-none focus-ring" value="{{ max(1, (int) ($actionItem->impact_score ?? 1)) }}" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="block text-[11px] t3 mb-1 font-medium">Status</label>
                                                <select name="is_active" class="w-full px-2.5 py-1 rounded-md border bs surface text-xs t1 outline-none focus-ring">
                                                    <option value="1" @selected($actionItem->is_active)>Active</option>
                                                    <option value="0" @selected(! $actionItem->is_active)>Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring">Save</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-8 text-xs t3">No actions found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($impactActionItems->count() > 6)
                    <div>
                        <button type="button" id="impactActionsViewMoreBtn" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 surface-2 transition">
                            View More
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Create Impact --}}
        <div class="rounded-xl border bs surface overflow-hidden space-y-3">
            <div class="px-4 py-3 surface-2 border-b bs">
                <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Create Impact</h6>
            </div>
            <div class="p-4">
                <form method="POST" action="{{ route('admin.impacts.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="block text-[11px] t3 mb-1 font-medium">Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ old('date', now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Action <span class="text-rose-500">*</span></label>
                            <select name="action" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" required>
                                <option value="">Select action</option>
                                @foreach($impactActions as $action)
                                    <option value="{{ $action }}" @selected(old('action') === $action)>{{ $action }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="block text-[11px] t3 mb-1 font-medium">Impacted Peer <span class="text-rose-500">*</span></label>
                            <select name="impacted_peer_id" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" required>
                                <option value="">Select peer</option>
                                @foreach($peers as $peer)
                                    <option value="{{ $peer->id }}" @selected(old('impacted_peer_id') === (string) $peer->id)>
                                        {{ $peer->adminFounderDropdownLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[11px] t3 mb-1 font-medium">Story to Share <span class="text-rose-500">*</span></label>
                            <textarea name="story_to_share" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" rows="3" required>{{ old('story_to_share') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="block text-[11px] t3 mb-1 font-medium">Additional Remarks</label>
                            <textarea name="additional_remarks" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" rows="3">{{ old('additional_remarks') }}</textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring">Create Impact</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Impact Log Grid --}}
        <div class="rounded-xl border bs surface overflow-hidden">
            <form id="impactTableFiltersForm" method="GET" action="{{ route('admin.impacts.index') }}"></form>
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Action</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impacted Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Life Impacted</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Review Remarks</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Approved By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Approved At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">
                                <a href="{{ route('admin.impacts.export.csv', request()->query()) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">Export CSV</a>
                            </th>
                        </tr>
                        <tr class="surface-2 border-b bs align-middle">
                            <th class="px-3 py-2">
                                <input type="date" name="filter_date" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal" value="{{ $filters['filter_date'] ?? '' }}">
                            </th>
                            <th class="px-3 py-2">
                                <select name="filter_action" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                    <option value="">All</option>
                                    @foreach($impactActions as $action)
                                        <option value="{{ $action }}" @selected(($filters['filter_action'] ?? '') === $action)>{{ $action }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2">
                                <input type="text" name="filter_impacted_peer" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" placeholder="Peer/Company/City" value="{{ $filters['filter_impacted_peer'] ?? '' }}">
                            </th>
                            <th class="px-3 py-2">
                                <input type="text" name="filter_submitted_by" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" placeholder="User/Company/City" value="{{ $filters['filter_submitted_by'] ?? '' }}">
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2">
                                <select name="filter_status" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                                    <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                                    <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                                </select>
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2">
                                <input type="text" name="filter_approved_by" form="impactTableFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" placeholder="Approved by" value="{{ $filters['filter_approved_by'] ?? '' }}">
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2 text-right">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('admin.impacts.index') }}" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($impacts as $impact)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->impact_date)->toDateString() }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $impact->action }}</span>
                                </td>
                                <td class="px-3 py-2.5 font-medium t1 text-[12.5px] whitespace-nowrap">{{ $displayUser($impact->impactedPeer) }}</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $displayUser($impact->user) }}</td>
                                <td class="px-3 py-2.5 font-semibold text-indigo-600 text-xs">{{ (int) ($impact->life_impacted ?? 1) }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge($impact->status) }}">{{ ucfirst($impact->status ?? 'pending') }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 max-w-[150px] truncate" title="{{ $impact->review_remarks ?: '-' }}">{{ $impact->review_remarks ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $impact->approvedBy?->name ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->approved_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.impacts.show', $impact->id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-8 text-xs t3">No impacts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $impacts->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = Array.from(document.querySelectorAll('.impact-action-row'));
            const button = document.getElementById('impactActionsViewMoreBtn');

            if (!button || rows.length <= 6) {
                return;
            }

            let visibleLimit = 6;

            const render = () => {
                rows.forEach((row, index) => {
                    row.style.display = index < visibleLimit ? '' : 'none';
                });

                if (visibleLimit >= rows.length) {
                    button.style.display = 'none';
                    return;
                }

                button.textContent = visibleLimit >= 12 ? 'View All' : 'View More';
            };

            button.addEventListener('click', () => {
                if (visibleLimit < 12) {
                    visibleLimit = 12;
                } else {
                    visibleLimit = rows.length;
                }

                render();
            });

            render();
        });
    </script>
@endpush

