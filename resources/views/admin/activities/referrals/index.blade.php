@extends('admin.layouts.app')

@section('title', 'Referrals')

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
                ($p->referrals_count ?? $p->total_count ?? 0) +
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
                'testimonials' => (int) ($p->testimonials_count ?? 0),
                'testimonialsUrl' => route('admin.activities.testimonials', $userId),
                'referrals' => (int) ($p->referrals_count ?? $p->total_count ?? 0),
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

        $formatReferralType = function (?string $type): array {
            $raw = strtolower(trim((string) $type));
            return match ($raw) {
                'customer_referral', 'customer' => [
                    'label' => 'Customer',
                    'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'dotClass' => 'bg-emerald-500',
                ],
                'b2b_referral', 'b2b' => [
                    'label' => 'B2B Referral',
                    'badgeClass' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'dotClass' => 'bg-indigo-500',
                ],
                'b2g_referral', 'b2g' => [
                    'label' => 'B2G Referral',
                    'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200',
                    'dotClass' => 'bg-purple-500',
                ],
                'collaborative_projects', 'collaborative' => [
                    'label' => 'Collaboration',
                    'badgeClass' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    'dotClass' => 'bg-cyan-500',
                ],
                'referral_partnerships', 'partnerships' => [
                    'label' => 'Partnership',
                    'badgeClass' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'dotClass' => 'bg-sky-500',
                ],
                'vendor_referrals', 'vendor' => [
                    'label' => 'Vendor',
                    'badgeClass' => 'bg-teal-50 text-teal-700 border-teal-200',
                    'dotClass' => 'bg-teal-500',
                ],
                'business' => [
                    'label' => 'Business',
                    'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'dotClass' => 'bg-blue-500',
                ],
                'service' => [
                    'label' => 'Service',
                    'badgeClass' => 'bg-violet-50 text-violet-700 border-violet-200',
                    'dotClass' => 'bg-violet-500',
                ],
                'others', 'other' => [
                    'label' => 'Other',
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'dotClass' => 'bg-slate-400',
                ],
                '' => [
                    'label' => '—',
                    'badgeClass' => '',
                    'dotClass' => '',
                ],
                default => [
                    'label' => ucwords(str_replace('_', ' ', $raw)),
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'dotClass' => 'bg-slate-500',
                ],
            };
        };

        $getHotBadge = function ($value): array {
            $val = (int) $value;
            if ($val <= 0) {
                return [
                    'value' => null,
                    'label' => '—',
                    'badgeClass' => '',
                    'iconClass' => '',
                    'title' => '',
                ];
            }

            return match ($val) {
                5 => [
                    'value' => 5,
                    'label' => '5',
                    'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'iconClass' => 'text-rose-600 animate-pulse',
                    'title' => 'Hotness: 5/5 (Very High)',
                ],
                4 => [
                    'value' => 4,
                    'label' => '4',
                    'badgeClass' => 'bg-orange-50 text-orange-700 border-orange-200',
                    'iconClass' => 'text-orange-500',
                    'title' => 'Hotness: 4/5 (High)',
                ],
                3 => [
                    'value' => 3,
                    'label' => '3',
                    'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'iconClass' => 'text-amber-500',
                    'title' => 'Hotness: 3/5 (Medium)',
                ],
                2 => [
                    'value' => 2,
                    'label' => '2',
                    'badgeClass' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                    'iconClass' => 'text-yellow-600',
                    'title' => 'Hotness: 2/5 (Low)',
                ],
                1 => [
                    'value' => 1,
                    'label' => '1',
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'iconClass' => 'text-slate-400',
                    'title' => 'Hotness: 1/5 (Cold)',
                ],
                default => [
                    'value' => $val,
                    'label' => (string) $val,
                    'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'iconClass' => 'text-rose-500',
                    'title' => "Hotness: {$val}",
                ],
            };
        };

        $makeReferralPayload = function($r) use ($displayName, $getInitials, $getAvatarBg, $formatDateTime, $formatDate) {
            $fromName = $r->from_user_name ?? $displayName($r->actor_display_name ?? null, $r->actor_first_name ?? null, $r->actor_last_name ?? null);
            $toName = $r->to_user_name ?? $displayName($r->peer_display_name ?? null, $r->peer_first_name ?? null, $r->peer_last_name ?? null);

            return json_encode([
                'id' => $r->id,
                'from_name' => $fromName,
                'from_company' => $r->from_company ?? '',
                'from_city' => $r->from_city ?? '',
                'from_initials' => $getInitials($fromName),
                'from_bg' => $getAvatarBg($fromName),
                'to_name' => $toName,
                'to_company' => $r->to_company ?? '',
                'to_city' => $r->to_city ?? '',
                'to_initials' => $getInitials($toName),
                'to_bg' => $getAvatarBg($toName),
                'referral_type' => $r->referral_type ?? '',
                'referral_of' => $r->referral_of ?? '',
                'referral_date' => $formatDate($r->referral_date ?? null),
                'phone' => $r->phone ?? '',
                'email' => $r->email ?? '',
                'address' => $r->address ?? '',
                'hot_value' => $r->hot_value ?? null,
                'remarks' => $r->remarks ?? '',
                'created_at' => $formatDateTime($r->created_at ?? null),
            ]);
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Referrals'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($total) }}</div>
                    <div class="metric-label">Total Referrals</div>
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
                    <div class="metric-label">Most Referrals by One Peer</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-danger-subtle text-danger">
                    <i class="bi bi-fire"></i>
                </div>
                <div>
                    <div class="metric-val">
                        {{ number_format($items->where('hot_value', '>', 3)->count()) }}
                    </div>
                    <div class="metric-label">Hot Referrals (Page)</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="referralsFiltersForm" method="GET" action="{{ route('admin.activities.referrals.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.referrals.index'),
                'resetUrl' => route('admin.activities.referrals.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.referrals.export', request()->query()),
                'renderFormTag' => false,
                'formId' => 'referralsFiltersForm',
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
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Referrals</th>
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
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Referrals Log</span>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Referrals count: {{ number_format($items->total()) }}</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-[1400px] w-full border-collapse text-[13px]" style="table-layout:auto;">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">From Company</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:160px;">To</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">To Company</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">Referral Of</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Type</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:100px;">Referral Date</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Phone</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:150px;">Email</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width:75px;">Hot</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:140px;">Remarks</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:80px;">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1 sticky left-0 z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);"><input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="from_company" value="{{ $filters['from_company'] ?? '' }}" placeholder="From company" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="to_user" value="{{ $filters['to_user'] ?? '' }}" placeholder="To name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="to_company" value="{{ $filters['to_company'] ?? '' }}" placeholder="To company" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="referral_of" value="{{ $filters['referral_of'] ?? '' }}" placeholder="Referral of" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="referral_type" value="{{ $filters['referral_type'] ?? '' }}" placeholder="Type" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="date" name="referral_date" value="{{ $filters['referral_date'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="phone" value="{{ $filters['phone'] ?? '' }}" placeholder="Phone" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="email" value="{{ $filters['email'] ?? '' }}" placeholder="Email" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="number" name="hot_value" value="{{ $filters['hot_value'] ?? '' }}" placeholder="Hot" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1"><input type="text" name="remarks" value="{{ $filters['remarks'] ?? '' }}" placeholder="Remarks" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()"></th>
                                    <th class="px-2 py-1">
                                        <select name="has_media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring js-no-select2 js-no-searchable-select" onchange="this.form.submit()">
                                            <option value="">Any</option>
                                            <option value="1" @selected(($filters['has_media'] ?? '') === '1')>Yes</option>
                                            <option value="0" @selected(($filters['has_media'] ?? '') === '0')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'referralsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs surface t2 hover:t1 hover:surface-3 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $referral)
                                    @php
                                        $actorName = $displayName($referral->actor_display_name ?? null, $referral->actor_first_name ?? null, $referral->actor_last_name ?? null);
                                        $peerName = $displayName($referral->peer_display_name ?? null, $referral->peer_first_name ?? null, $referral->peer_last_name ?? null);

                                        $fromName = $referral->from_user_name ?? $actorName;
                                        $toName = $referral->to_user_name ?? $peerName;
                                        $fromId = $referral->actor_id ?? $referral->from_user_id ?? null;
                                        $toId = $referral->user_id ?? $referral->to_user_id ?? null;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-referral="{{ $makeReferralPayload($referral) }}" onclick="openReferralDetailModal(this, event)">
                                        {{-- From peer (sticky) --}}
                                        <td class="px-3 py-2.5 sticky left-0 z-10 surface" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
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
                                                </div>
                                            </div>
                                        </td>
                                        {{-- From Company --}}
                                        <td class="px-3 py-2.5 text-xs t2">
                                            <x-admin-grid-text :text="$referral->from_company ?? '—'" />
                                            @if($referral->from_city)<x-admin-grid-text :text="$referral->from_city" class="t3 text-[10px]" />@endif
                                        </td>
                                        {{-- To peer --}}
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($toName) }}">
                                                    {{ $getInitials($toName) }}
                                                </div>
                                                <div class="font-semibold t1 text-[12.5px]">
                                                    @if(!empty($toId))
                                                        <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $toId }}', event);">
                                                            {{ $toName }}
                                                        </a>
                                                    @else
                                                        {{ $toName }}
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        {{-- To Company --}}
                                        <td class="px-3 py-2.5 text-xs t2">
                                            <x-admin-grid-text :text="$referral->to_company ?? '—'" />
                                            @if($referral->to_city)<x-admin-grid-text :text="$referral->to_city" class="t3 text-[10px]" />@endif
                                        </td>
                                        {{-- Referral Of --}}
                                        <td class="px-3 py-2.5 text-xs font-semibold t1">
                                            <x-admin-grid-text :text="$referral->referral_of ?? '—'" />
                                        </td>
                                        {{-- Type --}}
                                        <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                            @php $typeInfo = $formatReferralType($referral->referral_type ?? null); @endphp
                                            @if(!empty($typeInfo['label']) && $typeInfo['label'] !== '—')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $typeInfo['badgeClass'] }}">
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $typeInfo['dotClass'] }}"></span>
                                                    <span>{{ $typeInfo['label'] }}</span>
                                                </span>
                                            @else
                                                <span class="t3">—</span>
                                            @endif
                                        </td>
                                        {{-- Referral Date --}}
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                            {{ $referral->referral_date ? $formatDate($referral->referral_date) : '—' }}
                                        </td>
                                        {{-- Phone --}}
                                        <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $referral->phone ?? '—' }}</td>
                                        {{-- Email --}}
                                        <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width:200px;">
                                            <x-admin-grid-text :text="$referral->email ?? '—'" :lines="1" />
                                        </td>
                                        {{-- Hot Value --}}
                                        <td class="px-3 py-2.5 text-xs text-center align-middle whitespace-nowrap">
                                            @php $hotInfo = $getHotBadge($referral->hot_value ?? null); @endphp
                                            @if($hotInfo['value'] !== null)
                                                <span class="inline-flex items-center justify-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $hotInfo['badgeClass'] }}" title="{{ $hotInfo['title'] }}">
                                                    <i class="bi bi-fire text-[11px] {{ $hotInfo['iconClass'] }}" aria-hidden="true"></i>
                                                    <span>{{ $hotInfo['label'] }}</span>
                                                </span>
                                            @else
                                                <span class="t3">—</span>
                                            @endif
                                        </td>
                                        {{-- Remarks --}}
                                        <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width:160px;">
                                            <x-admin-grid-text :text="$referral->remarks ?? '—'" :lines="2" />
                                        </td>
                                        {{-- Media --}}
                                        <td class="px-3 py-2.5 text-xs">
                                            @if ((int) ($referral->has_media ?? 0) === 1)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Yes</span>
                                                @if (!empty($referral->media_reference))
                                                    @php
                                                        $mediaReference = (string) $referral->media_reference;
                                                        $mediaUrl = str_starts_with($mediaReference, 'http://') || str_starts_with($mediaReference, 'https://')
                                                            ? $mediaReference
                                                            : url('/api/v1/files/' . $mediaReference);
                                                    @endphp
                                                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="px-2 py-0.5 text-xs font-semibold rounded border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition no-underline ms-1">View</a>
                                                @endif
                                            @else
                                                <span class="t3">No Media</span>
                                            @endif
                                        </td>
                                        {{-- Created At --}}
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                            {{ $formatDateTime($referral->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="text-center text-muted py-4 text-xs t3">No referrals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

    @include('admin.activities.partials.peer-modal')
    @include('admin.activities.referrals.partials.detail-modal')
@endsection
