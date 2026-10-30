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
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };

    $formatVisibilityBadge = function (?string $visibility): array {
        $raw = strtolower(trim((string) $visibility));
        return match ($raw) {
            'public' => [
                'label' => 'Public',
                'badgeClass' => 'bg-sky-50 text-sky-700 border-sky-200',
                'icon' => 'bi-globe2 text-sky-500',
            ],
            'circle' => [
                'label' => 'Circle',
                'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200',
                'icon' => 'bi-people-fill text-purple-500',
            ],
            'connections' => [
                'label' => 'Connections',
                'badgeClass' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'icon' => 'bi-person-check-fill text-indigo-500',
            ],
            'private' => [
                'label' => 'Private',
                'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                'icon' => 'bi-lock-fill text-slate-500',
            ],
            '' => [
                'label' => '—',
                'badgeClass' => '',
                'icon' => '',
            ],
            default => [
                'label' => ucfirst($raw),
                'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                'icon' => 'bi-eye-fill text-slate-500',
            ],
        };
    };

    $formatModerationBadge = function (?string $status): array {
        $raw = strtolower(trim((string) $status));
        return match ($raw) {
            'approved' => [
                'label' => 'Approved',
                'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'dotClass' => 'bg-emerald-500',
            ],
            'pending' => [
                'label' => 'Pending',
                'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                'dotClass' => 'bg-amber-500',
            ],
            'rejected' => [
                'label' => 'Rejected',
                'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                'dotClass' => 'bg-rose-500',
            ],
            'flagged' => [
                'label' => 'Flagged',
                'badgeClass' => 'bg-orange-50 text-orange-700 border-orange-200',
                'dotClass' => 'bg-orange-500',
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
    {{-- Floating Toast for Instant Visibility --}}
    <div id="post-deactivate-toast" class="fixed top-5 right-5 z-[99999] hidden max-w-md bg-emerald-600 text-white px-4 py-3.5 rounded-xl shadow-2xl flex items-center gap-3 transition-all duration-300">
        <i class="bi bi-check-circle-fill text-xl text-emerald-200 shrink-0"></i>
        <div class="text-xs font-semibold" id="post-deactivate-toast-msg"></div>
        <button type="button" onclick="document.getElementById('post-deactivate-toast').classList.add('hidden')" class="ml-auto text-emerald-200 hover:text-white p-1 leading-none">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Flash Alert Container (Inline) --}}
    <div id="flash-alert-container">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; border-left: 5px solid #198754; border-radius: 10px; padding: 13px 18px;">
                <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                <div class="text-sm font-semibold">{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; border-left: 5px solid #ffc107; border-radius: 10px; padding: 13px 18px;">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-warning"></i>
                <div class="text-sm font-semibold">{{ session('warning') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; border-left: 5px solid #dc3545; border-radius: 10px; padding: 13px 18px;">
                <i class="bi bi-x-circle-fill me-2 fs-5 text-danger"></i>
                <div class="text-sm font-semibold">{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; border-left: 5px solid #dc3545; border-radius: 10px; padding: 13px 18px;">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5 text-danger"></i>
                <div class="text-sm font-semibold">{{ $errors->first() }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    </div>

    {{-- Two Campaign Toggles: Referral Invite & Book Reward (/referral/campaign-config) --}}
    @php
        $refCampaign = $referralCampaign ?? app(\App\Services\Referrals\ReferralCampaignConfigService::class)->getOrCreateCurrentConfig();
        $isBookActive = (bool) ($refCampaign->is_active ?? true);
        $isReferralActive = (bool) ($refCampaign->show_floating_badge ?? true);
    @endphp

    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-3" id="referral-campaign-config-container">
        {{-- Campaign 1: Referral Invite Campaign --}}
        <div class="rounded-xl border bs px-4 py-3 surface flex items-center justify-between gap-3 shadow-xs" id="referral-card-box" style="border-left: 4px solid {{ $isReferralActive ? '#10b981' : '#94a3b8' }};">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $isReferralActive ? 'bg-sky-50 text-sky-600 border border-sky-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}" id="referral-icon-box">
                    <i class="bi bi-people-fill text-base"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-display font-bold text-xs tracking-tight text-gray-800">
                            Referral Invite Popup
                        </span>
                        <span id="referral-status-badge" class="px-2 py-0.5 text-[11px] font-semibold rounded-full inline-flex items-center gap-1.5 {{ $isReferralActive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                            <span id="referral-status-dot" class="w-1.5 h-1.5 rounded-full {{ $isReferralActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                            <span id="referral-status-text">{{ $isReferralActive ? 'Active' : 'Off' }}</span>
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-500 m-0">
                        Referral code, invite button &amp; popup
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 px-2.5 py-1.5 rounded-lg shrink-0">
                <span class="text-[11px] font-semibold text-gray-700">Referral:</span>
                <label class="relative inline-flex items-center cursor-pointer select-none m-0">
                    <input type="checkbox" id="referral-toggle-switch" class="sr-only peer" {{ $isReferralActive ? 'checked' : '' }} onchange="toggleCampaign('referral', this.checked)">
                    <div class="w-10 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-600"></div>
                    <span id="referral-toggle-label" class="ml-1.5 text-xs font-bold {{ $isReferralActive ? 'text-sky-600' : 'text-slate-500' }}">
                        {{ $isReferralActive ? 'ON' : 'OFF' }}
                    </span>
                </label>
            </div>
        </div>

        {{-- Campaign 2: Book Reward Campaign --}}
        <div class="rounded-xl border bs px-4 py-3 surface flex items-center justify-between gap-3 shadow-xs" id="book-card-box" style="border-left: 4px solid {{ $isBookActive ? '#10b981' : '#94a3b8' }};">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $isBookActive ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}" id="book-icon-box">
                    <i class="bi bi-book-half text-base"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-display font-bold text-xs tracking-tight text-gray-800">
                            Leadership Book Campaign
                        </span>
                        <span id="book-status-badge" class="px-2 py-0.5 text-[11px] font-semibold rounded-full inline-flex items-center gap-1.5 {{ $isBookActive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                            <span id="book-status-dot" class="w-1.5 h-1.5 rounded-full {{ $isBookActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                            <span id="book-status-text">{{ $isBookActive ? 'Active' : 'Off' }}</span>
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-500 m-0">
                        Leadership Book 10-peer milestone banner
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 px-2.5 py-1.5 rounded-lg shrink-0">
                <span class="text-[11px] font-semibold text-gray-700">Book:</span>
                <label class="relative inline-flex items-center cursor-pointer select-none m-0">
                    <input type="checkbox" id="book-toggle-switch" class="sr-only peer" {{ $isBookActive ? 'checked' : '' }} onchange="toggleCampaign('book', this.checked)">
                    <div class="w-10 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    <span id="book-toggle-label" class="ml-1.5 text-xs font-bold {{ $isBookActive ? 'text-emerald-600' : 'text-slate-500' }}">
                        {{ $isBookActive ? 'ON' : 'OFF' }}
                    </span>
                </label>
            </div>
        </div>
    </div>

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
                    <select name="moderation_status" form="postsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
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
                                <select name="inline_moderation_status" form="postsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
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
                                $mediaUrl = (function ($media, $post) {
                                    if (empty($media)) {
                                        if (($post->post_type ?? null) === 'life_impact_recognition' || ($post->source_type ?? null) === 'life_impact') {
                                            $meta = app(\App\Services\Creative\LifeImpactCreativeGenerator::class)->getRecognitionMeta(2500);
                                            return asset($meta['badge_image'] ?? 'images/life_impact_badges/Legacy Maker.png');
                                        }
                                        return null;
                                    }

                                    $items = [];

                                    if (is_array($media)) {
                                        $items = $media;
                                    } elseif (is_object($media)) {
                                        $items = data_get($media, 'items', []);
                                    }

                                    if (! is_array($items) || empty($items)) {
                                        if (($post->post_type ?? null) === 'life_impact_recognition' || ($post->source_type ?? null) === 'life_impact') {
                                            $meta = app(\App\Services\Creative\LifeImpactCreativeGenerator::class)->getRecognitionMeta(2500);
                                            return asset($meta['badge_image'] ?? 'images/life_impact_badges/Legacy Maker.png');
                                        }
                                        return null;
                                    }

                                    $imageItem = collect($items)->first(function ($item) {
                                        return data_get($item, 'type') === 'image';
                                    });

                                    $candidate = $imageItem ?? (collect($items)->first() ?? []);
                                    $url = data_get($candidate, 'url');

                                    if ($url) {
                                        if (! app()->isProduction() && str_contains($url, 'peersunity.com/storage/')) {
                                            $storageRel = explode('peersunity.com/storage/', $url)[1] ?? '';
                                            return asset('storage/' . ltrim($storageRel, '/'));
                                        }
                                        return $url;
                                    }

                                    $id = data_get($candidate, 'id') ?? data_get($candidate, 'file_id');

                                    if ($id) {
                                        return url('/api/v1/files/' . $id);
                                    }

                                    $path = data_get($candidate, 'path');
                                    if ($path) {
                                        return asset(ltrim($path, '/'));
                                    }

                                    if (($post->post_type ?? null) === 'life_impact_recognition' || ($post->source_type ?? null) === 'life_impact') {
                                        $meta = app(\App\Services\Creative\LifeImpactCreativeGenerator::class)->getRecognitionMeta(2500);
                                        return asset($meta['badge_image'] ?? 'images/life_impact_badges/Legacy Maker.png');
                                    }

                                    return null;
                                })($post->media ?? null, $post);
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
                                {{-- Visibility --}}
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @php $visInfo = $formatVisibilityBadge($post->visibility); @endphp
                                    @if(!empty($visInfo['label']) && $visInfo['label'] !== '—')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $visInfo['badgeClass'] }}">
                                            <i class="bi {{ $visInfo['icon'] }} text-[11px]" aria-hidden="true"></i>
                                            <span>{{ $visInfo['label'] }}</span>
                                        </span>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                {{-- Moderation Status --}}
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @php $modInfo = $formatModerationBadge($post->moderation_status); @endphp
                                    @if(!empty($modInfo['label']) && $modInfo['label'] !== '—')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $modInfo['badgeClass'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $modInfo['dotClass'] }}"></span>
                                            <span>{{ $modInfo['label'] }}</span>
                                        </span>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                {{-- Active --}}
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if($isActive)
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
                                <td class="px-3 py-2.5 text-xs">
                                    @if($isImpact)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-sky-50 text-sky-700 border border-sky-200 mb-1">
                                            <i class="bi bi-lightning-charge-fill text-sky-500 text-[10px]"></i> Impact
                                        </span>
                                    @endif
                                    <div class="t1 font-medium max-w-[250px] admin-grid-text-clamp" data-full-text="{{ $post->content_text }}">{{ $post->content_text }}</div>
                                    @if(!$isActive && !empty($post->deactivation_remarks))
                                        <div class="mt-1 text-[11px] text-rose-700 bg-rose-50/80 border border-rose-200 rounded px-2 py-0.5 inline-flex items-center gap-1 max-w-[250px] truncate" title="Deactivation reason: {{ $post->deactivation_remarks }}">
                                            <i class="bi bi-info-circle-fill text-rose-500 shrink-0"></i>
                                            <span class="truncate"><strong>Reason:</strong> {{ $post->deactivation_remarks }}</span>
                                        </div>
                                    @endif
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
                                                @php
                                                    $previewData = $post->deactivation_preview ?? [];
                                                    $coinsToRev = $previewData['coins_to_reverse'] ?? 0;
                                                    $impactToRev = $previewData['impact_to_reverse'] ?? (int) ($post->life_impacted ?? 1);
                                                    $currCoins = $previewData['current_coins'] ?? (int) ($owner->coins_balance ?? 0);
                                                    $currImpact = $previewData['current_impact'] ?? (int) ($owner->life_impacted_count ?? 0);
                                                @endphp
                                                <button type="button" 
                                                    class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring btn-open-deactivate-modal"
                                                    data-action="{{ route('admin.posts.impacts.deactivate', $post->id) }}"
                                                    data-preview-url="{{ route('admin.posts.impacts.preview-deactivation', $post->id) }}"
                                                    data-post-id="{{ $post->id }}"
                                                    data-owner-name="{{ $ownerName ?: 'Unknown' }}"
                                                    data-owner-initials="{{ $getInitials($ownerName) }}"
                                                    data-owner-bg="{{ $getAvatarBg($ownerName) }}"
                                                    data-company="{{ $owner->company_name ?? $owner->company ?? $owner->business_name ?? '—' }}"
                                                    data-content="{{ preg_replace('/\s+/', ' ', (string) ($post->content_text ?: 'Impact story')) }}"
                                                    data-media="{{ $mediaUrl ?: '' }}"
                                                    data-date="{{ $post->created_at ? \Carbon\Carbon::parse($post->created_at)->format('M d, Y H:i') : '—' }}"
                                                    data-coins-to-reverse="{{ $coinsToRev }}"
                                                    data-impact-to-reverse="{{ $impactToRev }}"
                                                    data-user-coins="{{ $currCoins }}"
                                                    data-user-impact="{{ $currImpact }}"
                                                    data-item-type="impact">
                                                    Deactivate
                                                </button>
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
                                                @php
                                                    $previewData = $post->deactivation_preview ?? [];
                                                    $coinsToRev = $previewData['coins_to_reverse'] ?? 0;
                                                    $impactToRev = $previewData['impact_to_reverse'] ?? 0;
                                                    $currCoins = $previewData['current_coins'] ?? (int) ($owner->coins_balance ?? 0);
                                                    $currImpact = $previewData['current_impact'] ?? (int) ($owner->life_impacted_count ?? 0);
                                                    $targetUserName = ! empty($previewData['user_name']) && $previewData['user_name'] !== 'Member' ? $previewData['user_name'] : ($ownerName ?: 'Unknown');
                                                    $targetCompany = ! empty($previewData['user_company']) && $previewData['user_company'] !== '—' ? $previewData['user_company'] : ($owner->company_name ?? $owner->company ?? $owner->business_name ?? '—');
                                                @endphp
                                                <button type="button" 
                                                    class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring btn-open-deactivate-modal"
                                                    data-action="{{ route('admin.posts.deactivate', $post) }}"
                                                    data-preview-url="{{ route('admin.posts.preview-deactivation', $post) }}"
                                                    data-post-id="{{ $post->id }}"
                                                    data-owner-name="{{ $targetUserName }}"
                                                    data-owner-initials="{{ $getInitials($targetUserName) }}"
                                                    data-owner-bg="{{ $getAvatarBg($targetUserName) }}"
                                                    data-company="{{ $targetCompany }}"
                                                    data-content="{{ preg_replace('/\s+/', ' ', (string) ($post->content_text ?: 'No caption')) }}"
                                                    data-media="{{ $mediaUrl ?: '' }}"
                                                    data-date="{{ $post->created_at ? $post->created_at->format('M d, Y H:i') : '—' }}"
                                                    data-coins-to-reverse="{{ $coinsToRev }}"
                                                    data-impact-to-reverse="{{ $impactToRev }}"
                                                    data-user-coins="{{ $currCoins }}"
                                                    data-user-impact="{{ $currImpact }}"
                                                    data-item-type="post">
                                                    Deactivate
                                                </button>
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

    {{-- Deactivation Confirmation Modal --}}
    <div id="deactivatePostModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm transition-all overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none; margin: 0; padding: 1.25rem;">
        <div class="flex min-h-full items-center justify-center text-center">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all w-full max-w-xl border border-slate-200/90 my-auto" style="box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1);">
                
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-50 to-red-100/80 border border-rose-200 text-rose-600 flex items-center justify-center shrink-0 shadow-2xs">
                            <i class="bi bi-shield-slash-fill text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 leading-tight mb-0" id="modal-title">Deactivate Post</h3>
                            <p class="text-xs text-slate-500 mb-0 mt-0.5">Record reason, reverse awarded rewards & notify member</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close-deactivate-modal w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition border border-transparent hover:border-slate-200 focus-ring">
                        <i class="bi bi-x-lg text-xs"></i>
                    </button>
                </div>

                {{-- Form --}}
                <form id="deactivatePostForm" method="POST" action="" class="m-0 p-0">
                    @csrf
                    <div class="px-6 py-4 space-y-3.5">
                        {{-- Selected Post Details Card --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div id="modal_post_avatar" class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0 shadow-xs ring-2 ring-white"></div>
                                    <div>
                                        <div id="modal_post_owner" class="text-xs font-bold text-slate-900 leading-tight"></div>
                                        <div id="modal_post_company" class="text-[11px] text-slate-500 leading-tight"></div>
                                    </div>
                                </div>
                                <span id="modal_post_date" class="text-[11px] font-medium text-slate-500 bg-white px-2.5 py-0.5 rounded border border-slate-200 shadow-2xs"></span>
                            </div>

                            <div class="flex gap-2.5 items-center bg-white p-2.5 rounded-lg border border-slate-200/80">
                                <div id="modal_post_media_container" class="hidden shrink-0">
                                    <img id="modal_post_media" src="" alt="Post media" class="w-11 h-11 object-cover rounded-md border border-slate-200 shadow-2xs" onerror="this.onerror=null; this.src='{{ asset('images/life_impact_badges/Legacy%20Maker.png') }}';">
                                </div>
                                <div class="text-xs text-slate-700 flex-1 line-clamp-2 leading-relaxed border-l-2 border-indigo-400 pl-2.5 italic">
                                    "<span id="modal_post_content"></span>"
                                </div>
                            </div>

                            <input type="hidden" name="has_options" value="1">

                            {{-- Action Options & Reward Reversal Cards --}}
                            <div class="space-y-2.5 pt-0.5">
                                <div class="grid grid-cols-3 gap-2">
                                    {{-- Option 1: Reverse Coins --}}
                                    <label id="card_opt_reverse_coins" for="opt_reverse_coins" class="relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-amber-50/90 border-amber-300 text-amber-900 shadow-2xs hover:shadow-xs">
                                        <input type="checkbox" name="reverse_coins" id="opt_reverse_coins" value="1" checked class="hidden peer">
                                        
                                        {{-- Top-Right Indicator --}}
                                        <div id="check_indicator_coins" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] shadow-2xs">
                                            <i class="bi bi-check"></i>
                                        </div>
                                        <div id="uncheck_indicator_coins" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full border border-slate-300 text-slate-400 items-center justify-center text-[9px] hidden">
                                            <i class="bi bi-x"></i>
                                        </div>

                                        <div class="flex items-center justify-center gap-1.5 text-xs font-bold leading-tight">
                                            <i class="bi bi-coin text-amber-600 text-sm"></i>
                                            <span>Reverse Coins</span>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-center gap-1">
                                            <span class="text-[11px] font-bold text-amber-900">Deduct:</span>
                                            <input type="number" name="coins_amount" id="modal_coins_amount_input" min="0" step="1"
                                                class="w-16 px-1 py-0.5 text-xs font-black text-center rounded border border-amber-300 bg-white text-amber-900 focus:outline-none focus:ring-1 focus:ring-amber-500 shadow-2xs"
                                                value="0" onclick="event.stopPropagation();">
                                            <span class="text-[10px] font-bold text-amber-900">Coins</span>
                                        </div>
                                        <div id="modal_coins_balance_preview" class="mt-1 text-[10px] text-amber-900/80 font-medium truncate">
                                            Bal: 0 → 0
                                        </div>
                                    </label>

                                    {{-- Option 2: Reverse Impact --}}
                                    <label id="card_opt_reverse_impact" for="opt_reverse_impact" class="relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-purple-50/90 border-purple-300 text-purple-900 shadow-2xs hover:shadow-xs">
                                        <input type="checkbox" name="reverse_impact" id="opt_reverse_impact" value="1" checked class="hidden peer">
                                        
                                        {{-- Top-Right Indicator --}}
                                        <div id="check_indicator_impact" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-purple-500 text-white flex items-center justify-center text-[10px] shadow-2xs">
                                            <i class="bi bi-check"></i>
                                        </div>
                                        <div id="uncheck_indicator_impact" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full border border-slate-300 text-slate-400 items-center justify-center text-[9px] hidden">
                                            <i class="bi bi-x"></i>
                                        </div>

                                        <div class="flex items-center justify-center gap-1.5 text-xs font-bold leading-tight">
                                            <i class="bi bi-stars text-purple-600 text-sm"></i>
                                            <span>Reverse Impact</span>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-center gap-1">
                                            <span class="text-[11px] font-bold text-purple-900">Deduct:</span>
                                            <input type="number" name="impact_amount" id="modal_impact_amount_input" min="0" step="1"
                                                class="w-20 px-1 py-0.5 text-xs font-black text-center rounded border border-purple-300 bg-white text-purple-900 focus:outline-none focus:ring-1 focus:ring-purple-500 shadow-2xs"
                                                value="0" onclick="event.stopPropagation();">
                                            <span class="text-[10px] font-bold text-purple-900">Impact</span>
                                        </div>
                                        <div id="modal_impact_balance_preview" class="mt-1 text-[10px] text-purple-900/80 font-medium truncate">
                                            Score: 0 → 0
                                        </div>
                                    </label>

                                    {{-- Option 3: Notify User --}}
                                    <label id="card_opt_notify_user" for="opt_notify_user" class="relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-sky-50/90 border-sky-300 text-sky-900 shadow-2xs hover:shadow-xs">
                                        <input type="checkbox" name="notify_user" id="opt_notify_user" value="1" checked class="hidden peer">
                                        
                                        {{-- Top-Right Indicator --}}
                                        <div id="check_indicator_notify" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full bg-sky-500 text-white flex items-center justify-center text-[10px] shadow-2xs">
                                            <i class="bi bi-check"></i>
                                        </div>
                                        <div id="uncheck_indicator_notify" class="absolute top-1.5 right-1.5 w-4 h-4 rounded-full border border-slate-300 text-slate-400 items-center justify-center text-[9px] hidden">
                                            <i class="bi bi-x"></i>
                                        </div>

                                        <div class="flex items-center justify-center gap-1.5 text-xs font-bold leading-tight">
                                            <i class="bi bi-bell-fill text-sky-600 text-sm"></i>
                                            <span>Notify User</span>
                                        </div>
                                        <div id="modal_notify_badge" class="mt-1.5 text-xs font-bold tracking-tight text-sky-800 bg-sky-200/70 rounded-md py-0.5 px-1.5">
                                            Push & In-App
                                        </div>
                                        <div id="modal_notify_preview" class="mt-1 text-[10px] text-sky-900/80 font-medium truncate">
                                            Alert sent to peer
                                        </div>
                                    </label>
                                </div>

                                {{-- Dynamic Live Impact / Summary Box --}}
                                <div id="modal_action_summary_box" class="rounded-xl p-2.5 text-xs border border-amber-200 bg-amber-50/80 text-amber-950 flex items-start gap-2 shadow-2xs transition-all duration-200">
                                    <i class="bi bi-info-circle-fill text-amber-600 mt-0.5 shrink-0 text-sm"></i>
                                    <div id="modal_action_summary_text" class="leading-relaxed">
                                        Will reverse awarded rewards & notify member.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Remarks Field --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="modal_deactivation_remarks" class="block text-xs font-bold text-slate-800 mb-0">
                                    Remarks / Reason for Deactivation <span class="text-rose-500">*</span>
                                </label>
                                <span id="char_counter" class="text-xs text-slate-400 font-medium">0 / 1000</span>
                            </div>
                            <div class="relative">
                                <textarea 
                                    id="modal_deactivation_remarks" 
                                    name="remarks" 
                                    rows="3" 
                                    required 
                                    minlength="3" 
                                    maxlength="1000" 
                                    placeholder="Write a clear reason for the member (e.g. duplicate post or image, violates community guidelines)..." 
                                    class="w-full text-xs rounded-xl border border-slate-200 shadow-2xs focus:border-rose-500 focus:ring-2 focus:ring-rose-500/10 p-2.5 text-slate-800 placeholder-slate-400 outline-none transition resize-none"></textarea>
                            </div>
                            <div id="modal_remarks_error" class="hidden text-xs text-rose-600 mt-1 font-semibold flex items-center gap-1">
                                <i class="bi bi-exclamation-circle-fill"></i> Please provide a reason with at least 3 characters.
                            </div>
                        </div>

                        {{-- Quick Template Chips --}}
                        <div>
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                <i class="bi bi-lightning-charge-fill text-amber-500"></i> Quick Suggestions:
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" class="btn-quick-remark group text-xs px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 border border-slate-200 text-slate-600 transition flex items-center gap-1.5 font-medium active:scale-95">
                                    <i class="bi bi-images text-slate-400 group-hover:text-rose-500"></i> Duplicate post or image
                                </button>
                                <button type="button" class="btn-quick-remark group text-xs px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 border border-slate-200 text-slate-600 transition flex items-center gap-1.5 font-medium active:scale-95">
                                    <i class="bi bi-shield-x text-slate-400 group-hover:text-rose-500"></i> Violates community guidelines
                                </button>
                                <button type="button" class="btn-quick-remark group text-xs px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 border border-slate-200 text-slate-600 transition flex items-center gap-1.5 font-medium active:scale-95">
                                    <i class="bi bi-exclamation-octagon text-slate-400 group-hover:text-rose-500"></i> Invalid activity submission
                                </button>
                                <button type="button" class="btn-quick-remark group text-xs px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 border border-slate-200 text-slate-600 transition flex items-center gap-1.5 font-medium active:scale-95">
                                    <i class="bi bi-info-circle text-slate-400 group-hover:text-rose-500"></i> Misleading details
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-slate-50/90 px-6 py-3.5 border-t border-slate-100 flex items-center justify-between rounded-b-2xl">
                        <div class="text-xs text-slate-400 font-medium flex items-center gap-1.5">
                            <i class="bi bi-shield-check text-emerald-500"></i>
                            <span>Reversible anytime</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn-close-deactivate-modal px-3.5 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-200/60 transition focus-ring">
                                Cancel
                            </button>
                            <button type="submit" id="btn_submit_deactivation" class="px-4 py-2 text-xs font-bold rounded-lg bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white shadow-sm shadow-rose-600/25 active:scale-95 transition flex items-center gap-1.5 focus-ring">
                                <i class="bi bi-shield-slash-fill"></i>
                                <span>Confirm Deactivation</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('deactivatePostModal');
        const form = document.getElementById('deactivatePostForm');
        const remarksInput = document.getElementById('modal_deactivation_remarks');
        const remarksError = document.getElementById('modal_remarks_error');
        const charCounter = document.getElementById('char_counter');
        const avatarEl = document.getElementById('modal_post_avatar');
        const ownerEl = document.getElementById('modal_post_owner');
        const companyEl = document.getElementById('modal_post_company');
        const contentEl = document.getElementById('modal_post_content');
        const dateEl = document.getElementById('modal_post_date');
        const mediaContainer = document.getElementById('modal_post_media_container');
        const mediaEl = document.getElementById('modal_post_media');

        const optReverseCoins = document.getElementById('opt_reverse_coins');
        const optReverseImpact = document.getElementById('opt_reverse_impact');
        const optNotifyUser = document.getElementById('opt_notify_user');

        const cardReverseCoins = document.getElementById('card_opt_reverse_coins');
        const cardReverseImpact = document.getElementById('card_opt_reverse_impact');
        const cardNotifyUser = document.getElementById('card_opt_notify_user');

        const checkIndicatorCoins = document.getElementById('check_indicator_coins');
        const uncheckIndicatorCoins = document.getElementById('uncheck_indicator_coins');
        const checkIndicatorImpact = document.getElementById('check_indicator_impact');
        const uncheckIndicatorImpact = document.getElementById('uncheck_indicator_impact');
        const checkIndicatorNotify = document.getElementById('check_indicator_notify');
        const uncheckIndicatorNotify = document.getElementById('uncheck_indicator_notify');

        const coinsAmountInput = document.getElementById('modal_coins_amount_input');
        const impactAmountInput = document.getElementById('modal_impact_amount_input');

        const coinsBalancePreview = document.getElementById('modal_coins_balance_preview');
        const impactBalancePreview = document.getElementById('modal_impact_balance_preview');
        const notifyBadge = document.getElementById('modal_notify_badge');
        const notifyPreview = document.getElementById('modal_notify_preview');

        const summaryBox = document.getElementById('modal_action_summary_box');
        const summaryText = document.getElementById('modal_action_summary_text');

        let currentOwnerName = 'Member';
        let currentCoinsToRev = 0;
        let currentImpactToRev = 0;
        let currentUserCoins = 0;
        let currentUserImpact = 0;

        function refreshOptionCardUi() {
            // 1. Coins Card State
            if (optReverseCoins && optReverseCoins.checked) {
                if (cardReverseCoins) cardReverseCoins.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-amber-50/90 border-amber-300 text-amber-900 shadow-2xs hover:shadow-xs';
                if (checkIndicatorCoins) checkIndicatorCoins.classList.remove('hidden');
                if (uncheckIndicatorCoins) uncheckIndicatorCoins.classList.add('hidden');
                if (coinsAmountInput) coinsAmountInput.disabled = false;
                if (coinsBalancePreview) coinsBalancePreview.textContent = 'Bal: ' + currentUserCoins.toLocaleString() + ' → ' + Math.max(0, currentUserCoins - currentCoinsToRev).toLocaleString();
            } else if (cardReverseCoins) {
                cardReverseCoins.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-slate-100/70 border-slate-200 text-slate-500 opacity-70 hover:opacity-90';
                if (checkIndicatorCoins) checkIndicatorCoins.classList.add('hidden');
                if (uncheckIndicatorCoins) uncheckIndicatorCoins.classList.remove('hidden');
                if (coinsAmountInput) coinsAmountInput.disabled = true;
                if (coinsBalancePreview) coinsBalancePreview.textContent = 'Bal: ' + currentUserCoins.toLocaleString() + ' (No change)';
            }

            // 2. Impact Card State
            if (optReverseImpact && optReverseImpact.checked) {
                if (cardReverseImpact) cardReverseImpact.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-purple-50/90 border-purple-300 text-purple-900 shadow-2xs hover:shadow-xs';
                if (checkIndicatorImpact) checkIndicatorImpact.classList.remove('hidden');
                if (uncheckIndicatorImpact) uncheckIndicatorImpact.classList.add('hidden');
                if (impactAmountInput) impactAmountInput.disabled = false;
                if (impactBalancePreview) impactBalancePreview.textContent = 'Score: ' + currentUserImpact.toLocaleString() + ' → ' + Math.max(0, currentUserImpact - currentImpactToRev).toLocaleString();
            } else if (cardReverseImpact) {
                cardReverseImpact.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-slate-100/70 border-slate-200 text-slate-500 opacity-70 hover:opacity-90';
                if (checkIndicatorImpact) checkIndicatorImpact.classList.add('hidden');
                if (uncheckIndicatorImpact) uncheckIndicatorImpact.classList.remove('hidden');
                if (impactAmountInput) impactAmountInput.disabled = true;
                if (impactBalancePreview) impactBalancePreview.textContent = 'Score: ' + currentUserImpact.toLocaleString() + ' (No change)';
            }

            // 3. Notify Card State
            if (optNotifyUser && optNotifyUser.checked) {
                if (cardNotifyUser) cardNotifyUser.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-sky-50/90 border-sky-300 text-sky-900 shadow-2xs hover:shadow-xs';
                if (checkIndicatorNotify) checkIndicatorNotify.classList.remove('hidden');
                if (uncheckIndicatorNotify) uncheckIndicatorNotify.classList.add('hidden');
                if (notifyBadge) {
                    notifyBadge.textContent = 'Push & In-App';
                    notifyBadge.className = 'mt-1.5 text-xs font-bold tracking-tight text-sky-800 bg-sky-200/70 rounded-md py-0.5 px-1.5';
                }
                if (notifyPreview) notifyPreview.textContent = 'Alert sent to peer';
            } else if (cardNotifyUser) {
                cardNotifyUser.className = 'relative block cursor-pointer select-none rounded-xl border-2 p-2.5 text-center transition-all duration-200 bg-slate-100/70 border-slate-200 text-slate-500 opacity-70 hover:opacity-90';
                if (checkIndicatorNotify) checkIndicatorNotify.classList.add('hidden');
                if (uncheckIndicatorNotify) uncheckIndicatorNotify.classList.remove('hidden');
                if (notifyBadge) {
                    notifyBadge.textContent = 'Silent (No Alert)';
                    notifyBadge.className = 'mt-1.5 text-xs font-semibold tracking-tight text-slate-600 bg-slate-200/80 rounded-md py-0.5 px-1.5';
                }
                if (notifyPreview) notifyPreview.textContent = 'Peer will not be alerted';
            }

            // 4. Live Summary Text
            let actions = [];
            const effectiveCoins = (optReverseCoins && optReverseCoins.checked) ? currentCoinsToRev : 0;
            const effectiveImpact = (optReverseImpact && optReverseImpact.checked) ? currentImpactToRev : 0;

            if (effectiveCoins > 0) {
                actions.push(`deduct <strong class="text-amber-800">-${effectiveCoins.toLocaleString()} Peers Coins</strong>`);
            } else if (optReverseCoins && !optReverseCoins.checked && currentCoinsToRev > 0) {
                actions.push(`<span class="text-slate-500">keep ${currentCoinsToRev.toLocaleString()} coins intact</span>`);
            }

            if (effectiveImpact > 0) {
                actions.push(`deduct <strong class="text-purple-800">-${effectiveImpact.toLocaleString()} Life Impact</strong>`);
            } else if (optReverseImpact && !optReverseImpact.checked && currentImpactToRev > 0) {
                actions.push(`<span class="text-slate-500">keep ${currentImpactToRev.toLocaleString()} life impact intact</span>`);
            }

            let summaryHtml = `Will deactivate post`;
            if (actions.length > 0) {
                summaryHtml += `, ${actions.join(' and ')} from <strong>${currentOwnerName}</strong>`;
            } else {
                summaryHtml += ` for <strong>${currentOwnerName}</strong> without decreasing coins or life impact score`;
            }

            if (optNotifyUser && optNotifyUser.checked) {
                summaryHtml += `, and send them an in-app & push notification explaining the reason.`;
            } else {
                summaryHtml += `, without notifying the member.`;
            }

            if (summaryText) summaryText.innerHTML = summaryHtml;
        }

        if (optReverseCoins) optReverseCoins.addEventListener('change', refreshOptionCardUi);
        if (optReverseImpact) optReverseImpact.addEventListener('change', refreshOptionCardUi);
        if (optNotifyUser) optNotifyUser.addEventListener('change', refreshOptionCardUi);

        if (coinsAmountInput) {
            coinsAmountInput.addEventListener('input', function () {
                currentCoinsToRev = Math.max(0, parseInt(this.value || '0', 10));
                refreshOptionCardUi();
            });
        }
        if (impactAmountInput) {
            impactAmountInput.addEventListener('input', function () {
                currentImpactToRev = Math.max(0, parseInt(this.value || '0', 10));
                refreshOptionCardUi();
            });
        }

        function updateCounter() {
            const len = remarksInput.value.length;
            charCounter.textContent = len + ' / 1000';
            if (len >= 3) {
                charCounter.classList.remove('text-slate-400', 'text-rose-500');
                charCounter.classList.add('text-emerald-600');
            } else {
                charCounter.classList.remove('text-emerald-600');
                charCounter.classList.add('text-slate-400');
            }
        }

        remarksInput.addEventListener('input', function () {
            updateCounter();
            if (this.value.trim().length >= 3) {
                remarksError.classList.add('hidden');
            }
        });

        function openModal(btn) {
            form.action = btn.getAttribute('data-action');
            remarksInput.value = '';
            remarksError.classList.add('hidden');
            updateCounter();

            ownerEl.textContent = btn.getAttribute('data-owner-name') || 'Unknown';
            companyEl.textContent = btn.getAttribute('data-company') || '—';
            contentEl.textContent = btn.getAttribute('data-content') || 'No caption';
            dateEl.textContent = btn.getAttribute('data-date') || '—';

            currentOwnerName = btn.getAttribute('data-owner-name') || 'Member';
            currentCoinsToRev = parseInt(btn.getAttribute('data-coins-to-reverse') || '0', 10);
            currentImpactToRev = parseInt(btn.getAttribute('data-impact-to-reverse') || '0', 10);
            currentUserCoins = parseInt(btn.getAttribute('data-user-coins') || '0', 10);
            currentUserImpact = parseInt(btn.getAttribute('data-user-impact') || '0', 10);

            if (optReverseCoins) optReverseCoins.checked = true;
            if (optReverseImpact) optReverseImpact.checked = true;
            if (optNotifyUser) optNotifyUser.checked = true;

            if (coinsAmountInput) coinsAmountInput.value = currentCoinsToRev;
            if (impactAmountInput) impactAmountInput.value = currentImpactToRev;

            refreshOptionCardUi();

            const previewUrl = btn.getAttribute('data-preview-url');
            if (previewUrl) {
                fetch(previewUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => {
                        if (d && d.success && d.preview) {
                            currentCoinsToRev = parseInt(d.preview.coins_to_reverse || 0, 10);
                            currentImpactToRev = parseInt(d.preview.impact_to_reverse || 0, 10);
                            currentUserCoins = parseInt(d.preview.current_coins || 0, 10);
                            currentUserImpact = parseInt(d.preview.current_impact || 0, 10);
                            if (d.preview.user_name && d.preview.user_name !== 'Member') {
                                currentOwnerName = d.preview.user_name;
                                ownerEl.textContent = currentOwnerName;
                            }
                            if (d.preview.image_url) {
                                mediaEl.src = d.preview.image_url;
                                mediaContainer.classList.remove('hidden');
                            }
                            if (coinsAmountInput) coinsAmountInput.value = currentCoinsToRev;
                            if (impactAmountInput) impactAmountInput.value = currentImpactToRev;
                            refreshOptionCardUi();
                        }
                    })
                    .catch(() => {});
            }

            const initials = btn.getAttribute('data-owner-initials') || 'P';
            const bg = btn.getAttribute('data-owner-bg') || '#6366f1';
            avatarEl.textContent = initials;
            avatarEl.style.backgroundColor = bg;

            const mediaUrl = btn.getAttribute('data-media');
            if (mediaUrl) {
                mediaEl.src = mediaUrl;
                mediaContainer.classList.remove('hidden');
            } else {
                mediaContainer.classList.add('hidden');
                mediaEl.src = '';
            }

            modal.classList.remove('hidden');
            modal.style.display = 'block';
            document.body.classList.add('overflow-hidden');
            setTimeout(function () {
                if (remarksInput) remarksInput.focus();
            }, 50);
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.classList.remove('overflow-hidden');
        }

        // Delegate click for open-modal buttons so dynamically refreshed rows still work
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-open-deactivate-modal');
            if (btn) {
                e.preventDefault();
                openModal(btn);
            }
        });

        document.querySelectorAll('.btn-close-deactivate-modal').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && ! modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        document.querySelectorAll('.btn-quick-remark').forEach(chip => {
            chip.addEventListener('click', function () {
                remarksInput.value = this.textContent.trim();
                remarksError.classList.add('hidden');
                updateCounter();
                remarksInput.focus();
            });
        });

        function renderSuccessAlert(message) {
            const container = document.getElementById('flash-alert-container');
            if (container) {
                container.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; border-left: 5px solid #198754; border-radius: 10px; padding: 13px 18px;">
                        <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                        <div class="text-sm font-semibold">${message}</div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }
            const toast = document.getElementById('post-deactivate-toast');
            const toastMsg = document.getElementById('post-deactivate-toast-msg');
            if (toast && toastMsg) {
                toastMsg.textContent = message;
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 8000);
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Check if there is a pending flash message from deactivation reload
        const storedFlash = sessionStorage.getItem('post_deactivation_success_flash');
        if (storedFlash) {
            sessionStorage.removeItem('post_deactivation_success_flash');
            renderSuccessAlert(storedFlash);
        } else {
            const serverAlert = document.querySelector('#flash-alert-container .alert-success');
            if (serverAlert) {
                const toast = document.getElementById('post-deactivate-toast');
                const toastMsg = document.getElementById('post-deactivate-toast-msg');
                if (toast && toastMsg) {
                    toastMsg.textContent = serverAlert.querySelector('.text-sm')?.textContent || 'Post deactivated successfully.';
                    toast.classList.remove('hidden');
                    setTimeout(() => {
                        toast.classList.add('hidden');
                    }, 8000);
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const val = remarksInput.value.trim();
            if (! val || val.length < 3) {
                remarksError.textContent = 'Please provide a reason with at least 3 characters.';
                remarksError.classList.remove('hidden');
                remarksInput.focus();
                return false;
            }
            remarksError.classList.add('hidden');

            const submitBtn = document.getElementById('btn_submit_deactivation');
            const origContent = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Deactivating...';

            const formData = new FormData(form);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const token = csrfMeta ? csrfMeta.getAttribute('content') : (form.querySelector('input[name="_token"]')?.value || '');

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token
                },
                body: formData
            })
            .then(async function (response) {
                const data = await response.json().catch(function () { return {}; });
                if (! response.ok) {
                    throw new Error(data.message || (data.errors && Object.values(data.errors)[0] ? Object.values(data.errors)[0][0] : 'Failed to deactivate post.'));
                }
                return data;
            })
            .then(function (data) {
                const successMsg = data.message || 'Post deactivated successfully.';
                sessionStorage.setItem('post_deactivation_success_flash', successMsg);
                closeModal();
                window.location.reload();
            })
            .catch(function (err) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origContent;
                remarksError.textContent = err.message || 'An unexpected error occurred while deactivating.';
                remarksError.classList.remove('hidden');
            });
        });
    });

    function showReferralToast(msg, isSuccess = true) {
        const toast = document.getElementById('post-deactivate-toast');
        const toastMsg = document.getElementById('post-deactivate-toast-msg');
        if (!toast || !toastMsg) return;

        toast.className = 'fixed top-5 right-5 z-[99999] max-w-md text-white px-4 py-3.5 rounded-xl shadow-2xl flex items-center gap-3 transition-all duration-300 ' + (isSuccess ? 'bg-emerald-600' : 'bg-slate-700');
        toastMsg.textContent = msg;
        toast.classList.remove('hidden');

        setTimeout(function () {
            toast.classList.add('hidden');
        }, 4500);
    }

    function toggleCampaign(type, isChecked) {
        const isBook = (type === 'book');
        const toggleLabel = document.getElementById(isBook ? 'book-toggle-label' : 'referral-toggle-label');
        const statusBadge = document.getElementById(isBook ? 'book-status-badge' : 'referral-status-badge');
        const statusDot = document.getElementById(isBook ? 'book-status-dot' : 'referral-status-dot');
        const statusText = document.getElementById(isBook ? 'book-status-text' : 'referral-status-text');
        const card = document.getElementById(isBook ? 'book-card-box' : 'referral-card-box');
        const iconBox = document.getElementById(isBook ? 'book-icon-box' : 'referral-icon-box');

        // Optimistic UI update
        if (toggleLabel) {
            toggleLabel.textContent = isChecked ? 'ON' : 'OFF';
            toggleLabel.className = 'ml-1.5 text-xs font-bold ' + (isChecked ? (isBook ? 'text-emerald-600' : 'text-sky-600') : 'text-slate-500');
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

        const payload = isBook
            ? { type: 'book', is_active: isChecked }
            : { type: 'referral', show_floating_badge: isChecked, is_active: isChecked };

        fetch('{{ route("admin.posts.referral-campaign.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(payload)
        })
        .then(async function (res) {
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) throw new Error(data.message || 'Failed to update campaign.');
            return data;
        })
        .then(function (data) {
            const active = isBook
                ? (data.data ? data.data.is_active : isChecked)
                : (data.data ? data.data.show_floating_badge : isChecked);

            if (active) {
                if (statusBadge) statusBadge.className = 'px-2 py-0.5 text-[11px] font-semibold rounded-full inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200';
                if (statusDot) statusDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse';
                if (statusText) statusText.textContent = 'Active';
                if (card) card.style.borderLeftColor = '#10b981';
                if (iconBox) iconBox.className = 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ' + (isBook ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' : 'bg-sky-50 text-sky-600 border border-sky-200');
                showReferralToast(data.message || (isBook ? 'Book campaign is now ACTIVE' : 'Referral campaign is now ACTIVE'), true);
            } else {
                if (statusBadge) statusBadge.className = 'px-2 py-0.5 text-[11px] font-semibold rounded-full inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200';
                if (statusDot) statusDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                if (statusText) statusText.textContent = 'Off';
                if (card) card.style.borderLeftColor = '#94a3b8';
                if (iconBox) iconBox.className = 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-slate-100 text-slate-500 border border-slate-200';
                showReferralToast(data.message || (isBook ? 'Book campaign is now OFF' : 'Referral campaign is now OFF'), false);
            }
        })
        .catch(function (err) {
            const switchEl = document.getElementById(isBook ? 'book-toggle-switch' : 'referral-toggle-switch');
            if (switchEl) switchEl.checked = !isChecked;
            if (toggleLabel) {
                toggleLabel.textContent = !isChecked ? 'ON' : 'OFF';
                toggleLabel.className = 'ml-1.5 text-xs font-bold ' + (!isChecked ? (isBook ? 'text-emerald-600' : 'text-sky-600') : 'text-slate-500');
            }
            showReferralToast(err.message || 'Error updating status', false);
        });
    }
    </script>
@endsection

