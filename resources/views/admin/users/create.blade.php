@extends('admin.layouts.app')

@section('title', 'Add Peer')

@push('styles')
<style>
    #createPeerTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #createPeerTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #createPeerTabs .nav-link.active {
        background-color: var(--primary);
        color: #ffffff;
        box-shadow: var(--shadow-sm);
    }
    .form-section-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    /* ── Membership & Sponsorship Premium Card ── */
    .sponsor-card {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(240, 244, 255, 0.6) 100%);
        border: 1.5px solid #e0e7ff;
        border-radius: var(--radius-lg, 16px);
        padding: 20px 24px;
        transition: all 0.25s ease-in-out;
        box-shadow: 0 2px 6px rgba(99, 102, 241, 0.04);
    }
    .sponsor-card:hover {
        border-color: #c7d2fe;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.08);
    }
    .sponsor-icon-badge {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);
    }
    .sponsor-toggle-switch {
        width: 3.2rem !important;
        height: 1.7rem !important;
        cursor: pointer;
        background-color: #cbd5e1;
        border-color: #94a3b8;
    }
    .sponsor-toggle-switch:checked {
        background-color: #6366f1 !important;
        border-color: #4f46e5 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
    }
    .sponsor-status-pill {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
    }
    .sponsor-status-active {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }
    .sponsor-status-inactive {
        background-color: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    .sponsor-profile-preview {
        background: #ffffff;
        border: 1.5px solid #c7d2fe !important;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.06);
    }
    .sponsor-avatar {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* ── Select2 Customization for Sponsor Dropdown ── */
    .sponsor-select-custom .select2-selection--single {
        height: 44px !important;
        border-radius: 10px !important;
        border: 1.5px solid #cbd5e1 !important;
        display: flex !important;
        align-items: center !important;
        padding: 0 12px !important;
        background-color: #ffffff !important;
        transition: all 0.2s ease !important;
    }
    .sponsor-select-custom .select2-selection--single:focus,
    .sponsor-select-custom.select2-container--open .select2-selection--single {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18) !important;
    }
    .sponsor-select-custom .select2-selection__rendered {
        font-size: 13.5px !important;
        color: #1e293b !important;
        line-height: 42px !important;
        padding-left: 0 !important;
    }
    .sponsor-select-custom .select2-selection__arrow {
        height: 42px !important;
        right: 10px !important;
    }
    .sponsor-select-custom .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent !important;
        border-width: 6px 5px 0 5px !important;
    }

    /* Dropdown Result Item Styling */
    .sponsor-result-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 4px 2px;
    }
    .sponsor-result-avatar {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #e0e7ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .sponsor-result-name {
        font-weight: 600;
        color: #0f172a;
        font-size: 13px;
    }
    .sponsor-result-sub {
        font-size: 11.5px;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add Peer</h4>
        <p class="text-muted small mb-0">Create a new platform member</p>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger mb-4">
        <strong>There were some problems with your input.</strong>
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $defaultFounder = $defaultFounder ?? null;
    $allUsers = $allUsers ?? collect();
    $types = $types ?? [];
    $statuses = $statuses ?? [];
    $meetingModes = $meetingModes ?? [];
    $meetingFrequencies = $meetingFrequencies ?? [];
    $circleStages = $circleStages ?? [];
    $countries = $countries ?? ['India'];
    $selectedCountry = $selectedCountry ?? 'India';
    $cities = $cities ?? collect();
    $allMainCategories = $allMainCategories ?? collect();
    $mainToSubCategoriesMap = $mainToSubCategoriesMap ?? [];
    $selectedMainCategoryId = $selectedMainCategoryId ?? null;
    $selectedSubCategoryId = $selectedSubCategoryId ?? null;
    $selectedSponsor = $selectedSponsor ?? null;

    $industryTagsValue = old('industry_tags');
    if (is_array($industryTagsValue)) {
        $industryTagsValue = implode(', ', $industryTagsValue);
    }

    $founderId = old('founder_user_id', $defaultFounder?->id);
@endphp

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="createPeerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="personal-tab" data-bs-toggle="pill" data-bs-target="#personal-section" type="button" role="tab" aria-controls="personal-section" aria-selected="true">
                    <i class="bi bi-person me-1"></i>1. Personal Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="business-tab" data-bs-toggle="pill" data-bs-target="#business-section" type="button" role="tab" aria-controls="business-section" aria-selected="false">
                    <i class="bi bi-briefcase me-1"></i>2. Business Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="membership-tab" data-bs-toggle="pill" data-bs-target="#membership-section" type="button" role="tab" aria-controls="membership-section" aria-selected="false">
                    <i class="bi bi-award me-1"></i>3. Membership & Coins
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="circles-tab" data-bs-toggle="pill" data-bs-target="#circles-section" type="button" role="tab" aria-controls="circles-section" aria-selected="false">
                    <i class="bi bi-circle me-1"></i>4. Circles & Location
                </button>
            </li>
        </ul>

        <form id="userCreateForm" action="{{ route('admin.users.store') }}" method="POST" class="p-4" novalidate>
            @csrf

            <div class="tab-content" id="createPeerTabsContent">
                <!-- Tab 1: Personal Profile -->
                <div class="tab-pane fade show active" id="personal-section" role="tabpanel" aria-labelledby="personal-tab">
                    <h5 class="form-section-title"><i class="bi bi-person-badge text-primary me-2"></i>Personal Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Display Name</label>
                            <input type="text" name="display_name" class="form-control" value="{{ old('display_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" value="{{ old('designation') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <input type="text" name="gender" class="form-control" value="{{ old('gender') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Experience Years</label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="100" value="{{ old('experience_years') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Short Bio</label>
                            <textarea name="short_bio" class="form-control" rows="2">{{ old('short_bio') }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Experience Summary</label>
                            <textarea name="experience_summary" class="form-control" rows="2">{{ old('experience_summary') }}</textarea>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="hidden" name="profile_photo_file_id" id="profilePhotoFileId" value="{{ old('profile_photo_file_id') }}">
                            <div id="profilePhotoExisting" class="{{ old('profile_photo_file_id') ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="profilePhotoPreview" src="{{ old('profile_photo_file_id') ? url('/api/v1/files/' . old('profile_photo_file_id')) : '#' }}" alt="Profile preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 80px; object-fit: cover;">
                                    <div>
                                        <a href="{{ old('profile_photo_file_id') ? url('/api/v1/files/' . old('profile_photo_file_id')) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="profilePhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="profilePhotoUpload" class="{{ old('profile_photo_file_id') ? 'd-none' : '' }}">
                                <input type="file" class="form-control" id="profilePhotoFile" accept="image/*">
                                <div class="form-text" id="profilePhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Cover Photo</label>
                            <input type="hidden" name="cover_photo_file_id" id="coverPhotoFileId" value="{{ old('cover_photo_file_id') }}">
                            <div id="coverPhotoExisting" class="{{ old('cover_photo_file_id') ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="coverPhotoPreview" src="{{ old('cover_photo_file_id') ? url('/api/v1/files/' . old('cover_photo_file_id')) : '#' }}" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 140px; object-fit: cover;">
                                    <div>
                                        <a href="{{ old('cover_photo_file_id') ? url('/api/v1/files/' . old('cover_photo_file_id')) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="coverPhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="coverPhotoUpload" class="{{ old('cover_photo_file_id') ? 'd-none' : '' }}">
                                <input type="file" class="form-control" id="coverPhotoFile" accept="image/*">
                                <div class="form-text" id="coverPhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label fw-semibold">Public Profile Slug</label>
                            <input type="text" name="public_profile_slug" class="form-control" value="{{ old('public_profile_slug') }}">
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-label fw-semibold">Social Links</label>
                            <textarea name="social_links" class="form-control" rows="2" placeholder="Enter social links">{{ old('social_links') }}</textarea>
                            <small class="text-muted">
                                Enter comma separated links, optionally as key=value (e.g. linkedin=https://linkedin.com/..., website=https://example.com)
                            </small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <div></div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('business-tab')">
                                Next: Business Details <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Business Details -->
                <div class="tab-pane fade" id="business-section" role="tabpanel" aria-labelledby="business-tab">
                    <h5 class="form-section-title"><i class="bi bi-briefcase text-primary me-2"></i>Business Classification</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Business Type</label>
                            <input type="text" name="business_type" class="form-control" value="{{ old('business_type') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Turnover Range</label>
                            <input type="text" name="turnover_range" class="form-control" value="{{ old('turnover_range') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="business_main_category_id">Main Category</label>
                            <select
                                name="main_business_category_id"
                                id="business_main_category_id"
                                class="form-select js-no-searchable-select js-no-select2"
                            >
                                <option value="">Select main category</option>
                                @foreach ($allMainCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected((string) old('main_business_category_id', $selectedMainCategoryId ?? '') === (string) $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="business_sub_category_id">Sub Category</label>
                            @php
                                $currentMainIdStr = (string) old('main_business_category_id', $selectedMainCategoryId ?? '');
                                $tab2InitialL4 = $mainToSubCategoriesMap[$currentMainIdStr] ?? [];
                            @endphp
                            <select
                                name="business_category_id"
                                id="business_sub_category_id"
                                class="form-select js-no-searchable-select js-no-select2"
                                data-selected-sub-category="{{ old('business_category_id', $selectedSubCategoryId ?? '') }}"
                            >
                                <option value="">Select sub category</option>
                                @foreach ($tab2InitialL4 as $sub)
                                    <option value="{{ $sub['id'] }}" @selected((string) old('business_category_id', $selectedSubCategoryId ?? '') === (string) $sub['id'])>
                                        {{ $sub['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-tags text-primary me-2"></i>Tags & Business Metadata</h5>
                    <div class="row g-3">
                        @php
                            $jsonFields = [
                                'industry_tags' => null,
                                'target_regions' => null,
                                'target_business_categories' => null,
                                'hobbies_interests' => null,
                                'leadership_roles' => null,
                                'special_recognitions' => null,
                                'skills' => null,
                            ];

                            $asCsv = function ($value): string {
                                if (is_array($value)) {
                                    return implode(', ', $value);
                                }
                                return '';
                            };
                        @endphp
                        @foreach ($jsonFields as $field => $value)
                            <div class="col-md-6">
                                <label class="form-label text-capitalize fw-semibold">{{ str_replace('_', ' ', $field) }}</label>
                                <textarea name="{{ $field }}" class="form-control" rows="2" placeholder="Enter comma separated values...">{{ old($field, $asCsv($value)) }}</textarea>
                            </div>
                        @endforeach
                    </div>



                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('personal-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('membership-tab')">
                                Next: Membership & Coins <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Membership & Coins -->
                <div class="tab-pane fade" id="membership-section" role="tabpanel" aria-labelledby="membership-tab">
                    <h5 class="form-section-title"><i class="bi bi-award-fill text-primary me-2"></i>Membership Settings</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Status</label>
                            @php
                                $membershipStatusLabels = [
                                    'free_trial_peer' => 'Free Trial Peer',
                                    'free_peer' => 'Free Peer',
                                    'only_unity_peer' => 'Global Peer',
                                    'Only Unity Peer' => 'Global Peer',
                                    'Circle Peer' => 'Circle Peer',
                                    'Multi Circle Peer' => 'Multi Circle Peer',
                                    'Charter Peer' => 'Charter Peer',
                                    'Industry Advisor' => 'Industry Advisor',
                                    'Charter Investor' => 'Charter Investor',
                                    'Circle Founder' => 'Circle Founder',
                                    'Circle Director' => 'Circle Director',
                                    'Board Advisor' => 'Board Advisor',
                                ];
                            @endphp
                            <select name="membership_status" class="form-select js-no-searchable-select" required>
                                @foreach ($membershipStatuses as $status)
                                    <option value="{{ $status }}" @selected(old('membership_status') === $status)>
                                        {{ $membershipStatusLabels[$status] ?? $status }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Plan</label>
                            <select name="zoho_plan_code" class="form-select @error('zoho_plan_code') is-invalid @enderror js-no-searchable-select">
                                <option value="">Select Membership Plan</option>
                                @foreach ($membershipPlanOptions as $plan)
                                    <option value="{{ $plan['code'] }}" @selected(old('zoho_plan_code') === $plan['code'])>{{ $plan['label'] }}</option>
                                @endforeach
                            </select>
                            @error('zoho_plan_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Start Date</label>
                            <input type="date" name="membership_starts_at" class="form-control" value="{{ old('membership_starts_at') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Expiry Date</label>
                            <input type="date" name="membership_ends_at" class="form-control" value="{{ old('membership_ends_at') }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="isSponsoredMember" name="is_sponsored_member" @checked(old('is_sponsored_member'))>
                                <label class="form-check-label fw-semibold" for="isSponsoredMember">
                                    Is Sponsored Member
                                </label>
                            </div>
                        </div>

                        <div class="col-12 {{ old('is_sponsored_member') ? '' : 'd-none' }}" id="sponsoredMemberContainer">
                            <div class="p-3.5 bg-light border border-indigo-100 rounded-3 shadow-xs">
                                <div class="row g-3 align-items-center">
                                    <div class="col-lg-6 col-md-12">
                                        <label class="form-label fw-semibold text-dark mb-1.5" for="sponsor_select">
                                            <i class="bi bi-search text-primary me-1"></i>Search & Select Sponsor (Introduced By Member)
                                        </label>
                                        <select name="introduced_by" id="sponsor_select" class="form-select">
                                            @if ($selectedSponsor)
                                                <option value="{{ $selectedSponsor->id }}" selected>
                                                    {{ $selectedSponsor->adminDisplayName() }} ({{ $selectedSponsor->email }})
                                                </option>
                                            @elseif (old('introduced_by'))
                                                <option value="{{ old('introduced_by') }}" selected>{{ old('introduced_by') }}</option>
                                            @else
                                                <option value="">Search by name, email, company, or phone...</option>
                                            @endif
                                        </select>
                                        <div class="form-text text-muted mt-1.5">
                                            <i class="bi bi-info-circle me-1"></i>Search and choose the platform member who sponsored / introduced this peer.
                                        </div>
                                    </div>

                                    <div class="col-lg-6 col-md-12" id="sponsorInfoBox" style="{{ ($selectedSponsor || old('introduced_by')) ? '' : 'display: none;' }}">
                                        <label class="form-label fw-semibold text-dark mb-1.5">
                                            <i class="bi bi-patch-check-fill text-success me-1"></i>Selected Sponsor Details
                                        </label>
                                        <div class="sponsor-profile-preview p-3 rounded-3 border bg-white shadow-xs position-relative">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="sponsor-avatar flex-shrink-0" id="sponsorAvatarInitial">
                                                    {{ strtoupper(substr($selectedSponsor?->first_name ?: ($selectedSponsor?->display_name ?: 'S'), 0, 1)) }}
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="fw-bold text-dark text-truncate fs-6" id="sponsorSummaryName">
                                                            {{ $selectedSponsor?->adminDisplayName() ?? '' }}
                                                        </span>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-0.5 px-2 text-[10px]">
                                                            <i class="bi bi-patch-check-fill me-0.5"></i> Sponsor
                                                        </span>
                                                    </div>
                                                    <div class="text-muted small text-truncate mt-0.5" id="sponsorSummaryMeta">
                                                        {{ $selectedSponsor?->company_name ?? '' }} {{ $selectedSponsor?->city ? '• '.$selectedSponsor->city : '' }}
                                                    </div>
                                                    <div class="text-secondary small text-truncate" id="sponsorSummaryEmail">
                                                        {{ $selectedSponsor?->email ?? '' }}
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-danger btn-sm border rounded-circle p-1.5 flex-shrink-0" id="clearSponsorBtn" title="Remove sponsor">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-coin text-primary me-2"></i>Coins Balance</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Coins Balance</label>
                            <input type="number" name="coins_balance" class="form-control" min="0" value="{{ old('coins_balance', 0) }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('business-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Save
                            </button>
                            <button type="button" class="btn btn-primary" onclick="switchTab('circles-tab')">
                                Next: Circles & Location <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Circles & Location -->
                <div class="tab-pane fade" id="circles-section" role="tabpanel" aria-labelledby="circles-tab">
                    <h5 class="form-section-title"><i class="bi bi-people-fill text-primary me-2"></i>Circle Management</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 text-muted small">
                            Manual admin override only. Does not affect payment history. Expired membership will be treated as Free Peer.
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="active_circle_id">Select Circle Membership</label>
                            <select name="active_circle_id" id="active_circle_id" class="form-select @error('active_circle_id') is-invalid @enderror">
                                <option value="">-- No Circle --</option>
                                @foreach ($circles as $circle)
                                    <option
                                        value="{{ $circle->id }}"
                                        @selected(old('active_circle_id', old('circle_id')) === $circle->id)
                                    >{{ $circle->name }}</option>
                                @endforeach
                            </select>
                            @error('active_circle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Joined Date</label>
                            <input type="date" name="circle_joined_at" class="form-control" value="{{ old('circle_joined_at') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Expiry Date</label>
                            <input type="date" name="circle_expires_at" class="form-control" value="{{ old('circle_expires_at') }}">
                        </div>
            </div>
                    <h5 class="form-section-title"><i class="bi bi-geo text-primary me-2"></i>Physical Location</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" value="{{ old('city') }}" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('membership-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-danger">Cancel</a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-circle me-1"></i>Save Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function switchTab(tabId) {
        const tabEl = document.getElementById(tabId);
        if (tabEl) {
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const uploadUrl = '{{ route('admin.files.upload') }}';

        const setupUploader = (prefix) => {
            const fileInput = document.getElementById(`${prefix}File`);
            const hiddenInput = document.getElementById(`${prefix}FileId`);
            const existing = document.getElementById(`${prefix}Existing`);
            const upload = document.getElementById(`${prefix}Upload`);
            const status = document.getElementById(`${prefix}Status`);
            const changeBtn = existing?.querySelector('[data-change-target]');
            const viewLink = existing?.querySelector('a');
            const previewImg = document.getElementById(`${prefix}Preview`);

            const setStatus = (text, isError = false) => {
                if (!status) return;
                status.textContent = text;
                status.classList.toggle('text-danger', isError);
                status.classList.toggle('text-success', !isError && text.includes('successful'));
            };

            changeBtn?.addEventListener('click', () => {
                if (upload) upload.classList.remove('d-none');
                if (existing) existing.classList.add('d-none');
                if (fileInput) {
                    fileInput.value = '';
                    fileInput.click();
                }
                setStatus('Select a new image file.');
            });

            fileInput?.addEventListener('change', async () => {
                const file = fileInput.files?.[0];
                if (!file) return;

                // Immediate local preview so the user sees their chosen photo right away
                try {
                    const localUrl = URL.createObjectURL(file);
                    if (previewImg) {
                        previewImg.src = localUrl;
                    }
                    if (viewLink) {
                        viewLink.href = localUrl;
                    }
                    if (existing) existing.classList.remove('d-none');
                    if (upload) upload.classList.add('d-none');
                } catch (err) {}

                setStatus('Uploading image...', false);

                const formData = new FormData();
                formData.append('file', file);

                try {
                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        },
                    });

                    const json = await response.json();

                    if (!response.ok || json.success === false) {
                        const errMsg = json?.message || 'Upload failed. Please try again.';
                        setStatus(errMsg, true);
                        if (existing) existing.classList.add('d-none');
                        if (upload) upload.classList.remove('d-none');
                        return;
                    }

                    const fileId = json?.data?.id ?? json?.data?.[0]?.id;
                    if (!fileId) {
                        const errMsg = json?.message || 'Upload failed. Missing file ID.';
                        setStatus(errMsg, true);
                        if (existing) existing.classList.add('d-none');
                        if (upload) upload.classList.remove('d-none');
                        return;
                    }

                    if (hiddenInput) hiddenInput.value = fileId;
                    if (previewImg) previewImg.src = `/api/v1/files/${fileId}`;
                    if (viewLink) viewLink.href = `/api/v1/files/${fileId}`;

                    if (existing) existing.classList.remove('d-none');
                    if (upload) upload.classList.add('d-none');
                    setStatus('Upload successful.');
                } catch (e) {
                    setStatus('Upload failed. Please try again.', true);
                    if (existing) existing.classList.add('d-none');
                    if (upload) upload.classList.remove('d-none');
                }
            });
        };

        setupUploader('profilePhoto');
        setupUploader('coverPhoto');

        const mainToSubCategoriesMap = @json($mainToSubCategoriesMap ?? []);

        const fillSelect = (selectEl, options, placeholder, selectedValue = '') => {
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            (options || []).forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.name;
                if (selectedValue !== '' && String(selectedValue) === String(item.id)) {
                    option.selected = true;
                }
                selectEl.appendChild(option);
            });
            selectEl.disabled = (options || []).length === 0;

            if (window.jQuery && window.jQuery(selectEl).data('select2')) {
                window.jQuery(selectEl).trigger('change.select2');
            }
        };

        // Tab 2 Business Classification Categories
        const businessMainCatSelect = document.getElementById('business_main_category_id');
        const businessSubCatSelect = document.getElementById('business_sub_category_id');

        const updateBusinessSubCategories = (presetSub = '') => {
            if (!businessMainCatSelect || !businessSubCatSelect) return;
            const selectedMainId = businessMainCatSelect.value || '';
            if (!selectedMainId) {
                fillSelect(businessSubCatSelect, [], 'Select sub category', presetSub);
                return;
            }
            const l4Options = mainToSubCategoriesMap[String(selectedMainId)] || [];
            fillSelect(businessSubCatSelect, l4Options, 'Select sub category', presetSub);
        };

        const onBusinessMainChange = () => {
            updateBusinessSubCategories();
        };

        businessMainCatSelect?.addEventListener('change', onBusinessMainChange);
        if (window.jQuery) {
            window.jQuery(businessMainCatSelect).on('change select2:select select2:clear', onBusinessMainChange);
        }

        const initialBusinessSub = businessSubCatSelect?.dataset.selectedSubCategory || '';
        if (businessMainCatSelect && businessMainCatSelect.value && (!businessSubCatSelect || businessSubCatSelect.options.length <= 1)) {
            updateBusinessSubCategories(initialBusinessSub);
        }

        // Tab 3 Sponsored Member dynamic fields
        const isSponsoredCheckbox = document.getElementById('isSponsoredMember');
        const sponsoredContainer = document.getElementById('sponsoredMemberContainer');
        const sponsorInfoBox = document.getElementById('sponsorInfoBox');
        const sponsorSummaryName = document.getElementById('sponsorSummaryName');
        const sponsorSummaryMeta = document.getElementById('sponsorSummaryMeta');
        const sponsorSummaryEmail = document.getElementById('sponsorSummaryEmail');
        const sponsorAvatarInitial = document.getElementById('sponsorAvatarInitial');
        const sponsorStatusBadge = document.getElementById('sponsorStatusBadge');

        const toggleSponsoredFields = () => {
            if (!isSponsoredCheckbox || !sponsoredContainer) return;
            if (isSponsoredCheckbox.checked) {
                sponsoredContainer.classList.remove('d-none');
                if (sponsorStatusBadge) {
                    sponsorStatusBadge.textContent = 'Sponsored';
                    sponsorStatusBadge.className = 'badge rounded-pill sponsor-status-pill sponsor-status-active';
                }
            } else {
                sponsoredContainer.classList.add('d-none');
                if (sponsorStatusBadge) {
                    sponsorStatusBadge.textContent = 'Not Sponsored';
                    sponsorStatusBadge.className = 'badge rounded-pill sponsor-status-pill sponsor-status-inactive';
                }
                if (window.jQuery && jQuery('#sponsor_select').length) {
                    jQuery('#sponsor_select').val(null).trigger('change');
                }
                if (sponsorInfoBox) sponsorInfoBox.style.display = 'none';
            }
        };

        isSponsoredCheckbox?.addEventListener('change', toggleSponsoredFields);

        function formatSponsorOption(item) {
            if (item.loading) return item.text;
            if (!item.id) return item.text;
            const name = item.name || item.text || '';
            const initial = (name.trim().charAt(0) || 'S').toUpperCase();
            const sub = item.subtext || '';
            return $(`
                <div class="sponsor-result-item">
                    <div class="sponsor-result-avatar">${initial}</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="sponsor-result-name">${name}</div>
                        ${sub ? `<div class="sponsor-result-sub">${sub}</div>` : ''}
                    </div>
                </div>
            `);
        }

        function formatSponsorSelection(item) {
            return item.name || item.text || 'Search by name, email, company, or phone...';
        }

        if (window.jQuery && window.jQuery.fn.select2 && jQuery('#sponsor_select').length) {
            jQuery('#sponsor_select').select2({
                placeholder: 'Search by name, email, company, or phone...',
                allowClear: true,
                minimumInputLength: 0,
                width: '100%',
                containerCssClass: 'sponsor-select-custom',
                dropdownCssClass: 'admin-filter-dropdown-menu sponsor-dropdown-custom',
                templateResult: formatSponsorOption,
                templateSelection: formatSponsorSelection,
                ajax: {
                    url: "{{ route('admin.users.search') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function (data) {
                        const members = (data || []).filter(function (item) {
                            return !item.type || item.type === 'member';
                        });
                        return {
                            results: members.map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.label_inline || item.label || item.name,
                                    name: item.name,
                                    subtext: item.subtext || (item.company ? item.company + (item.city ? ' • ' + item.city : '') : ''),
                                    email: item.email || '',
                                    company: item.company || '',
                                    city: item.city || ''
                                };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1
            }).on('select2:select', function (e) {
                const data = e.params.data;
                const name = data.name || data.text || '';
                const initial = (name.trim().charAt(0) || 'S').toUpperCase();
                if (sponsorSummaryName) sponsorSummaryName.textContent = name;
                if (sponsorSummaryMeta) sponsorSummaryMeta.textContent = data.subtext || '';
                if (sponsorSummaryEmail) sponsorSummaryEmail.textContent = data.email || '';
                if (sponsorAvatarInitial) sponsorAvatarInitial.textContent = initial;
                if (sponsorInfoBox) sponsorInfoBox.style.display = 'block';
            }).on('select2:clear', function () {
                if (sponsorInfoBox) sponsorInfoBox.style.display = 'none';
            });
        }

        document.getElementById('clearSponsorBtn')?.addEventListener('click', function() {
            if (window.jQuery && jQuery('#sponsor_select').length) {
                jQuery('#sponsor_select').val(null).trigger('change');
            }
            if (sponsorInfoBox) sponsorInfoBox.style.display = 'none';
        });
    });
</script>
@endpush
