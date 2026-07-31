@extends('admin.layouts.app')

@section('title', 'All Posts')

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
    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form id="postsFiltersForm" method="GET" action="{{ route('admin.posts.index') }}"></form>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">All Posts</h2>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($posts->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2.5 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Active</label>
                    <select name="active" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all" @selected(($filters['active'] ?? 'all') === 'all')>All</option>
                        <option value="active" @selected(($filters['active'] ?? '') === 'active')>Active</option>
                        <option value="deactivated" @selected(($filters['active'] ?? '') === 'deactivated')>Deactivated</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Visibility</label>
                    <select name="visibility" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">Any</option>
                        @foreach ($visibilities as $visibility)
                            <option value="{{ $visibility }}" @selected(($filters['visibility'] ?? '') === $visibility)>{{ ucfirst($visibility) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Moderation</label>
                    <select name="moderation_status" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        @foreach ($moderationOptions as $value => $label)
                            <option value="{{ $value === 'any' ? '' : $value }}" @selected(($filters['moderation_status'] ?? '') === ($value === 'any' ? '' : $value))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                    <select name="circle_id" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all">All Circles</option>
                        @foreach ($circles as $c)
                            <option value="{{ $c->id }}" @selected(($circleId ?? 'all') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Content or owner" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.posts.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-[1100px] w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visibility</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Moderation Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Active</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Content</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1 sticky left-0 z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);"><input type="text" name="peer" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" style="min-width:180px" value="{{ $peer ?? '' }}" placeholder="Peer Search"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1">
                                <select name="inline_visibility" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="any">Any</option>
                                    @foreach ($visibilities as $visibility)
                                        <option value="{{ $visibility }}" @selected(($inlineVisibility ?? 'any') === $visibility)>{{ ucfirst($visibility) }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-2 py-1">
                                <select name="inline_moderation_status" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    @foreach ($moderationOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($inlineModerationStatus ?? 'any') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-2 py-1">
                                <select name="inline_active" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="any" @selected(($inlineActive ?? 'any') === 'any')>Any</option>
                                    <option value="yes" @selected(($inlineActive ?? '') === 'yes')>Yes</option>
                                    <option value="no" @selected(($inlineActive ?? '') === 'no')>No</option>
                                </select>
                            </th>
                            <th class="px-2 py-1 text-center t3">—</th>
                            <th class="px-2 py-1">
                                <select name="media" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="any" @selected(($media ?? 'any') === 'any')>Any</option>
                                    <option value="has" @selected(($media ?? '') === 'has')>Has Media</option>
                                    <option value="none" @selected(($media ?? '') === 'none')>No Media</option>
                                </select>
                            </th>
                            <th class="px-2 py-1">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'postsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($posts as $post)
                            @php
                                $isImpact = ($post->timeline_item_type ?? $post->source_type ?? 'post') === 'impact';
                                $owner = $post->user;
                                $ownerName = $owner ? ($owner->display_name ?: trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? ''))) : '—';
                                $circleName = optional($post->circle)->name;
                                $isActive = $isImpact
                                    ? (bool) ($post->is_active ?? ! is_null($post->timeline_posted_at ?? null))
                                    : $post->deleted_at === null && ! (bool) ($post->is_deleted ?? false);
                                $mediaUrl = (function ($media) {
                                    if (empty($media)) {
                                        return null;
                                    }

                                    $items = [];

                                    if (is_array($media)) {
                                        $items = $media;
                                    } elseif (is_object($media)) {
                                        $items = data_get($media, 'items', []);
                                    }

                                    if (! is_array($items)) {
                                        return null;
                                    }

                                    $imageItem = collect($items)->first(function ($item) {
                                        return data_get($item, 'type') === 'image';
                                    });

                                    $candidate = $imageItem ?? (collect($items)->first() ?? []);
                                    $url = data_get($candidate, 'url');

                                    if ($url) {
                                        return $url;
                                    }

                                    $id = data_get($candidate, 'id') ?? data_get($candidate, 'file_id');

                                    if ($id) {
                                        return url('/api/v1/files/' . $id);
                                    }

                                    return data_get($candidate, 'path');
                                })($post->media ?? null);
                                $isCompletedCollaboration = ! $isImpact
                                    && ($post->source_type ?? null) === 'collaboration_post'
                                    && ($post->source_event ?? null) === 'completed';
                                $acceptedBy = $isCompletedCollaboration ? optional($post->collaborationPost)->acceptedByUser : null;
                                $acceptedByName = $acceptedBy
                                    ? ($acceptedBy->display_name ?: trim(($acceptedBy->first_name ?? '') . ' ' . ($acceptedBy->last_name ?? '')))
                                    : null;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                    @if($owner)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($ownerName) }}">
                                                {{ $getInitials($ownerName) }}
                                            </div>
                                            <a href="{{ route('admin.users.show', $owner->id) }}" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $ownerName }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$owner->company_name ?? $owner->company ?? $owner->business_name ?? '—'" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$owner->city ?? '—'" /></td>
                                <td class="px-3 py-2.5 text-xs t2"><x-admin-grid-text :text="$circleName ?: '—'" /></td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ ucfirst($post->visibility) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $post->moderation_status ? ucfirst($post->moderation_status) : '—' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($isActive)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Yes</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">No</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($isImpact)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200 mb-1 inline-block">Impact</span>
                                    @endif
                                    <div class="t1 font-medium max-w-[250px] admin-grid-text-clamp" data-full-text="{{ $post->content_text }}">{{ $post->content_text }}</div>
                                    @if($isCompletedCollaboration)
                                        <div class="t3 text-[10px] mt-1">
                                            @if($acceptedBy)
                                                <div><strong>Accepted by:</strong> {{ $acceptedByName !== '' ? $acceptedByName : 'Not available' }}</div>
                                                <div><strong>Company:</strong> {{ $acceptedBy->company_name ?: '—' }}</div>
                                                <div><strong>City:</strong> {{ $acceptedBy->city ?: '—' }}</div>
                                            @else
                                                <div><strong>Accepted by:</strong> Not available</div>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if ($mediaUrl)
                                        <a class="px-2 py-0.5 text-xs font-semibold rounded border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition no-underline" target="_blank" href="{{ $mediaUrl }}">View</a>
                                    @else
                                        <span class="t3">None</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        @if($isImpact)
                                            <a href="{{ route('admin.impacts.show', $post->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                            @if($isActive)
                                                <form method="POST" action="{{ route('admin.posts.impacts.deactivate', $post->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Are you sure you want to deactivate this impact?')">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.posts.impacts.activate', $post->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Are you sure you want to activate this impact?')">
                                                        Activate
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <a href="{{ route('admin.posts.show', $post) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>

                                            @if($isActive)
                                                <form method="POST" action="{{ route('admin.posts.deactivate', $post) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Are you sure you want to deactivate this post?')">
                                                        Deactivate
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.posts.restore', $post->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Are you sure you want to activate this post?')">
                                                        Activate
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-8 text-xs t3">No posts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $posts->links() }}
            </div>
        </div>
    </div>
@endsection

