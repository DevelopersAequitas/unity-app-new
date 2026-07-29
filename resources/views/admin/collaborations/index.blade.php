@extends('admin.layouts.app')

@section('title', 'Find & Build Collaborations')

@include('admin.partials.grid-head')

@section('content')
    @php
        use App\Support\CollaborationFormatter;
        $getInitials = function($name) {
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };
        $getAvatarBg = function($name) {
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name);
            return $colors[abs($hash) % count($colors)];
        };
    @endphp

    <form id="collaborationsFiltersForm" method="GET" action="{{ route('admin.collaborations.index') }}"></form>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Find & Build Collaborations</h2>
                <p class="text-xs t3 m-0 mt-0.5">Browse and manage community business collaboration opportunities.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                Total: {{ number_format($total) }}
            </span>
        </div>

        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.collaborations.index'),
            'resetUrl' => route('admin.collaborations.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => true,
            'exportUrl' => route('admin.collaborations.export', request()->query()),
            'renderFormTag' => false,
            'formId' => 'collaborationsFiltersForm',
        ])

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Scope</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Mode</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Stage</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Yrs Active</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1"><input type="text" name="peer_name" form="collaborationsFiltersForm" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" name="collaboration_type" form="collaborationsFiltersForm" value="{{ $filters['collaboration_type'] ?? '' }}" placeholder="Type" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" name="title" form="collaborationsFiltersForm" value="{{ $filters['title'] ?? '' }}" placeholder="Title" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" name="scope" form="collaborationsFiltersForm" value="{{ $filters['scope'] ?? '' }}" placeholder="Scope" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" name="preferred_mode" form="collaborationsFiltersForm" value="{{ $filters['preferred_mode'] ?? '' }}" placeholder="Mode" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" name="business_stage" form="collaborationsFiltersForm" value="{{ $filters['business_stage'] ?? '' }}" placeholder="Stage" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1"><input type="text" name="year_in_operation" form="collaborationsFiltersForm" value="{{ $filters['year_in_operation'] ?? '' }}" placeholder="Years" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                            <th class="px-2 py-1">
                                <select name="status" form="collaborationsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="">Any</option>
                                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                                </select>
                            </th>
                            <th class="px-2 py-1">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'collaborationsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($posts as $post)
                            @php
                                $peerName = $post->peer_name ?? $post->person_name ?? $post->name ?? '—';
                                $company = ($post->peer_company ?? null) ?? $post->company ?? $post->company_name ?? $post->business_name ?? '—';
                                $city = ($post->peer_city ?? null) ?? $post->city ?? $post->user_city ?? '—';
                                $typeName = $post->collaborationType?->name ?? CollaborationFormatter::humanize($post->collaboration_type);
                                $title = $post->title ?? $post->collaboration_title ?? $post->subject ?? '—';
                                $scope = CollaborationFormatter::humanize($post->scope ?? $post->collaboration_scope ?? $post->scope_text);
                                $preferredMode = CollaborationFormatter::humanize($post->preferred_mode ?? $post->preferred_model ?? $post->meeting_mode ?? $post->mode);
                                $businessStage = CollaborationFormatter::humanize($post->business_stage ?? $post->stage ?? $post->business_stage_text);
                                $yearInOperation = CollaborationFormatter::humanize($post->year_in_operation ?? $post->years_in_operation ?? $post->operating_years ?? $post->years);
                                $status = $post->status ?? '—';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        @if(!empty($post->user_id ?? $post->user?->id))
                                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $post->user_id ?? $post->user?->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $peerName }}
                                            </a>
                                        @else
                                            <span class="font-semibold t1">{{ $peerName }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $company }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $city }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $typeName }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1 text-[12.5px] max-w-[150px] truncate" title="{{ $title }}">
                                    @if(!empty($post->id))
                                        <a href="{{ route('admin.collaborations.show', ['id' => $post->id] + request()->query()) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                            {{ $title }}
                                        </a>
                                    @else
                                        {{ $title }}
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $scope }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $preferredMode }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $businessStage }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $yearInOperation }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if(strtolower((string) $status) === 'active')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ CollaborationFormatter::humanize((string) $status) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <a class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline" href="{{ route('admin.collaborations.show', ['id' => $post->id] + request()->query()) }}">Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-8 text-xs t3">No collaboration posts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $posts->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
@endsection
