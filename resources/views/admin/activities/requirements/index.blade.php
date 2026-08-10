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

        $makePeerPayload = function($p) use ($getInitials, $getAvatarBg) {
            $userId = $p->actor_id ?? $p->id ?? $p->user_id ?? '';
            $name = $p->peer_name ?? $p->display_name ?? trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: 'Peer';
            $company = $p->peer_company ?? $p->company_name ?? '';
            $city = $p->peer_city ?? $p->city_name ?? $p->city ?? '';
            $circle = $p->circle_name ?? '';
            $email = $p->email ?? '';
            $phone = $p->phone ?? '';
            $designation = $p->designation ?? 'Member';

            $score = (int) ($p->performance_score ?? (
                ($p->testimonials_count ?? 0) +
                ($p->referrals_count ?? 0) +
                ($p->business_deals_count ?? 0) +
                ($p->p2p_completed_count ?? 0) +
                ($p->requirements_count ?? $p->total_count ?? 0) +
                ($p->become_leader_count ?? 0) +
                ($p->recommend_peer_count ?? 0) +
                ($p->register_visitor_count ?? 0)
            ));

            return json_encode([
                'id' => $userId,
                'name' => $name,
                'company' => $company,
                'city' => $city,
                'circle' => $circle,
                'email' => $email,
                'phone' => $phone,
                'designation' => $designation,
                'initials' => $getInitials($name),
                'avatarBg' => $getAvatarBg($name),
                'testimonials' => (int) ($p->testimonials_count ?? 0),
                'testimonialsUrl' => route('admin.activities.testimonials', $userId),
                'referrals' => (int) ($p->referrals_count ?? 0),
                'referralsUrl' => route('admin.activities.referrals', $userId),
                'deals' => (int) ($p->business_deals_count ?? 0),
                'dealsUrl' => route('admin.activities.business-deals', $userId),
                'p2p' => (int) ($p->p2p_completed_count ?? 0),
                'p2pUrl' => route('admin.activities.p2p-meetings', $userId),
                'requirements' => (int) ($p->requirements_count ?? $p->total_count ?? 0),
                'requirementsUrl' => route('admin.activities.requirements', $userId),
                'leadership' => (int) ($p->become_leader_count ?? 0),
                'leadershipUrl' => route('admin.activities.become-a-leader.show', $userId),
                'recommendations' => (int) ($p->recommend_peer_count ?? 0),
                'recommendationsUrl' => route('admin.activities.recommend-peer.show', $userId),
                'visitors' => (int) ($p->register_visitor_count ?? 0),
                'visitorsUrl' => route('admin.activities.register-visitor.show', $userId),
                'score' => $score,
            ]);
        };

        $makeRequirementPayload = function($r) use ($displayName, $getInitials, $getAvatarBg, $mediaSummary, $firstMediaId, $decodeFilter, $formatDateTime) {
            $fromName = $r->from_user_name ?? $displayName($r->actor_display_name ?? null, $r->actor_first_name ?? null, $r->actor_last_name ?? null);
            $mediaInfo = $mediaSummary($r->media ?? null);
            $mediaId = $firstMediaId($r->media ?? null);
            $regionFilter = $decodeFilter($r->region_filter ?? null);
            $categoryFilter = $decodeFilter($r->category_filter ?? null);

            return json_encode([
                'id' => $r->id,
                'from_name' => $fromName,
                'from_company' => $r->from_company ?? '',
                'from_city' => $r->from_city ?? '',
                'from_initials' => $getInitials($fromName),
                'from_bg' => $getAvatarBg($fromName),
                'subject' => $r->subject ?? '',
                'description' => $r->description ?? '',
                'region' => $regionFilter['region_label'] ?? $regionFilter['region_name'] ?? $regionFilter['city_name'] ?? 'Any',
                'category' => $categoryFilter['category'] ?? 'Any',
                'status' => $r->status ?? 'open',
                'media_has' => $mediaInfo['has'],
                'media_count' => $mediaInfo['count'],
                'media_url' => $mediaId ? url('/api/v1/files/' . $mediaId) : null,
                'created_at' => $formatDateTime($r->created_at ?? null),
            ]);
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
                <div>
                    <div class="metric-val">{{ number_format($total) }}</div>
                    <div class="metric-label">Total Requirements</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div>
                    <div class="metric-val">
                        @if(($topMembers ?? collect())->isNotEmpty())
                            {{ $topMembers->first()->total_count ?? 0 }}
                        @else
                            0
                        @endif
                    </div>
                    <div class="metric-label">Most Requirements by One Peer</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-images"></i>
                </div>
                <div>
                    <div class="metric-val">
                        {{ number_format(count($validMediaIds ?? [])) }}
                    </div>
                    <div class="metric-label">Verified Attachments</div>
                </div>
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
                            <tbody class="divide-y divide-gray-200/50">
                                @forelse ($topMembers as $index => $member)
                                    <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-peer="{{ $makePeerPayload($member) }}" onclick="openActivityPeerModal(this, event)">
                                        <td class="px-3 py-2.5 text-xs font-semibold t3">#{{ $index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($member->peer_name ?? '') }}">
                                                    {{ $getInitials($member->peer_name ?? '') }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">
                                                        @php $mId = $member->actor_id ?? $member->id ?? $member->user_id ?? null; @endphp
                                                        @if(!empty($mId))
                                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $mId }}', event);">
                                                                {{ $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null) }}
                                                            </a>
                                                        @else
                                                            {{ $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null) }}
                                                        @endif
                                                    </div>
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
                        <table class="min-w-[1050px] w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:140px;">Company</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:100px;">City</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Subject</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">Description</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:90px;">Region</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">Business Category</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:80px;">Status</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:80px;">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row align-middle">
                                    <th class="px-2 py-1 sticky left-0 z-10 surface-2" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                        <input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1" style="min-width:140px;">
                                        <input type="text" name="from_company" value="{{ $filters['from_company'] ?? '' }}" placeholder="Company" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1" style="min-width:100px;">
                                        <input type="text" name="from_city" value="{{ $filters['from_city'] ?? '' }}" placeholder="City" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1" style="min-width:110px;"><input type="text" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="Subject" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1" style="min-width:130px;"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                    <th class="px-2 py-1" style="min-width:90px;">
                                        <input type="text" name="region" value="{{ $filters['region'] ?? '' }}" placeholder="Region" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1" style="min-width:130px;">
                                        <input type="text" name="category" value="{{ $filters['category'] ?? '' }}" placeholder="Category" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1" style="min-width:80px;">
                                        <select name="status" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            @foreach (($statuses ?? collect()) as $status)
                                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </th>
                                    <th class="px-2 py-1" style="min-width:80px;">
                                        <select name="has_media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            <option value="1" @selected(($filters['has_media'] ?? '') === '1')>Yes</option>
                                            <option value="0" @selected(($filters['has_media'] ?? '') === '0')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1" style="min-width:110px;">
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
                                        $fromId = $requirement->actor_id ?? $requirement->user_id ?? null;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-requirement="{{ $makeRequirementPayload($requirement) }}" onclick="openRequirementDetailModal(this, event)">
                                        <td class="px-3 py-2.5 align-middle sticky left-0 z-10 surface" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($fromName) }}">
                                                    {{ $getInitials($fromName) }}
                                                </div>
                                                <div class="font-semibold t1 text-[12.5px] whitespace-nowrap">
                                                    @if(!empty($fromId))
                                                        <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $fromId }}', event);">
                                                            {{ $fromName }}
                                                        </a>
                                                    @else
                                                        {{ $fromName }}
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-slate-700 font-medium align-middle" style="min-width:140px;">
                                            @if($requirement->from_company)
                                                <div class="flex items-center gap-1.5 text-slate-800 font-medium">
                                                    <i class="bi bi-building text-slate-400 text-[11px]"></i>
                                                    <span class="line-clamp-1" title="{{ $requirement->from_company }}">{{ $requirement->from_company }}</span>
                                                </div>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs align-middle" style="min-width:100px;">
                                            @if($requirement->from_city)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                    <i class="bi bi-geo-alt text-slate-400 text-[10px]"></i> {{ $requirement->from_city }}
                                                </span>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs font-semibold t1 text-[12px] align-middle" style="min-width:110px;"><x-admin-grid-text :text="$requirement->subject ?? '—'" :lines="2" /></td>
                                        <td class="px-3 py-2.5 text-xs t3 align-middle" style="min-width:130px;"><x-admin-grid-text :text="$requirement->description ?? '—'" :lines="2" /></td>
                                        <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width:90px;"><x-admin-grid-text :text="$regionLabel ?: '—'" :lines="2" /></td>
                                        <td class="px-3 py-2.5 text-xs font-medium t1 align-middle whitespace-nowrap" style="min-width:130px;">
                                            <x-admin-grid-text :text="$categoryLabel ?: '—'" :lines="2" />
                                        </td>
                                        <td class="px-3 py-2.5 text-xs align-middle whitespace-nowrap" style="min-width:80px;">
                                            @php
                                                $st = strtolower((string)($requirement->status ?? ''));
                                            @endphp
                                            @if($st === 'open' || $st === 'active')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($requirement->status ?? 'Open') }}
                                                </span>
                                            @elseif($st === 'completed' || $st === 'closed')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-sky-50 text-sky-700 border border-sky-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>{{ ucfirst($requirement->status ?? 'Completed') }}
                                                </span>
                                            @elseif($st === 'rejected' || $st === 'cancelled')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>{{ ucfirst($requirement->status ?? 'Rejected') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-slate-100 text-slate-700 border border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($requirement->status ?: '—') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs align-middle" style="min-width:80px;">
                                            @if($mediaInfo['has'] && $mediaId)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    Available
                                                </span>
                                                <button type="button" class="text-indigo-600 font-semibold text-xs ms-1 bg-transparent border-0 cursor-pointer p-0" data-bs-toggle="modal" data-bs-target="#mediaViewerModal" data-media-source="media-json-req-{{ $requirement->id }}" onclick="event.stopPropagation();">View</button>
                                                <script type="application/json" id="media-json-req-{{ $requirement->id }}">{{ e(json_encode(is_string($requirement->media ?? null) ? json_decode($requirement->media ?? '[]', true) : ($requirement->media ?? []))) }}</script>
                                            @else
                                                <span class="t3">No Media</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap align-middle" style="min-width:110px;">
                                            {{ $formatDateTime($requirement->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-8 text-xs t3">No requirements found.</td>
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

    @include('admin.activities.partials.peer-modal')
    @include('admin.activities.requirements.partials.detail-modal')
@endsection
