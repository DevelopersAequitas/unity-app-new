@extends('admin.layouts.app')

@section('title', 'Post Reports')

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

    $formatReportStatusBadge = function (?string $status): array {
        $raw = strtolower(trim((string) $status));
        return match ($raw) {
            'resolved' => [
                'label' => 'Resolved',
                'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'dotClass' => 'bg-emerald-500',
            ],
            'pending' => [
                'label' => 'Pending',
                'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                'dotClass' => 'bg-amber-500',
            ],
            'dismissed' => [
                'label' => 'Dismissed',
                'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                'dotClass' => 'bg-slate-400',
            ],
            '' => [
                'label' => '—',
                'badgeClass' => '',
                'dotClass' => '',
            ],
            default => [
                'label' => ucfirst($raw),
                'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                'dotClass' => 'bg-slate-500',
            ],
        };
    };
@endphp

@section('content')
    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ $errors->first() }}</div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Post Reports</h2>
        </div>

        <form id="postReportsFiltersForm" method="GET" action="{{ route('admin.post-reports.index') }}" class="space-y-4">
            <!-- Filter Card -->
            <div class="p-3 rounded-lg border bs surface-2">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2.5 items-end">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                        <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                            <option value="">Any</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Reason</label>
                        <select name="reason" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                            <option value="">Any</option>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>{{ ucfirst($reason) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From</label>
                        <input type="date" name="date_from" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To</label>
                        <input type="date" name="date_to" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                        <select name="circle_id" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                            <option value="all">All Circles</option>
                            @foreach ($circles as $circle)
                                <option value="{{ $circle->id }}" @selected(($circleId ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="clearAdminFilters(event, 'postReportsFiltersForm')" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center w-full">Clear</button>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reported At</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Post ID</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reporter Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reason</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reports Count</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Post Active</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            </tr>
                            <tr class="surface-2 border-b bs filter-row">
                                <th class="px-2 py-1 text-center t3">—</th>
                                <th class="px-2 py-1"><input type="text" name="post_id" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $postId ?? '' }}" placeholder="Post ID"></th>
                                <th class="px-2 py-1"><input type="text" name="peer" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $peer ?? '' }}" placeholder="Peer Name"></th>
                                <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                <th class="px-2 py-1"><input type="text" name="reporter" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $reporter ?? '' }}" placeholder="Reporter Name"></th>
                                <th class="px-2 py-1"><input type="text" name="reason_text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $reasonText ?? '' }}" placeholder="Reason"></th>
                                <th class="px-2 py-1">
                                    <select name="status" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                        <option value="any">Any</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(($filters['status'] ?? 'any') === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th class="px-2 py-1">
                                    <select name="total_reports" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                        <option value="any" @selected(($totalReports ?? 'any') === 'any')>Any</option>
                                        <option value="1" @selected(($totalReports ?? '') === '1')>1</option>
                                        <option value="2-5" @selected(($totalReports ?? '') === '2-5')>2-5</option>
                                        <option value="6+" @selected(($totalReports ?? '') === '6+')>6+</option>
                                    </select>
                                </th>
                                <th class="px-2 py-1">
                                    <select name="post_active" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                        <option value="any" @selected(($postActive ?? 'any') === 'any')>Any</option>
                                        <option value="yes" @selected(($postActive ?? '') === 'yes')>Yes</option>
                                        <option value="no" @selected(($postActive ?? '') === 'no')>No</option>
                                    </select>
                                </th>
                                <th class="px-2 py-1">
                                    <select name="media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                        <option value="any" @selected(($media ?? 'any') === 'any')>Any</option>
                                        <option value="has" @selected(($media ?? '') === 'has')>Has Media</option>
                                        <option value="none" @selected(($media ?? '') === 'none')>No Media</option>
                                    </select>
                                </th>
                                <th class="px-2 py-1">
                                    <div class="flex justify-end">
                                        <button type="button" onclick="clearAdminFilters(event, 'postReportsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($reports as $report)
                                @php
                                    $postOwner = $report->post?->user;
                                    $postOwnerName = $postOwner ? ($postOwner->display_name ?: trim(($postOwner->first_name ?? '') . ' ' . ($postOwner->last_name ?? ''))) : '—';
                                    $circleName = $report->post?->circle?->name;
                                    $reporterName = $report->reporter?->display_name ?: trim(($report->reporter?->first_name ?? '') . ' ' . ($report->reporter?->last_name ?? ''));
                                    $isPostActive = $report->post ? ! $report->post->is_deleted && ! $report->post->deleted_at : false;
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
                                    })($report->post?->media ?? null);
                                @endphp
                                <tr class="hover:surface-2 transition border-b bs">
                                    <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-2.5 text-xs font-mono t2">{{ $report->post_id }}</td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if($postOwner)
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($postOwnerName) }}">
                                                    {{ $getInitials($postOwnerName) }}
                                                </div>
                                                <a href="{{ route('admin.users.show', $postOwner->id) }}" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                    {{ $postOwnerName }}
                                                </a>
                                            </div>
                                        @else
                                            <span class="t3">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $postOwner->company_name ?? $postOwner->company ?? $postOwner->business_name ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $postOwner->city ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $circleName ?: '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t1 font-medium">{{ $reporterName !== '' ? $reporterName : 'Unknown' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $report->reasonOption?->title ?? $report->reason ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                        @php $repStatusInfo = $formatReportStatusBadge($report->status); @endphp
                                        @if(!empty($repStatusInfo['label']) && $repStatusInfo['label'] !== '—')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $repStatusInfo['badgeClass'] }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $repStatusInfo['dotClass'] }}"></span>
                                                <span>{{ $repStatusInfo['label'] }}</span>
                                            </span>
                                        @else
                                            <span class="t3">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs font-semibold t1 text-center">{{ $report->total_reports ?? 0 }}</td>
                                    <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                        @if($isPostActive)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span>Yes</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                <span>No</span>
                                            </span>
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
                                            <a href="{{ route('admin.post-reports.show', $report) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                            @if($isPostActive)
                                                <form method="POST" action="{{ route('admin.posts.deactivate', $report->post_id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Deactivate this reported post?')">Deactivate Post</button>
                                                </form>
                                            @endif
                                            @if($report->status !== 'resolved')
                                                <form method="POST" action="{{ route('admin.post-reports.resolve', $report) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Resolve this report?')">Resolve</button>
                                                </form>
                                            @endif
                                            @if($report->status !== 'dismissed')
                                                <form method="POST" action="{{ route('admin.post-reports.dismiss', $report) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition focus-ring" onclick="return confirm('Dismiss this report?')">Dismiss</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="13" class="text-center py-8 text-xs t3">No reports found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $reports->links() }}
        </div>
    </div>
@endsection

