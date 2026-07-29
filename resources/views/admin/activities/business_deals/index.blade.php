@extends('admin.layouts.app')

@section('title', 'Business Deals')

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

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
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
                ($p->business_deals_count ?? $p->total_count ?? 0) +
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
                'testimonials' => (int) ($p->testimonials_count ?? 0),
                'testimonialsUrl' => route('admin.activities.testimonials', $userId),
                'referrals' => (int) ($p->referrals_count ?? 0),
                'referralsUrl' => route('admin.activities.referrals', $userId),
                'deals' => (int) ($p->business_deals_count ?? $p->total_count ?? 0),
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

        $makeDealPayload = function($d) use ($displayName, $getInitials, $getAvatarBg, $formatDateTime, $formatDate) {
            $fromName = $d->from_user_name ?? $displayName($d->actor_display_name ?? null, $d->actor_first_name ?? null, $d->actor_last_name ?? null);
            $toName = $d->to_user_name ?? $displayName($d->peer_display_name ?? null, $d->peer_first_name ?? null, $d->peer_last_name ?? null);
            $hasMedia = (int) ($d->has_media ?? 0) === 1;
            $mediaRef = (string) ($d->media_reference ?? '');
            $mediaUrl = null;
            if ($hasMedia && $mediaRef) {
                $mediaUrl = str_starts_with($mediaRef, 'http://') || str_starts_with($mediaRef, 'https://')
                    ? $mediaRef
                    : url('/api/v1/files/' . $mediaRef);
            }

            return json_encode([
                'id' => $d->id,
                'from_name' => $fromName,
                'from_company' => $d->from_company ?? '',
                'from_city' => $d->from_city ?? '',
                'from_initials' => $getInitials($fromName),
                'from_bg' => $getAvatarBg($fromName),
                'to_name' => $toName,
                'to_company' => $d->to_company ?? '',
                'to_city' => $d->to_city ?? '',
                'to_initials' => $getInitials($toName),
                'to_bg' => $getAvatarBg($toName),
                'deal_amount' => $d->deal_amount ?? 0,
                'business_type' => $d->business_type ?? '',
                'deal_date' => $formatDate($d->deal_date ?? null),
                'comment' => $d->comment ?? '',
                'media_has' => $hasMedia,
                'media_url' => $mediaUrl,
                'created_at' => $formatDateTime($d->created_at ?? null),
            ]);
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Business Deals'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-briefcase-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($total) }}</div>
                    <div class="metric-label">Total Deals</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div>
                    <div class="metric-val">
                        ₹{{ number_format($items->sum('deal_amount'), 2) }}
                    </div>
                    <div class="metric-label">Total Deal Value (Page)</div>
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
                    <div class="metric-label">Most Deals by One Peer</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="businessDealsFiltersForm" method="GET" action="{{ route('admin.activities.business-deals.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.business-deals.index'),
                'resetUrl' => route('admin.activities.business-deals.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.business-deals.export', request()->query()),
                'renderFormTag' => false,
                'formId' => 'businessDealsFiltersForm',
            ])

            <!-- Top 5 & All logs row -->
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
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Deals</th>
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
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Business Deals Log</span>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">To</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Deal Details</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Amount</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Comment</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1"><input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1"><input type="text" name="to_user" value="{{ $filters['to_user'] ?? '' }}" placeholder="To name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1">
                                        <input type="date" name="deal_date" value="{{ $filters['deal_date'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1" placeholder="Date">
                                        <input type="text" name="business_type" value="{{ $filters['business_type'] ?? '' }}" placeholder="Business type" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1"><input type="number" step="0.01" name="deal_amount" value="{{ $filters['deal_amount'] ?? '' }}" placeholder="Amount" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1"><input type="text" name="comment" value="{{ $filters['comment'] ?? '' }}" placeholder="Comment" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1">
                                        <select name="has_media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            <option value="yes" @selected(($filters['has_media'] ?? '') === 'yes')>Yes</option>
                                            <option value="no" @selected(($filters['has_media'] ?? '') === 'no')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'businessDealsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $deal)
                                    @php
                                        $mediaValue = $deal->media_reference ?? null;
                                        $hasMedia = false;

                                        if (is_string($mediaValue)) {
                                            $trim = trim($mediaValue);
                                            $hasMedia = ($trim !== '' && $trim !== 'null' && $trim !== '[]' && $trim !== '{}');
                                        } elseif (is_array($mediaValue)) {
                                            $hasMedia = count($mediaValue) > 0;
                                        } elseif (! is_null($mediaValue)) {
                                            $hasMedia = true;
                                        }

                                        $actorName = $displayName($deal->actor_display_name ?? null, $deal->actor_first_name ?? null, $deal->actor_last_name ?? null);
                                        $peerName = $displayName($deal->peer_display_name ?? null, $deal->peer_first_name ?? null, $deal->peer_last_name ?? null);

                                        $fromName = $deal->from_user_name ?? $actorName;
                                        $toName = $deal->to_user_name ?? $peerName;
                                        $fromId = $deal->actor_id ?? $deal->from_user_id ?? null;
                                        $toId = $deal->user_id ?? $deal->to_user_id ?? null;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-deal="{{ $makeDealPayload($deal) }}" onclick="openBusinessDealDetailModal(this, event)">
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
                                                        @if($deal->from_company) <span>{{ $deal->from_company }}</span> @endif
                                                        @if($deal->from_city) &bull; <span>{{ $deal->from_city }}</span> @endif
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
                                                        @if($deal->to_company) <span>{{ $deal->to_company }}</span> @endif
                                                        @if($deal->to_city) &bull; <span>{{ $deal->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($deal->deal_date)
                                                <div class="font-semibold t1 text-[11px]">{{ $formatDate($deal->deal_date) }}</div>
                                            @endif
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 mt-0.5">{{ $deal->business_type ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs font-bold text-emerald-600">
                                            ₹{{ number_format($deal->deal_amount ?? 0, 2) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t2 max-w-[150px] truncate" title="{{ $deal->comment }}">
                                            {{ $deal->comment ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if ($hasMedia)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    Available
                                                </span>
                                                <button type="button" class="text-indigo-600 font-semibold text-xs ms-1 bg-transparent border-0 cursor-pointer p-0" data-bs-toggle="modal" data-bs-target="#mediaViewerModal" data-media-source="media-json-{{ $deal->id }}">View</button>
                                                <script type="application/json" id="media-json-{{ $deal->id }}">{{ e(json_encode(is_string($deal->media_reference ?? null) ? json_decode($deal->media_reference ?? '[]', true) : ($deal->media_reference ?? []))) }}</script>
                                            @else
                                                <span class="t3">No Media</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                            {{ $formatDateTime($deal->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-8 text-xs t3">No business deals found.</td>
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

    <!-- Media Viewer Modal -->
    <div class="modal fade" id="mediaViewerModal" tabindex="-1" aria-labelledby="mediaViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header">
                    <h5 class="modal-title font-semibold t1" id="mediaViewerModalLabel">Media Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body surface-2" data-media-container>
                    <p class="t3 mb-0">No media available.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-media-source]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('mediaViewerModal');
                const container = modal.querySelector('[data-media-container]');
                const sourceId = button.getAttribute('data-media-source');
                const scriptTag = document.getElementById(sourceId);
                let items = [];

                if (scriptTag) {
                    try {
                        items = JSON.parse(scriptTag.textContent || '[]');
                    } catch (error) {
                        items = [];
                    }
                }

                container.innerHTML = '';

                if (!Array.isArray(items) || items.length === 0) {
                    container.innerHTML = '<p class="t3 mb-0 py-4 text-center">No media available.</p>';
                    return;
                }

                items.forEach((item, index) => {
                    let url = null;

                    if (typeof item === 'string') {
                        url = item;
                    } else if (item && typeof item === 'object') {
                        url = item.url || item.id || null;
                    }

                    if (!url) {
                        return;
                    }

                    if (!url.startsWith('http') && /^[0-9a-fA-F-]{36}$/.test(url)) {
                        url = `/api/v1/files/${url}`;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.classList.add('card', 'p-3', 'mb-3', 'border-0', 'shadow-sm');

                    const link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = `Open File Reference ${index + 1}`;
                    link.classList.add('btn', 'btn-sm', 'btn-outline-primary', 'd-inline-block', 'mb-3', 'align-self-start');

                    wrapper.appendChild(link);

                    if (/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(url)) {
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = `Media ${index + 1}`;
                        img.classList.add('img-fluid', 'rounded', 'border');
                        img.style.maxHeight = '400px';
                        img.style.objectFit = 'contain';
                        wrapper.appendChild(img);
                    }

                    container.appendChild(wrapper);
                });
            });
        });
    </script>
    @include('admin.activities.partials.peer-modal')
    @include('admin.activities.business_deals.partials.detail-modal')
@endsection

