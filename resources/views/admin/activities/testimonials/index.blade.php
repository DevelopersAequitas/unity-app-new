@extends('admin.layouts.app')

@section('title', 'Testimonials')

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
                ($p->testimonials_count ?? $p->total_count ?? 0) +
                ($p->referrals_count ?? 0) +
                ($p->business_deals_count ?? 0) +
                ($p->p2p_completed_count ?? 0) +
                ($p->requirements_count ?? 0) +
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
                'testimonials' => (int) ($p->testimonials_count ?? $p->total_count ?? 0),
                'testimonialsUrl' => route('admin.activities.testimonials', $userId),
                'referrals' => (int) ($p->referrals_count ?? 0),
                'referralsUrl' => route('admin.activities.referrals', $userId),
                'deals' => (int) ($p->business_deals_count ?? 0),
                'dealsUrl' => route('admin.activities.business-deals', $userId),
                'p2p' => (int) ($p->p2p_completed_count ?? 0),
                'p2pUrl' => route('admin.activities.p2p-meetings', $userId),
                'requirements' => (int) ($p->requirements_count ?? 0),
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

        $makeTestimonialPayload = function($t) use ($displayName, $getInitials, $getAvatarBg, $mediaSummary, $firstMediaId, $formatDateTime) {
            $fromName = $t->from_user_name ?? $displayName($t->actor_display_name ?? null, $t->actor_first_name ?? null, $t->actor_last_name ?? null);
            $toName = $t->to_user_name ?? $displayName($t->peer_display_name ?? null, $t->peer_first_name ?? null, $t->peer_last_name ?? null);
            $mediaInfo = $mediaSummary($t->media ?? null);
            $mediaId = $firstMediaId($t->media ?? null);

            return json_encode([
                'id' => $t->id,
                'from_name' => $fromName,
                'from_company' => $t->from_company ?? '',
                'from_city' => $t->from_city ?? '',
                'from_initials' => $getInitials($fromName),
                'from_bg' => $getAvatarBg($fromName),
                'to_name' => $toName,
                'to_company' => $t->to_company ?? '',
                'to_city' => $t->to_city ?? '',
                'to_initials' => $getInitials($toName),
                'to_bg' => $getAvatarBg($toName),
                'content' => $t->content ?? '',
                'media_has' => $mediaInfo['has'],
                'media_count' => $mediaInfo['count'],
                'media_url' => $mediaId ? url('/api/v1/files/' . $mediaId) : null,
                'created_at' => $formatDateTime($t->created_at ?? null),
            ]);
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Testimonials'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-chat-quote-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($total) }}</div>
                    <div class="metric-label">Total Testimonials</div>
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
                    <div class="metric-label">Most Testimonials by One Peer</div>
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
        <form id="testimonialsFiltersForm" method="GET" action="{{ route('admin.activities.testimonials.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.testimonials.index'),
                'resetUrl' => route('admin.activities.testimonials.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.testimonials.export', request()->except(['content'])),
                'renderFormTag' => false,
                'formId' => 'testimonialsFiltersForm',
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
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Testimonials</th>
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
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Testimonials Log</span>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">To</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Content</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1">
                                        <input type="text" name="from_peer" value="{{ $tableFilters['from_peer'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="From name">
                                    </th>
                                    <th class="px-2 py-1">
                                        <input type="text" name="to_peer" value="{{ $tableFilters['to_peer'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="To name">
                                    </th>
                                    <th class="px-2 py-1"></th>
                                    <th class="px-2 py-1">
                                        <select name="media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="" @selected(($tableFilters['media'] ?? '') === '')>Any</option>
                                            <option value="yes" @selected(($tableFilters['media'] ?? '') === 'yes')>Yes</option>
                                            <option value="no" @selected(($tableFilters['media'] ?? '') === 'no')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'testimonialsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $testimonial)
                                    @php
                                        $actorName = $displayName($testimonial->actor_display_name ?? null, $testimonial->actor_first_name ?? null, $testimonial->actor_last_name ?? null);
                                        $peerName = $displayName($testimonial->peer_display_name ?? null, $testimonial->peer_first_name ?? null, $testimonial->peer_last_name ?? null);
                                        $mediaInfo = $mediaSummary($testimonial->media ?? null);
                                        $mediaId = $firstMediaId($testimonial->media ?? null);

                                        $fromName = $testimonial->from_user_name ?? $actorName;
                                        $toName = $testimonial->to_user_name ?? $peerName;
                                        $fromId = $testimonial->actor_id ?? $testimonial->from_user_id ?? null;
                                        $toId = $testimonial->user_id ?? $testimonial->to_user_id ?? null;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-testimonial="{{ $makeTestimonialPayload($testimonial) }}" onclick="openTestimonialDetailModal(this, event)">
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($fromName) }}">
                                                    {{ $getInitials($fromName) }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">
                                                        @if(!empty($fromId))
                                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $fromId }}', event);">
                                                                {{ $fromName }}
                                                            </a>
                                                        @else
                                                            {{ $fromName }}
                                                        @endif
                                                    </div>
                                                    <div class="t3 text-[10px]">
                                                        @if($testimonial->from_company) <span>{{ $testimonial->from_company }}</span> @endif
                                                        @if($testimonial->from_city) &bull; <span>{{ $testimonial->from_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($toName) }}">
                                                    {{ $getInitials($toName) }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">
                                                        @if(!empty($toId))
                                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $toId }}', event);">
                                                                {{ $toName }}
                                                            </a>
                                                        @else
                                                            {{ $toName }}
                                                        @endif
                                                    </div>
                                                    <div class="t3 text-[10px]">
                                                        @if($testimonial->to_company) <span>{{ $testimonial->to_company }}</span> @endif
                                                        @if($testimonial->to_city) &bull; <span>{{ $testimonial->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t2 max-w-xs truncate" title="{{ $testimonial->content ?? '' }}">
                                            {{ \Illuminate\Support\Str::limit($testimonial->content ?? '—', 60) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($mediaInfo['has'] && $mediaId)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <i class="bi bi-paperclip"></i>
                                                    Media ({{ $mediaInfo['count'] }})
                                                </span>
                                            @else
                                                <span class="text-slate-400 text-[11px]">No Media</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3">
                                            {{ $formatDateTime($testimonial->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-xs t3">No testimonials found.</td>
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
    @include('admin.activities.testimonials.partials.detail-modal')
@endsection
