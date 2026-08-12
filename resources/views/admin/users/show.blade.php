@extends('admin.layouts.app')

@section('title', 'Member Profile - ' . $user->adminDisplayName())

@include('admin.partials.grid-head')

@push('styles')
<style>
    .profile-tab-btn {
        color: #64748b;
        font-weight: 500;
        font-size: 13px;
        padding: 8px 16px;
        border-radius: 9999px;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        border: 1px solid transparent;
        background: transparent;
    }
    .profile-tab-btn:hover {
        color: #0f172a;
        background-color: #f1f5f9;
    }
    .profile-tab-btn.active {
        color: #4338ca;
        background-color: #eef2ff;
        border-color: #c7d2fe;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
@php
    $avatarUrl = $user->profile_photo_url ?? ($user->profile_photo_file_id ? url('/api/v1/files/' . $user->profile_photo_file_id) : null);
    $initials = (function($name) {
        $clean = trim((string)$name);
        if (!$clean) return '?';
        $parts = preg_split('/\s+/', $clean);
        if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 2));
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    })($user->adminDisplayName());

    $statusValue = strtolower((string)($user->status ?? 'active'));
    $statusObj = match($statusValue) {
        'active' => [
            'label' => 'Active',
            'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'dotClass' => 'bg-emerald-500',
        ],
        'inactive' => [
            'label' => 'Inactive',
            'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
            'dotClass' => 'bg-slate-400',
        ],
        default => [
            'label' => ucfirst($statusValue),
            'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
            'dotClass' => 'bg-amber-500',
        ],
    };

    $membershipLabel = Str::headline(str_replace('_', ' ', (string)($user->membership_status ?? 'Free Peer')));
    $membershipBadgeClass = match(strtolower((string)($user->membership_status ?? ''))) {
        'gold' => 'bg-amber-50 text-amber-700 border-amber-200',
        'platinum' => 'bg-purple-50 text-purple-700 border-purple-200',
        'only unity peer', 'global peer' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        default => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    };

    $cityName = $user->city->name ?? $user->city ?? '—';
    if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
        $decoded = json_decode(trim($cityName), true);
        $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
    }

    $companyName = $user->company_name ?? $user->company ?? $user->business_name ?? '—';
    $primaryCircleName = $selectedCircle->name ?? $user->circleMembers->first()?->circle?->name ?? 'No Circle';
@endphp

<div id="grid-root-container" class="light max-w-7xl mx-auto space-y-6 pb-12">
    <!-- Top Action Bar -->
    <div class="flex flex-wrap justify-between items-center gap-3 pt-2">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full border bs bg-white text-xs font-semibold t2 hover:t1 hover:surface-2 transition shadow-xs no-underline">
            <i class="bi bi-arrow-left text-[11px]"></i>
            <span>Back to Peers</span>
        </a>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.users.edit', $user->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-sm no-underline">
                <i class="bi bi-pencil-square text-[11px]"></i>
                <span>Edit Peer</span>
            </a>
        </div>
    </div>

    <!-- Hero Profile Card (Dribbble-inspired) -->
    <div class="rounded-2xl border bs surface p-6 sm:p-8 relative shadow-sm text-center">
        <!-- Avatar -->
        <div class="flex justify-center mb-4">
            <div class="relative">
                @if($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $user->adminDisplayName() }}" class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover shadow-md ring-4 ring-slate-100/80 mx-auto" onerror="this.onerror=null; this.outerHTML='<div class=\'w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-indigo-600 font-bold flex items-center justify-center text-white text-2xl shadow-md ring-4 ring-slate-100/80 mx-auto\'>{{ $initials }}</div>';" />
                @else
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-indigo-600 font-bold flex items-center justify-center text-white text-2xl shadow-md ring-4 ring-slate-100/80 mx-auto">
                        {{ $initials }}
                    </div>
                @endif
                <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full {{ $statusObj['dotClass'] }} ring-2 ring-white" title="{{ $statusObj['label'] }}"></span>
            </div>
        </div>

        <!-- Category Tag -->
        <div class="text-[11px] font-semibold uppercase tracking-wider text-indigo-500 mb-1">
            {{ $user->mainBusinessCategory?->name ?? (is_array($user->industry_tags) ? implode(', ', $user->industry_tags) : ($user->industry_tags ?: 'Peers Global Community')) }}
        </div>

        <!-- Name & Designation -->
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-slate-900 m-0">
            {{ $user->adminDisplayName() }}
        </h1>
        <p class="text-sm t2 mt-1 mb-4">
            {{ $user->designation ?: 'Member' }} @if($companyName !== '—') <span class="t3">•</span> <span class="font-medium text-slate-700">{{ $companyName }}</span> @endif
        </p>

        <!-- Meta Pills Row -->
        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
            @if($user->phone)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-mono bg-slate-100 text-slate-700 border border-slate-200/60">
                    <i class="bi bi-telephone text-[11px] text-slate-400"></i>
                    <span>{{ $user->phone }}</span>
                </span>
            @endif

            @if($user->email)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs bg-slate-100 text-slate-700 border border-slate-200/60">
                    <i class="bi bi-envelope text-[11px] text-slate-400"></i>
                    <span>{{ $user->email }}</span>
                </span>
            @endif

            @if($primaryCircleName !== 'No Circle')
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                    <i class="bi bi-people text-[11px]"></i>
                    <span>{{ $primaryCircleName }}</span>
                </span>
            @endif

            @if($user->introducedBy)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs bg-slate-100 text-slate-700 border border-slate-200/60">
                    <i class="bi bi-person-check text-[11px] text-slate-400"></i>
                    <span>Introduced by {{ $user->introducedBy->adminDisplayName() }}</span>
                </span>
            @endif

            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border {{ $statusObj['badgeClass'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $statusObj['dotClass'] }}"></span>
                <span>{{ $statusObj['label'] }}</span>
            </span>

            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border {{ $membershipBadgeClass }}">
                <i class="bi bi-shield-check text-[11px]"></i>
                <span>{{ $membershipLabel }}</span>
            </span>
        </div>
    </div>

    <!-- Quick Stats Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Coins -->
        <div class="rounded-xl border bs surface p-4 sm:p-5 relative shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium t3">Coins Balance</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center text-sm border border-amber-200/60">
                    <i class="bi bi-coin"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-display font-bold text-slate-900 font-mono">
                    {{ number_format((int)($user->coins_balance ?? 0)) }}
                </div>
                <div class="text-[11px] t3 mt-0.5">
                    {{ $user->coin_milestone_title ?: 'Active Balance' }}
                </div>
            </div>
        </div>

        <!-- Life Impacted -->
        <div class="rounded-xl border bs surface p-4 sm:p-5 relative shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium t3">Life Impacted</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center text-sm border border-rose-200/60">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl font-display font-bold text-slate-900 font-mono">
                    {{ (int)($user->life_impacted_count ?? 0) }}
                </div>
                <div class="text-[11px] t3 mt-0.5">
                    Total Peer Impacts
                </div>
            </div>
        </div>

        <!-- Membership -->
        <div class="rounded-xl border bs surface p-4 sm:p-5 relative shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium t3">Membership Plan</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center text-sm border border-indigo-200/60">
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-lg font-display font-bold text-slate-900 truncate" title="{{ $membershipLabel }}">
                    {{ $membershipLabel }}
                </div>
                <div class="text-[11px] t3 mt-0.5">
                    @if($user->membership_ends_at)
                        Expires {{ optional($user->membership_ends_at)->format('d M Y') }}
                    @else
                        No Expiry Date
                    @endif
                </div>
            </div>
        </div>

        <!-- Joined Date -->
        <div class="rounded-xl border bs surface p-4 sm:p-5 relative shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium t3">Member Since</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center text-sm border border-emerald-200/60">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-lg font-display font-bold text-slate-900 font-mono">
                    {{ optional($user->created_at)->format('d M Y') ?: '—' }}
                </div>
                <div class="text-[11px] t3 mt-0.5">
                    {{ optional($user->created_at)->diffForHumans() ?: '—' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b bs">
        <button type="button" class="profile-tab-btn active" onclick="switchProfileTab('overview', this)">
            <i class="bi bi-person-lines-fill text-[12px]"></i> Overview & Bio
        </button>
        <button type="button" class="profile-tab-btn" onclick="switchProfileTab('business', this)">
            <i class="bi bi-briefcase text-[12px]"></i> Business & Industry
        </button>
        <button type="button" class="profile-tab-btn" onclick="switchProfileTab('circle', this)">
            <i class="bi bi-people text-[12px]"></i> Circle & Leadership
        </button>
        <button type="button" class="profile-tab-btn" onclick="switchProfileTab('membership', this)">
            <i class="bi bi-shield-check text-[12px]"></i> Membership & Billing
        </button>
        <button type="button" class="profile-tab-btn" onclick="switchProfileTab('social', this)">
            <i class="bi bi-share text-[12px]"></i> Interests & Social
        </button>
    </div>

    <!-- Tab Panes Content -->
    <div>
        <!-- TAB 1: OVERVIEW & BIO -->
        <div id="tab-pane-overview" class="profile-tab-pane space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Left: Bio Cards -->
                <div class="lg:col-span-7 space-y-4">
                    <!-- Short Bio -->
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-500 mb-2.5">
                            <i class="bi bi-quote text-sm"></i> Short Bio
                        </div>
                        <p class="text-sm t1 leading-relaxed m-0 italic bg-slate-50 border border-slate-200/60 rounded-xl p-4">
                            {{ $user->short_bio ?: 'No short bio provided.' }}
                        </p>
                    </div>

                    <!-- Long Bio / Experience Summary -->
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-500 mb-2.5">
                            <i class="bi bi-file-text text-sm"></i> Experience Summary & Long Bio
                        </div>
                        @if($user->long_bio_html)
                            <div class="text-xs t1 leading-relaxed prose max-w-none bg-slate-50 border border-slate-200/60 rounded-xl p-4">
                                {!! $user->long_bio_html !!}
                            </div>
                        @elseif($user->experience_summary)
                            <div class="text-xs t1 leading-relaxed bg-slate-50 border border-slate-200/60 rounded-xl p-4 whitespace-pre-line">
                                {{ $user->experience_summary }}
                            </div>
                        @else
                            <p class="text-xs t3 m-0 bg-slate-50 border border-slate-200/60 rounded-xl p-4">
                                No experience summary or detailed bio available.
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Right: Personal Details -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Personal & Account Details
                        </h6>
                        <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                            <dt class="t3 font-medium">Peer ID</dt>
                            <dd class="col-span-2 font-mono font-semibold t1">{{ $user->peer_id ?? ('PGU-'.substr($user->id, 0, 8)) }}</dd>

                            <dt class="t3 font-medium">Full Name</dt>
                            <dd class="col-span-2 font-semibold t1">{{ $user->first_name }} {{ $user->last_name }}</dd>

                            <dt class="t3 font-medium">Gender</dt>
                            <dd class="col-span-2 t1 capitalize">{{ $user->gender ?: '—' }}</dd>

                            <dt class="t3 font-medium">Date of Birth</dt>
                            <dd class="col-span-2 t1">{{ optional($user->dob)->format('d M Y') ?: '—' }}</dd>

                            <dt class="t3 font-medium">Experience</dt>
                            <dd class="col-span-2 t1">{{ $user->experience_years ? $user->experience_years . ' Years' : '—' }}</dd>

                            <dt class="t3 font-medium">Location</dt>
                            <dd class="col-span-2 t1">{{ $cityName }}, {{ $user->state ?: '—' }}, {{ $user->country ?: 'India' }}</dd>

                            <dt class="t3 font-medium">Directory Listing</dt>
                            <dd class="col-span-2">
                                @if(($user->community_directory_listing ?? 'Yes') === 'Yes')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="bi bi-check-circle-fill text-[10px]"></i> Visible
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        Hidden
                                    </span>
                                @endif
                            </dd>

                            <dt class="t3 font-medium">Public Slug</dt>
                            <dd class="col-span-2 font-mono text-[11px] text-indigo-600">
                                @if($user->public_profile_slug)
                                    <a href="{{ url('/' . $user->public_profile_slug) }}" target="_blank" class="hover:underline no-underline inline-flex items-center gap-1">
                                        <span>/{{ $user->public_profile_slug }}</span>
                                        <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                    </a>
                                @else
                                    <span class="t3">Not generated</span>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: BUSINESS & INDUSTRY -->
        <div id="tab-pane-business" class="profile-tab-pane hidden space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Business Info -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Business Profile
                        </h6>
                        <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                            <dt class="t3 font-medium">Company Name</dt>
                            <dd class="col-span-2 font-semibold t1">{{ $companyName }}</dd>

                            <dt class="t3 font-medium">Designation</dt>
                            <dd class="col-span-2 font-medium t1">{{ $user->designation ?: '—' }}</dd>

                            <dt class="t3 font-medium">Business Type</dt>
                            <dd class="col-span-2 t1">{{ $user->business_type ?: '—' }}</dd>

                            <dt class="t3 font-medium">Turnover Range</dt>
                            <dd class="col-span-2 t1">{{ $user->turnover_range ?: ($user->annual_revenue_range ?: '—') }}</dd>

                            <dt class="t3 font-medium">Website</dt>
                            <dd class="col-span-2">
                                @if($user->business_website || $user->website)
                                    <a href="{{ $user->business_website ?: $user->website }}" target="_blank" class="text-indigo-600 hover:underline inline-flex items-center gap-1 no-underline">
                                        <span>{{ $user->business_website ?: $user->website }}</span>
                                        <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                    </a>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </dd>

                            <dt class="t3 font-medium">Business Address</dt>
                            <dd class="col-span-2 t2">{{ $user->business_address ?: '—' }}</dd>

                            <dt class="t3 font-medium">Business City</dt>
                            <dd class="col-span-2 t1">{{ $user->business_city ?: $cityName }}</dd>
                        </dl>
                    </div>

                    <!-- Sustainability -->
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-emerald-600 uppercase tracking-wider mb-3">
                            🌱 Sustainability & ESG
                        </h6>
                        @if($user->sustainability_contribution)
                            <div class="text-xs t2 bg-emerald-50/50 border border-emerald-200/60 rounded-xl p-3.5 mb-3 leading-relaxed">
                                {{ $user->sustainability_contribution }}
                            </div>
                        @endif
                        @if(!empty($user->sustainability_areas))
                            <div class="mb-2">
                                <span class="text-[11px] t3 font-medium block mb-1.5">Sustainability Focus Areas:</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach((array)$user->sustainability_areas as $area)
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            {{ $area }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Category Hierarchy & Skills -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Industry & Categories
                        </h6>
                        <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                            <dt class="t3 font-medium">Main Category</dt>
                            <dd class="col-span-2 font-semibold t1">{{ $user->mainBusinessCategory?->name ?: '—' }}</dd>

                            <dt class="t3 font-medium">Business Sub-Cat</dt>
                            <dd class="col-span-2 t1">{{ $user->businessCategory?->name ?: ($user->business_sub_category ?: '—') }}</dd>

                            <dt class="t3 font-medium">Industry Tags</dt>
                            <dd class="col-span-2">
                                @if(!empty($user->industry_tags))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(is_array($user->industry_tags) ? $user->industry_tags : explode(',', (string)$user->industry_tags) as $tag)
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ trim($tag) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </dd>
                        </dl>
                    </div>

                    <!-- Skills & Recognitions -->
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Skills & Recognitions
                        </h6>
                        <div class="space-y-3 text-xs">
                            <div>
                                <span class="t3 font-medium block mb-1.5">Skills:</span>
                                @if(!empty($user->skills))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(is_array($user->skills) ? $user->skills : explode(',', (string)$user->skills) as $skill)
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                {{ trim($skill) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="t3 text-xs italic">No skills listed</span>
                                @endif
                            </div>

                            @if(!empty($user->special_recognitions))
                                <div>
                                    <span class="t3 font-medium block mb-1.5">Special Recognitions:</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(is_array($user->special_recognitions) ? $user->special_recognitions : explode(',', (string)$user->special_recognitions) as $rec)
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                                ⭐ {{ trim($rec) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: CIRCLE & LEADERSHIP -->
        <div id="tab-pane-circle" class="profile-tab-pane hidden space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Primary Circle Details -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Primary Circle Membership
                        </h6>
                        @if($selectedCircle)
                            <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                                <dt class="t3 font-medium">Circle Name</dt>
                                <dd class="col-span-2 font-semibold t1 text-[13px] text-indigo-600">{{ $selectedCircle->name }}</dd>

                                <dt class="t3 font-medium">Circle Location</dt>
                                <dd class="col-span-2 t1">{{ $selectedCircle->city ?? '—' }}, {{ $selectedCircle->country ?? 'India' }}</dd>

                                <dt class="t3 font-medium">Meeting Format</dt>
                                <dd class="col-span-2 t1">{{ $user->circle_meeting_mode ?: '—' }} ({{ $user->circle_meeting_frequency ?: 'Weekly' }})</dd>

                                <dt class="t3 font-medium">Joined Circle At</dt>
                                <dd class="col-span-2 font-mono t2">{{ optional($user->circle_joined_at)->format('d M Y') ?: '—' }}</dd>

                                <dt class="t3 font-medium">Circle Expiry</dt>
                                <dd class="col-span-2 font-mono t2">{{ optional($user->circle_expires_at)->format('d M Y') ?: 'No Expiry' }}</dd>
                            </dl>
                        @else
                            <div class="text-center py-6 t3 text-xs bg-slate-50 rounded-xl border border-slate-200/60">
                                <i class="bi bi-people text-2xl d-block mb-1 opacity-50"></i>
                                Not assigned to any primary circle.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Admin Roles & Introductions -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Administrative Roles & Leadership
                        </h6>
                        <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                            <dt class="t3 font-medium">Assigned Roles</dt>
                            <dd class="col-span-2">
                                @if(!empty($assignedAdminRoles) && $assignedAdminRoles->isNotEmpty())
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($assignedAdminRoles as $role)
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="t3">Standard Member (No Admin Roles)</span>
                                @endif
                            </dd>

                            @if(!empty($assignedDedMapping))
                                <dt class="t3 font-medium">DED District</dt>
                                <dd class="col-span-2 font-medium t1">{{ $assignedDedMapping->district_name ?? '—' }} ({{ $assignedDedMapping->state_name ?? '—' }})</dd>
                            @endif

                            <dt class="t3 font-medium">Introduced By</dt>
                            <dd class="col-span-2 font-medium t1">{{ $user->introducedBy?->adminDisplayName() ?: 'Direct Platform Sign-up' }}</dd>

                            <dt class="t3 font-medium">Members Introduced</dt>
                            <dd class="col-span-2 font-mono font-semibold text-indigo-600">{{ (int)($user->members_introduced_count ?? 0) }} Peers</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: MEMBERSHIP & BILLING -->
        <div id="tab-pane-membership" class="profile-tab-pane hidden space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Subscription & Plan Details
                        </h6>
                        <dl class="grid grid-cols-3 gap-y-3.5 text-xs mb-0">
                            <dt class="t3 font-medium">Membership Status</dt>
                            <dd class="col-span-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $membershipBadgeClass }}">
                                    {{ $membershipLabel }}
                                </span>
                            </dd>

                            <dt class="t3 font-medium">Zoho Plan Code</dt>
                            <dd class="col-span-2 font-mono text-xs t1">{{ $user->zoho_plan_code ?: '—' }}</dd>

                            <dt class="t3 font-medium">Zoho Customer ID</dt>
                            <dd class="col-span-2 font-mono text-xs t2">{{ $user->zoho_customer_id ?: '—' }}</dd>

                            <dt class="t3 font-medium">Zoho Subscription</dt>
                            <dd class="col-span-2 font-mono text-xs t2">{{ $user->zoho_subscription_id ?: '—' }}</dd>

                            <dt class="t3 font-medium">Starts At</dt>
                            <dd class="col-span-2 font-mono t1">{{ optional($user->membership_starts_at)->format('d M Y') ?: '—' }}</dd>

                            <dt class="t3 font-medium">Ends At / Expiry</dt>
                            <dd class="col-span-2 font-mono font-semibold text-slate-900">{{ optional($user->membership_ends_at)->format('d M Y') ?: 'No Expiry' }}</dd>

                            <dt class="t3 font-medium">Last Payment</dt>
                            <dd class="col-span-2 font-mono t2">{{ optional($user->last_payment_at)->format('d M Y') ?: '—' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Membership Remarks & Auditing
                        </h6>
                        <div class="space-y-3 text-xs">
                            @if($user->membership_expiry_date_remark)
                                <div>
                                    <span class="t3 font-medium block mb-1">Expiry / Status Update Remark:</span>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/60 t1 leading-relaxed">
                                        {{ $user->membership_expiry_date_remark }}
                                    </div>
                                </div>
                            @endif

                            @if($user->coins_remark)
                                <div>
                                    <span class="t3 font-medium block mb-1">Coins Balance Remark:</span>
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/60 t1 leading-relaxed">
                                        {{ $user->coins_remark }}
                                    </div>
                                </div>
                            @endif

                            <dl class="grid grid-cols-3 gap-y-2 text-xs pt-2 mb-0">
                                <dt class="t3 font-medium">Approved At</dt>
                                <dd class="col-span-2 font-mono t2">{{ optional($user->membership_approved_at)->format('d M Y H:i') ?: '—' }}</dd>

                                <dt class="t3 font-medium">Approved By</dt>
                                <dd class="col-span-2 t2">{{ $user->membership_approved_by ?: '—' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 5: INTERESTS & SOCIAL -->
        <div id="tab-pane-social" class="profile-tab-pane hidden space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- Hobbies & Targets -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Hobbies & Target Regions
                        </h6>
                        <div class="space-y-4 text-xs">
                            <div>
                                <span class="t3 font-medium block mb-1.5">Hobbies & Interests:</span>
                                @if(!empty($user->hobbies_interests))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(is_array($user->hobbies_interests) ? $user->hobbies_interests : explode(',', (string)$user->hobbies_interests) as $hobby)
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ trim($hobby) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="t3 text-xs italic">No hobbies listed</span>
                                @endif
                            </div>

                            @if(!empty($user->target_regions))
                                <div>
                                    <span class="t3 font-medium block mb-1.5">Target Expansion Regions:</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(is_array($user->target_regions) ? $user->target_regions : explode(',', (string)$user->target_regions) as $region)
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                🌍 {{ trim($region) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="rounded-xl border bs surface p-5 shadow-xs">
                        <h6 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider mb-4">
                            Social Media & Web Links
                        </h6>
                        <div class="space-y-2.5 text-xs">
                            @php
                                $socialItems = [
                                    ['icon' => 'bi-linkedin', 'color' => 'text-[#0A66C2]', 'label' => 'LinkedIn', 'val' => $user->linkedin_profile],
                                    ['icon' => 'bi-twitter-x', 'color' => 'text-slate-900', 'label' => 'Twitter / X', 'val' => $user->twitter_handle],
                                    ['icon' => 'bi-instagram', 'color' => 'text-[#E4405F]', 'label' => 'Instagram', 'val' => $user->instagram_handle],
                                    ['icon' => 'bi-facebook', 'color' => 'text-[#1877F2]', 'label' => 'Facebook', 'val' => $user->facebook_profile],
                                    ['icon' => 'bi-youtube', 'color' => 'text-[#FF0000]', 'label' => 'YouTube', 'val' => $user->youtube_channel],
                                    ['icon' => 'bi-globe', 'color' => 'text-indigo-600', 'label' => 'Website', 'val' => $user->other_website],
                                ];
                            @endphp

                            @forelse(collect($socialItems)->filter(fn($i) => !empty($i['val'])) as $item)
                                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/60">
                                    <div class="flex items-center gap-2.5 font-medium t1">
                                        <i class="bi {{ $item['icon'] }} {{ $item['color'] }} text-base"></i>
                                        <span>{{ $item['label'] }}</span>
                                    </div>
                                    <a href="{{ str_starts_with($item['val'], 'http') ? $item['val'] : 'https://' . $item['val'] }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:underline no-underline">
                                        <span>Visit</span>
                                        <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                    </a>
                                </div>
                            @empty
                                <div class="text-center py-6 t3 text-xs bg-slate-50 rounded-xl border border-slate-200/60">
                                    <i class="bi bi-share text-2xl d-block mb-1 opacity-50"></i>
                                    No social media links connected.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchProfileTab(tabName, clickedBtn) {
    document.querySelectorAll('.profile-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.profile-tab-pane').forEach(pane => pane.classList.add('hidden'));

    clickedBtn.classList.add('active');
    const targetPane = document.getElementById('tab-pane-' + tabName);
    if (targetPane) {
        targetPane.classList.remove('hidden');
    }
}
</script>
@endsection
