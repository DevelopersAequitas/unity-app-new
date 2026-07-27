@extends('admin.layouts.app')

@section('title', 'Requirements')

@include('admin.partials.grid-head')

@section('content')
    @php
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

        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $name = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $name !== '' ? $name : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };

        $mediaSummary = function ($media) use ($validMediaIds): array {
            if (! $media) {
                return ['has' => false, 'count' => 0];
            }

            $decoded = is_string($media) ? json_decode($media, true) : $media;
            $items = is_array($decoded) ? $decoded : [$decoded];

            $validCount = 0;
            foreach ($items as $item) {
                $id = null;
                if (is_array($item)) {
                    $id = $item['id'] ?? $item['file_id'] ?? $item['fileId'] ?? null;
                } elseif (is_string($item) && \Illuminate\Support\Str::isUuid($item)) {
                    $id = $item;
                }

                if ($id && in_array($id, $validMediaIds ?? [], true)) {
                    $validCount++;
                }
            }

            return ['has' => $validCount > 0, 'count' => $validCount];
        };

        $firstMediaId = function ($media) use ($validMediaIds): ?string {
            if (! $media) {
                return null;
            }

            $decoded = is_string($media) ? json_decode($media, true) : $media;
            $items = is_array($decoded) ? array_values($decoded) : [$decoded];

            foreach ($items as $item) {
                $id = null;
                if (is_array($item)) {
                    $id = $item['id'] ?? $item['file_id'] ?? $item['fileId'] ?? null;
                } elseif (is_string($item) && \Illuminate\Support\Str::isUuid($item)) {
                    $id = $item;
                }

                if ($id && in_array($id, $validMediaIds ?? [], true)) {
                    return $id;
                }
            }

            return null;
        };

        $decodeFilter = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Requirements'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="metric-val">{{ number_format($total) }}</div>
                <div class="metric-label">Total Requirements</div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="metric-val">
                    @if(($topMembers ?? collect())->isNotEmpty())
                        {{ $topMembers->first()->total_count ?? 0 }}
                    @else
                        0
                    @endif
                </div>
                <div class="metric-label">Most Requirements by One Peer</div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-images"></i>
                </div>
                <div class="metric-val">
                    {{ number_format(count($validMediaIds ?? [])) }}
                </div>
                <div class="metric-label">Verified Attachments</div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="requirementsFiltersForm" method="GET" action="{{ route('admin.activities.requirements.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.requirements.index'),
                'resetUrl' => route('admin.activities.requirements.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.requirements.export', request()->query()),
                'renderFormTag' => false,
                'formId' => 'requirementsFiltersForm',
            ])

            <div class="space-y-4">
                <!-- Top 5 Peers Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Top 5 Peers</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-16">Rank</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Requirements</th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($topMembers as $index => $member)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5 text-xs font-semibold t3">#{{ $index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($member->peer_name ?? '') }}">
                                                    {{ $getInitials($member->peer_name ?? '') }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">{{ $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null) }}</div>
                                                    <div class="t3 text-[10px]">{{ $member->peer_company ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs font-bold text-indigo-600 text-right">{{ $member->total_count ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-xs t3">No data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- All Logs Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Requirements Log</span>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Subject & Description</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Region & Category</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1">
                                        <input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1"><input type="text" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="Subject" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1">
                                        <input type="text" name="region" value="{{ $filters['region'] ?? '' }}" placeholder="Region" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                        <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Category" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1">
                                        <select name="status" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            @foreach (($statuses ?? collect()) as $status)
                                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <select name="has_media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            <option value="1" @selected(($filters['has_media'] ?? '') === '1')>Yes</option>
                                            <option value="0" @selected(($filters['has_media'] ?? '') === '0')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'requirementsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $requirement)
                                    @php
                                        $actorName = $displayName($requirement->actor_display_name ?? null, $requirement->actor_first_name ?? null, $requirement->actor_last_name ?? null);
                                        $mediaInfo = $mediaSummary($requirement->media ?? null);
                                        $mediaId = $firstMediaId($requirement->media ?? null);
                                        $regionFilter = $decodeFilter($requirement->region_filter ?? null);
                                        $categoryFilter = $decodeFilter($requirement->category_filter ?? null);
                                        $regionLabel = $regionFilter['region_label'] ?? $regionFilter['region_name'] ?? $regionFilter['city_name'] ?? null;
                                        $categoryLabel = $categoryFilter['category'] ?? null;

                                        $fromName = $requirement->from_user_name ?? $actorName;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($fromName) }}">
                                                    {{ $getInitials($fromName) }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">{{ $fromName }}</div>
                                                    <div class="t3 text-[10px]">
                                                        @if($requirement->from_company) <span>{{ $requirement->from_company }}</span> @endif
                                                        @if($requirement->from_city) &bull; <span>{{ $requirement->from_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <div class="font-semibold t1 text-[12px]">{{ $requirement->subject ?? '—' }}</div>
                                            <div class="t3 text-[10px] max-w-[250px] truncate" title="{{ $requirement->description }}">
                                                {{ $requirement->description ?? '—' }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t2">
                                            <div><span class="t3">Region:</span> {{ $regionLabel ?: '—' }}</div>
                                            <div class="mt-0.5"><span class="t3">Category:</span> <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ $categoryLabel ?: '—' }}</span></div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($requirement->status === 'active')
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ ucfirst($requirement->status ?? '—') }}</span>
                                            @else
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ ucfirst($requirement->status ?? '—') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if ($mediaInfo['has'] && $mediaId)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    Available ({{ $mediaInfo['count'] }})
                                                </span>
                                                <a href="{{ url('/api/v1/files/' . $mediaId) }}" target="_blank" rel="noopener" class="text-indigo-600 font-semibold text-xs ms-1 no-underline">View</a>
                                            @else
                                                <span class="t3">No Media</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                            {{ $formatDateTime($requirement->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-8 text-xs t3">No requirements found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

