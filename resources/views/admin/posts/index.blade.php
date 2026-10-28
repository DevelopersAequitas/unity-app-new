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

    {{-- Referral Campaign Popup & Feed Banner Management Section (/referral/campaign-config) --}}
    @php
        $refCampaign = $referralCampaign ?? app(\App\Services\Referrals\ReferralCampaignConfigService::class)->getOrCreateCurrentConfig();
        $isCampaignActive = (bool) ($refCampaign->is_active ?? true);
        $showFloating = (bool) ($refCampaign->show_floating_badge ?? true);
        $autoOpen = (bool) ($refCampaign->auto_open_badge_screen ?? true);
        $showInvite = (bool) ($refCampaign->show_send_invite_button ?? true);
    @endphp

    <div class="mb-4 rounded-xl border bs p-4 transition-all duration-200 shadow-sm surface" id="referral-campaign-config-card" style="border-left: 5px solid {{ $isCampaignActive ? '#10b981' : '#94a3b8' }};">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm {{ $isCampaignActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}" id="referral-status-icon-box">
                    <i class="bi bi-gift-fill text-xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-display font-bold text-sm tracking-tight text-gray-800">
                            Referral Campaign Feed Popup &amp; Banner
                        </span>
                        <code class="px-2 py-0.5 text-[11px] font-mono rounded bg-slate-100 text-indigo-600 border border-slate-200">/referral/campaign-config</code>
                        <span id="referral-campaign-status-badge" class="px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1.5 {{ $isCampaignActive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                            <span id="referral-campaign-status-dot" class="w-2 h-2 rounded-full {{ $isCampaignActive ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                            <span id="referral-campaign-status-text">{{ $isCampaignActive ? 'Active (Visible on Mobile Feed)' : 'Disabled (Hidden from Mobile Feed)' }}</span>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 mb-0">
                        Controls whether the referral popup/banner (refer 10 peers &amp; receive the Leadership Book) is visible or hidden on the mobile app home feed.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                {{-- Quick ON/OFF Switch --}}
                <div class="flex items-center gap-2.5 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl shadow-xs">
                    <span class="text-xs font-semibold text-gray-700">Mobile Popup:</span>
                    <label class="relative inline-flex items-center cursor-pointer select-none m-0">
                        <input type="checkbox" id="referral-campaign-toggle-switch" class="sr-only peer" {{ $isCampaignActive ? 'checked' : '' }} onchange="toggleReferralCampaign(this.checked)">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        <span id="referral-toggle-label" class="ml-2 text-xs font-bold {{ $isCampaignActive ? 'text-emerald-600' : 'text-slate-500' }}">
                            {{ $isCampaignActive ? 'ON' : 'OFF' }}
                        </span>
                    </label>
                </div>

                {{-- Settings Toggle Button --}}
                <button type="button" onclick="toggleReferralSettingsDrawer()" class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-gray-700 inline-flex items-center gap-1.5 shadow-xs transition">
                    <i class="bi bi-sliders2 text-indigo-500"></i>
                    <span>Configure Details</span>
                    <i class="bi bi-chevron-down text-[10px] text-gray-400 transition-transform duration-200" id="referral-settings-chevron"></i>
                </button>
            </div>
        </div>

        {{-- Collapsible Settings Drawer & Live Mobile Preview --}}
        <div id="referral-settings-drawer" class="hidden mt-4 pt-4 border-t border-slate-200">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Left: Live Mobile Preview Card matching the uploaded screenshot --}}
                <div class="lg:col-span-5 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                            <i class="bi bi-phone"></i> Mobile Feed Preview
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100">Live Appearance</span>
                    </div>

                    <div class="bg-white rounded-xl p-3.5 border border-slate-200 shadow-sm space-y-2.5">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-xs text-slate-900">Peers Global Genie</span>
                            <i class="bi bi-patch-check-fill text-sky-500 text-xs"></i>
                        </div>
                        <p class="text-[10px] text-slate-400 -mt-1 mb-0">World's First Community of Collaboration</p>

                        <div class="font-bold text-xs text-slate-900 leading-snug" id="preview-reward-title">
                            {{ $refCampaign->reward_title ?? 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book' }}
                        </div>

                        <p class="text-[11px] text-slate-600 line-clamp-3 leading-normal mb-0" id="preview-reward-subtitle">
                            {{ $refCampaign->reward_subtitle ?? "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep." }}
                        </p>

                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-medium flex items-center gap-1">
                                <i class="bi bi-book-half"></i> Learning Pillar
                            </span>
                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-medium flex items-center gap-1">
                                <i class="bi bi-coin"></i> +1,000 Coins / Referral
                            </span>
                            <span class="px-2 py-0.5 text-[10px] rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium flex items-center gap-1">
                                <i class="bi bi-truck"></i> Complimentary Delivery
                            </span>
                        </div>

                        {{-- Book Card Preview --}}
                        <div class="flex items-center gap-3 p-2.5 rounded-lg bg-slate-50 border border-slate-200 mt-2">
                            <div class="w-12 h-16 rounded bg-slate-800 text-white flex flex-col items-center justify-center text-center p-1 shrink-0 overflow-hidden shadow">
                                <span class="text-[8px] font-bold text-amber-300">JOHN C. MAXWELL</span>
                                <span class="text-base font-extrabold text-white leading-none">5</span>
                                <span class="text-[7px] text-slate-300">LEVELS</span>
                            </div>
                            <div class="space-y-1">
                                <span class="px-1.5 py-0.5 text-[9px] uppercase font-bold rounded bg-indigo-600 text-white">HARDCOVER EDITION</span>
                                <div class="text-xs font-bold text-slate-900">The 5 Levels of Leadership</div>
                                <div class="text-[10px] text-slate-500">By John C. Maxwell</div>
                                <div class="text-[10px] font-semibold text-amber-600 flex items-center gap-1">
                                    <i class="bi bi-award-fill"></i> Milestone: 10 Referrals
                                </div>
                            </div>
                        </div>

                        <div class="pt-1 flex gap-2">
                            <div class="flex-1 py-1 px-2 rounded bg-slate-100 text-slate-700 text-[10px] font-mono border border-slate-200 text-center">
                                YOUR INVITE CODE: <strong>DEMO123</strong>
                            </div>
                            <button type="button" class="py-1 px-2.5 rounded bg-blue-600 text-white text-[10px] font-semibold flex items-center gap-1">
                                <span>Invite Friends</span> <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Right: Edit Form --}}
                <div class="lg:col-span-7">
                    <form id="referralCampaignConfigForm" method="POST" action="{{ route('admin.posts.referral-campaign.update') }}" class="space-y-3">
                        @csrf
                        <div class="flex items-center justify-between pb-1">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">Campaign Parameters</h3>
                            <span class="text-[11px] text-slate-400">Syncs immediately with <code>/referral/campaign-config</code></span>
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-slate-700 mb-1">Reward Headline / Title</label>
                            <input type="text" name="reward_title" id="form-reward-title" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 surface" value="{{ $refCampaign->reward_title ?? 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book' }}" oninput="document.getElementById('preview-reward-title').textContent = this.value">
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-slate-700 mb-1">Reward Description / Subtitle</label>
                            <textarea name="reward_subtitle" id="form-reward-subtitle" rows="3" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 surface" oninput="document.getElementById('preview-reward-subtitle').textContent = this.value">{{ $refCampaign->reward_subtitle ?? "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep." }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-slate-700 mb-1">Floating Badge Delay (Seconds)</label>
                                <input type="number" name="floating_badge_delay_seconds" min="0" max="600" class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 surface" value="{{ $refCampaign->floating_badge_delay_seconds ?? 10 }}">
                            </div>
                            <div>
                                <label class="block text-[11px] uppercase tracking-wider font-semibold text-slate-700 mb-1">Auto-Open Popup Delay (Seconds)</label>
                                <input type="number" name="auto_open_delay_seconds" min="0" max="600" class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 surface" value="{{ $refCampaign->auto_open_delay_seconds ?? 30 }}">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold text-slate-700 mb-1">Reward Image URL (Optional)</label>
                            <input type="url" name="reward_image_url" class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 surface" placeholder="https://..." value="{{ $refCampaign->reward_image_url ?? '' }}">
                        </div>

                        <div class="flex flex-wrap gap-4 pt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="is_active" value="1" {{ $isCampaignActive ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Active Campaign</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="show_floating_badge" value="1" {{ $showFloating ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Show Floating Badge</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="auto_open_badge_screen" value="1" {{ $autoOpen ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Auto-Open Badge Screen</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="show_send_invite_button" value="1" {{ $showInvite ? 'checked' : '' }} class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Show Send Invite Button</span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" onclick="toggleReferralSettingsDrawer()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 transition">
                                Cancel
                            </button>
                            <button type="submit" id="btn_save_referral_config" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition flex items-center gap-1.5 shadow-sm">
                                <i class="bi bi-check-lg"></i> Save Campaign Settings
                            </button>
                        </div>
                    </form>
                </div>
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
                                                <button type="button" 
                                                    class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring btn-open-deactivate-modal"
                                                    data-action="{{ route('admin.posts.impacts.deactivate', $post->id) }}"
                                                    data-post-id="{{ $post->id }}"
                                                    data-owner-name="{{ $ownerName ?: 'Unknown' }}"
                                                    data-owner-initials="{{ $getInitials($ownerName) }}"
                                                    data-owner-bg="{{ $getAvatarBg($ownerName) }}"
                                                    data-company="{{ $owner->company_name ?? $owner->company ?? $owner->business_name ?? '—' }}"
                                                    data-content="{{ $post->content_text ?: 'Impact story' }}"
                                                    data-media="{{ $mediaUrl ?: '' }}"
                                                    data-date="{{ $post->created_at ? \Carbon\Carbon::parse($post->created_at)->format('M d, Y H:i') : '—' }}">
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
                                                <button type="button" 
                                                    class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring btn-open-deactivate-modal"
                                                    data-action="{{ route('admin.posts.deactivate', $post) }}"
                                                    data-post-id="{{ $post->id }}"
                                                    data-owner-name="{{ $ownerName ?: 'Unknown' }}"
                                                    data-owner-initials="{{ $getInitials($ownerName) }}"
                                                    data-owner-bg="{{ $getAvatarBg($ownerName) }}"
                                                    data-company="{{ $owner->company_name ?? $owner->company ?? $owner->business_name ?? '—' }}"
                                                    data-content="{{ $post->content_text ?: 'No caption' }}"
                                                    data-media="{{ $mediaUrl ?: '' }}"
                                                    data-date="{{ $post->created_at ? $post->created_at->format('M d, Y H:i') : '—' }}">
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
    <div id="deactivatePostModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm transition-all overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="margin: 0; padding: 1.25rem;">
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
                                    <img id="modal_post_media" src="" alt="Post media" class="w-11 h-11 object-cover rounded-md border border-slate-200 shadow-2xs">
                                </div>
                                <div class="text-xs text-slate-700 flex-1 line-clamp-2 leading-relaxed border-l-2 border-indigo-400 pl-2.5 italic">
                                    "<span id="modal_post_content"></span>"
                                </div>
                            </div>

                            {{-- Action Consequences Highlight Bar --}}
                            <div class="flex items-center justify-between gap-2 pt-0.5">
                                <div class="flex-1 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 text-center">
                                    <span class="text-xs font-bold text-amber-800 flex items-center justify-center gap-1.5">
                                        <i class="bi bi-coin text-amber-600"></i> Reverse Coins
                                    </span>
                                </div>
                                <div class="flex-1 bg-purple-50 border border-purple-200 rounded-lg px-2 py-1.5 text-center">
                                    <span class="text-xs font-bold text-purple-800 flex items-center justify-center gap-1.5">
                                        <i class="bi bi-stars text-purple-600"></i> Reverse Impact
                                    </span>
                                </div>
                                <div class="flex-1 bg-sky-50 border border-sky-200 rounded-lg px-2 py-1.5 text-center">
                                    <span class="text-xs font-bold text-sky-800 flex items-center justify-center gap-1.5">
                                        <i class="bi bi-bell-fill text-sky-600"></i> Notify User
                                    </span>
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
            document.body.classList.add('overflow-hidden');
            remarksInput.focus();
        }

        function closeModal() {
            modal.classList.add('hidden');
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

    function toggleReferralSettingsDrawer() {
        const drawer = document.getElementById('referral-settings-drawer');
        const chevron = document.getElementById('referral-settings-chevron');
        if (!drawer) return;
        const isHidden = drawer.classList.contains('hidden');
        if (isHidden) {
            drawer.classList.remove('hidden');
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        } else {
            drawer.classList.add('hidden');
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        }
    }

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

    function toggleReferralCampaign(isChecked) {
        const toggleLabel = document.getElementById('referral-toggle-label');
        const statusBadge = document.getElementById('referral-campaign-status-badge');
        const statusDot = document.getElementById('referral-campaign-status-dot');
        const statusText = document.getElementById('referral-campaign-status-text');
        const card = document.getElementById('referral-campaign-config-card');
        const iconBox = document.getElementById('referral-status-icon-box');

        // Optimistic UI update
        if (toggleLabel) {
            toggleLabel.textContent = isChecked ? 'ON' : 'OFF';
            toggleLabel.className = 'ml-2 text-xs font-bold ' + (isChecked ? 'text-emerald-600' : 'text-slate-500');
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta ? csrfMeta.getAttribute('content') : '{{ csrf_token() }}';

        fetch('{{ route("admin.posts.referral-campaign.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ is_active: isChecked })
        })
        .then(async function (res) {
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) throw new Error(data.message || 'Failed to update referral campaign.');
            return data;
        })
        .then(function (data) {
            const active = data.data ? data.data.is_active : isChecked;
            if (active) {
                if (statusBadge) statusBadge.className = 'px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200';
                if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                if (statusText) statusText.textContent = 'Active (Visible on Mobile Feed)';
                if (card) card.style.borderLeftColor = '#10b981';
                if (iconBox) iconBox.className = 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm bg-emerald-50 text-emerald-600 border border-emerald-200';
                showReferralToast(data.message || 'Referral campaign popup is now ENABLED on mobile feed.', true);
            } else {
                if (statusBadge) statusBadge.className = 'px-2.5 py-0.5 text-xs font-semibold rounded-full inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200';
                if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-slate-400';
                if (statusText) statusText.textContent = 'Disabled (Hidden from Mobile Feed)';
                if (card) card.style.borderLeftColor = '#94a3b8';
                if (iconBox) iconBox.className = 'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm bg-slate-100 text-slate-500 border border-slate-200';
                showReferralToast(data.message || 'Referral campaign popup is now DISABLED (Hidden from mobile feed).', false);
            }
        })
        .catch(function (err) {
            const switchEl = document.getElementById('referral-campaign-toggle-switch');
            if (switchEl) switchEl.checked = !isChecked;
            if (toggleLabel) {
                toggleLabel.textContent = !isChecked ? 'ON' : 'OFF';
                toggleLabel.className = 'ml-2 text-xs font-bold ' + (!isChecked ? 'text-emerald-600' : 'text-slate-500');
            }
            showReferralToast(err.message || 'Error updating status', false);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const configForm = document.getElementById('referralCampaignConfigForm');
        if (configForm) {
            configForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = document.getElementById('btn_save_referral_config');
                const origText = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                }

                const formData = new FormData(configForm);
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const token = csrfMeta ? csrfMeta.getAttribute('content') : (configForm.querySelector('input[name="_token"]')?.value || '');

                fetch(configForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    },
                    body: formData
                })
                .then(async function (res) {
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(data.message || 'Failed to update campaign configuration.');
                    return data;
                })
                .then(function (data) {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    }
                    showReferralToast(data.message || 'Referral campaign configuration updated successfully.', true);
                    if (data.data) {
                        const active = !!data.data.is_active;
                        const switchEl = document.getElementById('referral-campaign-toggle-switch');
                        if (switchEl) switchEl.checked = active;
                        const toggleLabel = document.getElementById('referral-toggle-label');
                        if (toggleLabel) {
                            toggleLabel.textContent = active ? 'ON' : 'OFF';
                            toggleLabel.className = 'ml-2 text-xs font-bold ' + (active ? 'text-emerald-600' : 'text-slate-500');
                        }
                    }
                })
                .catch(function (err) {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    }
                    showReferralToast(err.message || 'Error updating configuration', false);
                });
            });
        }
    });
    </script>
@endsection

