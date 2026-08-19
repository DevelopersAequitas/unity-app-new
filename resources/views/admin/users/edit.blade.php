@extends('admin.layouts.app')

@section('title', ($isReadOnly ?? false) ? 'View Profile' : 'Edit Peer')

@push('styles')
<style>
    #editPeerTabs .nav-link {
        color: var(--text-secondary);
        border-radius: var(--radius-md);
        transition: all var(--duration-fast) var(--ease-smooth);
        border: 1px solid transparent;
    }
    #editPeerTabs .nav-link:hover {
        background-color: var(--border-light);
    }
    #editPeerTabs .nav-link.active {
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
        <h4 class="mb-1 text-dark fw-bold">
            <i class="bi bi-person-fill text-primary me-2"></i>{{ ($isReadOnly ?? false) ? 'View Profile' : 'Edit Peer' }}
        </h4>
        <small class="text-muted">ID: {{ $user->id }}</small>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
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

@if ($hasAssignedAdminRole)
    <form id="removeAdminRoleForm" method="POST" action="{{ route('admin.users.roles.remove', $user->id) }}">
        @csrf
    </form>
@endif

<script>
window.switchTab = function(tabId) {
    const tabEl = typeof tabId === 'string' ? document.getElementById(tabId) : tabId;
    if (!tabEl) return;
    
    const allTabs = document.querySelectorAll('#editPeerTabs .nav-link');
    allTabs.forEach(function(t) {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
    });
    
    const allPanes = document.querySelectorAll('#editPeerTabsContent > .tab-pane');
    allPanes.forEach(function(p) {
        p.classList.remove('show', 'active');
        p.style.setProperty('display', 'none', 'important');
    });
    
    tabEl.classList.add('active');
    tabEl.setAttribute('aria-selected', 'true');
    
    const targetSelector = tabEl.getAttribute('data-bs-target');
    if (targetSelector) {
        const targetPane = document.querySelector(targetSelector);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
            targetPane.style.setProperty('display', 'block', 'important');
        }
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        try {
            const bsTab = bootstrap.Tab.getOrCreateInstance(tabEl);
            bsTab.show();
        } catch (e) {}
    }
};
</script>

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="editPeerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-2 px-3 fw-semibold" id="personal-tab" onclick="switchTab('personal-tab')" data-bs-toggle="pill" data-bs-target="#personal-section" type="button" role="tab" aria-controls="personal-section" aria-selected="true">
                    <i class="bi bi-person me-1"></i>1. Personal Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="business-tab" onclick="switchTab('business-tab')" data-bs-toggle="pill" data-bs-target="#business-section" type="button" role="tab" aria-controls="business-section" aria-selected="false">
                    <i class="bi bi-briefcase me-1"></i>2. Business Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="membership-tab" onclick="switchTab('membership-tab')" data-bs-toggle="pill" data-bs-target="#membership-section" type="button" role="tab" aria-controls="membership-section" aria-selected="false">
                    <i class="bi bi-award me-1"></i>3. Membership & Coins
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="circles-tab" onclick="switchTab('circles-tab')" data-bs-toggle="pill" data-bs-target="#circles-section" type="button" role="tab" aria-controls="circles-section" aria-selected="false">
                    <i class="bi bi-circle me-1"></i>4. Circles & Admin
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="stories-tab" onclick="switchTab('stories-tab')" data-bs-toggle="pill" data-bs-target="#stories-section" type="button" role="tab" aria-controls="stories-section" aria-selected="false">
                    <i class="bi bi-journal-text me-1"></i>5. Story Submissions ({{ $storySubmissionsCount }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="introduced-tab" onclick="switchTab('introduced-tab')" data-bs-toggle="pill" data-bs-target="#introduced-section" type="button" role="tab" aria-controls="introduced-section" aria-selected="false">
                    <i class="bi bi-people me-1"></i>6. Introduced Members ({{ $introducedPeersCount }})
                </button>
            </li>
        </ul>

        <form id="userEditForm" action="{{ route('admin.users.update', $user->id) }}" method="POST" class="p-4" novalidate>
            @csrf
            @method('PUT')

            <div class="tab-content" id="editPeerTabsContent">
                <!-- Tab 1: Personal Profile -->
                <div class="tab-pane fade show active" id="personal-section" role="tabpanel" aria-labelledby="personal-tab">
                    <h5 class="form-section-title"><i class="bi bi-person-badge text-primary me-2"></i>Personal Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Display Name</label>
                            <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $user->display_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" value="{{ old('designation', $user->designation) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <input type="text" name="gender" class="form-control" value="{{ old('gender', $user->gender) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="{{ old('dob', optional($user->dob)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Experience Years</label>
                            <input type="number" name="experience_years" class="form-control" min="0" max="100" value="{{ old('experience_years', $user->experience_years) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Short Bio</label>
                            <textarea name="short_bio" class="form-control" rows="2">{{ old('short_bio', $user->short_bio) }}</textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Experience Summary</label>
                            <textarea name="experience_summary" class="form-control" rows="2">{{ old('experience_summary', $user->experience_summary) }}</textarea>
                        </div>
                        
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="hidden" name="profile_photo_file_id" id="profilePhotoFileId" value="{{ old('profile_photo_file_id', $user->profile_photo_file_id) }}">
                            <div id="profilePhotoExisting" class="{{ ($user->profile_photo_file_id || old('profile_photo_file_id')) ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="profilePhotoPreview" src="{{ ($user->profile_photo_file_id || old('profile_photo_file_id')) ? url('/api/v1/files/' . old('profile_photo_file_id', $user->profile_photo_file_id)) : '#' }}" alt="Profile preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 80px; object-fit: cover;">
                                    <div>
                                        <a href="{{ ($user->profile_photo_file_id || old('profile_photo_file_id')) ? url('/api/v1/files/' . old('profile_photo_file_id', $user->profile_photo_file_id)) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="profilePhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="profilePhotoUpload" class="{{ ($user->profile_photo_file_id || old('profile_photo_file_id')) ? 'd-none' : '' }}">
                                <input type="file" class="form-control" id="profilePhotoFile" accept="image/*">
                                <div class="form-text" id="profilePhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Cover Photo</label>
                            <input type="hidden" name="cover_photo_file_id" id="coverPhotoFileId" value="{{ old('cover_photo_file_id', $user->cover_photo_file_id) }}">
                            <div id="coverPhotoExisting" class="{{ ($user->cover_photo_file_id || old('cover_photo_file_id')) ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="coverPhotoPreview" src="{{ ($user->cover_photo_file_id || old('cover_photo_file_id')) ? url('/api/v1/files/' . old('cover_photo_file_id', $user->cover_photo_file_id)) : '#' }}" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 140px; object-fit: cover;">
                                    <div>
                                        <a href="{{ ($user->cover_photo_file_id || old('cover_photo_file_id')) ? url('/api/v1/files/' . old('cover_photo_file_id', $user->cover_photo_file_id)) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="coverPhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="coverPhotoUpload" class="{{ ($user->cover_photo_file_id || old('cover_photo_file_id')) ? 'd-none' : '' }}">
                                <input type="file" class="form-control" id="coverPhotoFile" accept="image/*">
                                <div class="form-text" id="coverPhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label fw-semibold">Public Profile Slug</label>
                            <input type="text" name="public_profile_slug" class="form-control" value="{{ old('public_profile_slug', $user->public_profile_slug) }}">
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $user->city) }}" required>
                        </div>

                        <div class="col-12 mt-3">
                            @php
                                $socialLinksValue = '';
                                if (is_array($user->social_links) && $user->social_links !== []) {
                                    if (array_keys($user->social_links) !== range(0, count($user->social_links) - 1)) {
                                        $pairs = [];
                                        foreach ($user->social_links as $k => $v) {
                                            $pairs[] = $k . '=' . $v;
                                        }
                                        $socialLinksValue = implode(', ', $pairs);
                                    } else {
                                        $socialLinksValue = implode(', ', $user->social_links);
                                    }
                                }
                            @endphp
                            <label class="form-label fw-semibold">Social Links</label>
                            <textarea name="social_links" class="form-control" rows="2" placeholder="Enter social links">{{ old('social_links', $socialLinksValue) }}</textarea>
                            <small class="text-muted">
                                Enter comma separated links, optionally as key=value (e.g. linkedin=https://linkedin.com/..., website=https://example.com)
                            </small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
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
                    @php
                        $displayMainCategory = $registeredMainCategoryName
                            ?? ($user->mainBusinessCategory?->name ?: ($user->businessCategory?->name ?: null));
                        $displaySubCategory = $registeredSubCategoryName
                            ?? ($user->level4Category?->name ?: ($user->business_sub_category ?: null));
                    @endphp
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $user->company_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Business Type</label>
                            <input type="text" name="business_type" class="form-control" value="{{ old('business_type', $user->business_type) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Turnover Range</label>
                            <input type="text" name="turnover_range" class="form-control" value="{{ old('turnover_range', $user->turnover_range) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="business_main_category_id">Main Category</label>
                            <select
                                name="main_business_category_id"
                                id="business_main_category_id"
                                class="form-select js-no-searchable-select js-no-select2"
                            >
                                <option value="">{{ $selectedMainCategoryId ? 'Select main category' : 'Null' }}</option>
                                @foreach ($allMainCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected((string) old('main_business_category_id', $selectedMainCategoryId) === (string) $cat->id)>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="business_sub_category_id">Sub Category</label>
                            @php
                                $currentMainIdStr = (string) old('main_business_category_id', $selectedMainCategoryId);
                                $tab2InitialL4 = $mainToSubCategoriesMap[$currentMainIdStr] ?? [];
                            @endphp
                            <select
                                name="business_category_id"
                                id="business_sub_category_id"
                                class="form-select js-no-searchable-select js-no-select2"
                                data-selected-sub-category="{{ old('business_category_id', $selectedSubCategoryId) }}"
                            >
                                <option value="">{{ $selectedSubCategoryId ? 'Select sub category' : 'Null' }}</option>
                                @foreach ($tab2InitialL4 as $sub)
                                    <option value="{{ $sub['id'] }}" @selected((string) old('business_category_id', $selectedSubCategoryId) === (string) $sub['id'])>
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
                                'industry_tags' => $user->industry_tags,
                                'target_regions' => $user->target_regions,
                                'target_business_categories' => $user->target_business_categories,
                                'hobbies_interests' => $user->hobbies_interests,
                                'leadership_roles' => $user->leadership_roles,
                                'special_recognitions' => $user->special_recognitions,
                                'skills' => $user->skills,
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
                                    <option value="{{ $status }}" @selected(old('membership_status', $user->membership_status) === $status)>
                                        {{ $membershipStatusLabels[$status] ?? $status }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select js-no-searchable-select" required>
                                @php
                                    $statusValue = old('status', $user->status ?? 'active');
                                @endphp
                                <option value="active" @selected($statusValue === 'active')>Active</option>
                                <option value="inactive" @selected($statusValue === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Plan</label>
                            <select name="zoho_plan_code" class="form-select @error('zoho_plan_code') is-invalid @enderror">
                                <option value="">Select Membership Plan</option>
                                @foreach ($membershipPlanOptions as $plan)
                                    <option value="{{ $plan['code'] }}" @selected(old('zoho_plan_code', $user->zoho_plan_code) === $plan['code'])>{{ $plan['label'] }}</option>
                                @endforeach
                            </select>
                            @error('zoho_plan_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Start Date</label>
                            <input type="date" name="membership_starts_at" class="form-control" value="{{ old('membership_starts_at', optional($user->membership_starts_at)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Membership Expiry Date</label>
                            <input type="date" name="membership_ends_at" class="form-control" value="{{ old('membership_ends_at', optional($user->membership_ends_at)->format('Y-m-d')) }}">
                        </div>
                        @if(old('membership_status', $user->membership_status) === 'free_trial_peer')
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Trial Expiry Date</label>
                                <input type="text" class="form-control bg-light" value="{{ old('membership_ends_at', optional($user->membership_ends_at)->format('Y-m-d')) }}" readonly>
                            </div>
                        @endif
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Membership Expiry Date Remark</label>
                            <input type="text" name="membership_expiry_date_remark" class="form-control @error('membership_expiry_date_remark') is-invalid @enderror" placeholder="Write remark explaining why membership status or expiry date was updated" value="{{ old('membership_expiry_date_remark', $user->membership_expiry_date_remark) }}">
                            @error('membership_expiry_date_remark')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="isSponsoredMember" name="is_sponsored_member" @checked(old('is_sponsored_member', $user->is_sponsored_member))>
                                <label class="form-check-label fw-semibold" for="isSponsoredMember">
                                    Is Sponsored Member
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Members Introduced Count</label>
                            <input type="number" name="members_introduced_count" class="form-control" min="0" value="{{ old('members_introduced_count', $user->members_introduced_count) }}">
                        </div>

                        @php
                            $effectiveSponsor = $selectedSponsor ?? $user->introducedBy ?? (old('introduced_by') ? \App\Models\User::find(old('introduced_by')) : null);
                        @endphp

                        <div class="col-12 {{ old('is_sponsored_member', $user->is_sponsored_member) ? '' : 'd-none' }}" id="sponsoredMemberContainer">
                            <div class="p-3.5 bg-light border border-indigo-100 rounded-3 shadow-xs">
                                <div class="row g-3 align-items-center">
                                    <div class="col-lg-6 col-md-12">
                                        <label class="form-label fw-semibold text-dark mb-1.5" for="sponsor_select">
                                            <i class="bi bi-search text-primary me-1"></i>Search & Select Sponsor (Introduced By Member)
                                        </label>
                                        <select name="introduced_by" id="sponsor_select" class="form-select">
                                            @if ($effectiveSponsor)
                                                <option value="{{ $effectiveSponsor->id }}" selected>
                                                    {{ $effectiveSponsor->adminDisplayName() }} ({{ $effectiveSponsor->email }})
                                                </option>
                                            @elseif (old('introduced_by', $user->introduced_by))
                                                <option value="{{ old('introduced_by', $user->introduced_by) }}" selected>{{ old('introduced_by', $user->introduced_by) }}</option>
                                            @else
                                                <option value="">Search by name, email, company, or phone...</option>
                                            @endif
                                        </select>
                                        <div class="form-text text-muted mt-1.5">
                                            <i class="bi bi-info-circle me-1"></i>Search and choose the platform member who sponsored / introduced this peer.
                                        </div>
                                    </div>

                                    <div class="col-lg-6 col-md-12" id="sponsorInfoBox" style="{{ ($effectiveSponsor || old('introduced_by', $user->introduced_by)) ? '' : 'display: none;' }}">
                                        <label class="form-label fw-semibold text-dark mb-1.5">
                                            <i class="bi bi-patch-check-fill text-success me-1"></i>Selected Sponsor Details
                                        </label>
                                        <div class="sponsor-profile-preview p-3 rounded-3 border bg-white shadow-xs position-relative">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="sponsor-avatar flex-shrink-0" id="sponsorAvatarInitial">
                                                    {{ strtoupper(substr($effectiveSponsor?->first_name ?: ($effectiveSponsor?->display_name ?: 'S'), 0, 1)) }}
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="fw-bold text-dark text-truncate fs-6" id="sponsorSummaryName">
                                                            {{ $effectiveSponsor?->adminDisplayName() ?? '' }}
                                                        </span>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill py-0.5 px-2 text-[10px]">
                                                            <i class="bi bi-patch-check-fill me-0.5"></i> Sponsor
                                                        </span>
                                                    </div>
                                                    <div class="text-muted small text-truncate mt-0.5" id="sponsorSummaryMeta">
                                                        {{ $effectiveSponsor?->company_name ?? '' }} {{ $effectiveSponsor?->city ? '• '.$effectiveSponsor->city : '' }}
                                                    </div>
                                                    <div class="text-secondary small text-truncate" id="sponsorSummaryEmail">
                                                        {{ $effectiveSponsor?->email ?? '' }}
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
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Coins Balance</label>
                            <input type="number" name="coins_balance" class="form-control" min="0" value="{{ old('coins_balance', $user->coins_balance) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Coins Remark</label>
                            <input
                                type="text"
                                name="coins_remark"
                                class="form-control @error('coins_remark') is-invalid @enderror"
                                maxlength="1000"
                                value="{{ old('coins_remark', !empty($hasCoinsRemarkColumn) ? $user->coins_remark : '') }}"
                                placeholder="Required when coins balance is changed"
                            >
                            @error('coins_remark')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Influencer Stars</label>
                            <input type="number" name="influencer_stars" class="form-control" min="0" value="{{ old('influencer_stars', $user->influencer_stars) }}">
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-heart-pulse text-primary me-2"></i>Life Impact</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Life Impacted</label>
                            <input
                                type="number"
                                name="life_impacted_count"
                                class="form-control @error('life_impacted_count') is-invalid @enderror"
                                min="0"
                                value="{{ old('life_impacted_count', $user->life_impacted_count ?? 0) }}"
                            >
                            @error('life_impacted_count')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Life Impact Remark</label>
                            <input
                                type="text"
                                name="life_impact_remark"
                                class="form-control @error('life_impact_remark') is-invalid @enderror"
                                maxlength="1000"
                                value="{{ old('life_impact_remark') }}"
                                placeholder="Required when total life impacted is changed"
                            >
                            @error('life_impact_remark')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Welcome Email Card -->
                    <div class="mt-4">
                        @include('admin.users.partials.membership_welcome_email_card', [
                            'showSendButton' => true,
                            'cardClass' => 'border border-light shadow-none bg-light-subtle',
                        ])
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
                                Next: Circles & Admin <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Circles & Admin -->
                <div class="tab-pane fade" id="circles-section" role="tabpanel" aria-labelledby="circles-tab">
                    <h5 class="form-section-title"><i class="bi bi-people-fill text-primary me-2"></i>Circle Management</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12 text-muted small">
                            Manual admin override only. Does not affect payment history. Expired membership will be treated as Free Peer.
                        </div>


                        <div class="col-12"><h6 class="mb-0 mt-3 text-dark fw-bold">Add Another Circle Membership</h6></div>
                        @php
                            $selectedCircleValue = (string) old('active_circle_id', $user->active_circle_id ?? $effectiveCircleId ?? '');
                        @endphp
                        <input type="hidden" name="active_circle_id" value="{{ $selectedCircleValue }}">
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="additional_circle_id">Circle</label>
                            <select name="additional_circle_id" id="additional_circle_id" class="form-select @error('additional_circle_id') is-invalid @enderror">
                                <option value="">-- Optional --</option>
                                @foreach ($circles as $circle)
                                    <option value="{{ $circle->id }}" @selected((string) old('additional_circle_id') === (string) $circle->id)>
                                        {{ $circle->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('additional_circle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="level1_category_name">Main Category</label>
                            <input type="text" id="level1_category_name" class="form-control bg-light" readonly placeholder="Auto-filled from circle" value="">
                            <input type="hidden" name="level1_category_id" id="level1_category_id" value="{{ old('level1_category_id', old('level_1_category_id', '')) }}">
                        </div>
                        <input type="hidden" name="level2_category_id" id="level2_category_id" value="{{ old('level2_category_id', old('level_2_category_id', '')) }}">
                        <input type="hidden" name="level3_category_id" id="level3_category_id" value="{{ old('level3_category_id', old('level_3_category_id', '')) }}">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="level4_category_id">Sub Category</label>
                            <select name="level4_category_id" id="level4_category_id" class="form-select js-no-searchable-select" disabled>
                                <option value="">Select sub category</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Joined Date</label>
                            <input type="date" name="circle_joined_at" class="form-control" value="{{ old('circle_joined_at', optional($user->circle_joined_at)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Circle Expiry Date</label>
                            <input type="date" name="circle_expires_at" class="form-control" value="{{ old('circle_expires_at', optional($user->circle_expires_at)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="add_circle_membership" value="1" class="btn btn-outline-primary w-100">
                                <i class="bi bi-plus-circle me-1"></i>Add Circle
                            </button>
                        </div>

                        @if (! $isJoinedToEffectiveCircle)
                            <div class="col-12 mt-2">
                                <div class="alert alert-warning mb-0 small">
                                    Peer is not joined to the selected circle. Select a circle and click <strong>Save</strong> to join.
                                </div>
                            </div>
                        @endif

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold mb-2 text-dark">Joined Circle Memberships (Multi-circle)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-premium align-middle mb-0 text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>Circle</th>
                                            <th>Addon Code</th>
                                            <th>Addon Name</th>
                                            <th>Joined At</th>
                                            <th>Expires At</th>
                                            <th>Member Status</th>
                                            <th>Payment Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($circleMemberships as $membership)
                                            @php
                                                $latestSubscription = $latestCircleSubscriptions->get((string) $membership->circle_id);
                                                $membershipExpiresAt = $membership->expires_at;
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($membership->circle?->id)
                                                        <a href="{{ route('admin.circles.show', $membership->circle->id) }}">{{ $membership->circle?->name ?: '—' }}</a>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>{{ $membership->zoho_addon_code ?: ($latestSubscription->zoho_addon_code ?? '—') }}</td>
                                                <td>{{ $latestSubscription->zoho_addon_name ?? '—' }}</td>
                                                <td>{{ optional($membership->joined_at)->format('Y-m-d') ?: '—' }}</td>
                                                <td>{{ $membershipExpiresAt ? \Illuminate\Support\Carbon::parse($membershipExpiresAt)->format('Y-m-d') : '—' }}</td>
                                                <td>{{ $membership->status ?: '—' }}</td>
                                                <td>{{ $membership->payment_status ?: ($latestSubscription->status ?? '—') }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-primary py-0 px-2"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editCircleMembershipModal-{{ $membership->id }}"
                                                        >
                                                            <i class="bi bi-pencil me-1"></i>Edit
                                                        </button>
                                                        <button
                                                            type="submit"
                                                            form="remove-circle-membership-{{ $membership->id }}"
                                                            class="btn btn-sm btn-outline-danger py-0 px-2"
                                                            onclick="return confirm('Remove this circle membership for this peer?');"
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-muted text-center py-3">No joined circle memberships.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <h6 class="fw-bold mb-2 text-dark">Joined Circle Categories</h6>
                            @php
                                $joinedCircleCategoryTrees = $joinedCircleCategoryTrees ?? collect();
                                $registeredMainBusinessCategory = $user->mainBusinessCategory;
                                $registeredBusinessCategory = $user->businessCategory;
                                $hasRegisteredBusinessCategory = $registeredMainBusinessCategory || $registeredBusinessCategory;
                            @endphp

                            @if($joinedCircleCategoryTrees->isEmpty() && ! $hasRegisteredBusinessCategory)
                                <div class="text-muted small">—</div>
                            @else
                                @if($hasRegisteredBusinessCategory)
                                    <div class="border rounded p-3 bg-light-subtle mb-3">
                                        <div class="fw-semibold mb-2 text-muted small">Registered Business Category</div>
                                        <div class="small fw-semibold text-dark">
                                            {{ $registeredMainBusinessCategory?->name ?? '—' }}
                                            @if($registeredBusinessCategory)
                                                <span class="text-muted mx-1">→</span>
                                                {{ $registeredBusinessCategory->name }}
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if($joinedCircleCategoryTrees->isNotEmpty())
                                <div class="row g-3">
                                    @foreach($joinedCircleCategoryTrees as $circleTree)
                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-light-subtle">
                                                <div class="fw-semibold mb-2 text-primary small">
                                                    Joined Circle: {{ $circleTree['circle']?->name ?: ($circleTree['membership']->circle?->name ?? '—') }}
                                                </div>

                                                @php
                                                    $selectedPath = $circleTree['selected_category_path'] ?? [];
                                                @endphp
                                                <div class="small mb-3 d-flex flex-wrap gap-3 bg-white p-2 rounded border">
                                                    <div><strong>Main Category:</strong> {{ $selectedPath['level1']->name ?? '—' }}</div>
                                                    <div><strong>Sub Category:</strong> {{ $selectedPath['level4']->name ?? '—' }}</div>
                                                </div>

                                                @if(($circleTree['categories'] ?? collect())->isEmpty())
                                                    <div class="text-muted small">—</div>
                                                @else
                                                    @foreach($circleTree['categories'] as $mainCategoryTree)
                                                        <div class="mb-2">
                                                            <div class="small fw-semibold text-dark">
                                                                <span class="badge bg-light text-dark border me-1">Main Category:</span> {{ $mainCategoryTree['node']->name }}
                                                                @if($selectedPath['level4'])
                                                                    <span class="text-muted mx-1">→</span>
                                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">Sub Category:</span> {{ $selectedPath['level4']->name }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <h5 class="form-section-title mt-4"><i class="bi bi-shield-lock text-primary me-2"></i>Admin Roles & Permissions</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            @php
                                $currentRoleIds = old('role_ids', $userRoleIds);
                                $currentRoleIds = is_array($currentRoleIds) ? $currentRoleIds : [];
                                $currentIndustryId = old('industry_id', $selectedIndustryId);
                            @endphp
                            @if ($hasAssignedAdminRole)
                                <form id="removeAdminRoleForm" action="{{ route('admin.users.roles.remove', $user->id) }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                                <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong>Currently assigned role(s):</strong>
                                        <span>{{ $assignedAdminRoleNames }}</span>
                                    </div>
                                    <button type="submit"
                                            form="removeAdminRoleForm"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Remove all assigned admin roles from this user?');">
                                        Remove All Roles
                                    </button>
                                </div>
                            @endif
                            <div class="row g-3 align-items-center bg-light p-3 rounded border mb-3">
                                @foreach ($roles as $role)
                                    <div class="col-md-4 d-flex align-items-start">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="role_ids[]"
                                                   value="{{ $role->id }}"
                                                   id="role-{{ $role->id }}"
                                                   data-role-key="{{ $role->key }}"
                                                   @checked(in_array($role->id, $currentRoleIds))>
                                            <label class="form-check-label" for="role-{{ $role->id }}">
                                                <strong class="text-dark">{{ $role->name }}</strong>
                                                <div class="small text-muted">{{ $role->description }}</div>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @php
                                $selectedDedStateId = old('ded_state_id', $assignedDedStateId);
                                $selectedDedStateName = old('ded_state_name', $assignedDedStateName);
                                $selectedDedDistrictId = old('ded_district_id', $assignedDedDistrictId);
                                $selectedDedDistrictName = old('ded_district_name', $assignedDedDistrictName);
                                $dedRoleId = optional($roles->firstWhere('key', 'ded'))->id;
                                $showDedDistrict = $dedRoleId && in_array($dedRoleId, (array) $currentRoleIds);
                            @endphp
                            <div id="dedDistrictField" class="row g-2 mt-2 {{ $showDedDistrict ? '' : 'd-none' }}">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="dedStateId">DED State</label>
                                    <select
                                        id="dedStateId"
                                        name="ded_state_id"
                                        class="form-select @error('ded_state_id') is-invalid @enderror js-no-searchable-select"
                                    >
                                        <option value="">Select state</option>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" @selected((string) $selectedDedStateId === (string) $state->id)>
                                                {{ $state->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="dedStateName" name="ded_state_name" value="{{ $selectedDedStateName }}">
                                    @error('ded_state_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="dedDistrictId">DED District <span class="text-danger">*</span></label>
                                    <select
                                        id="dedDistrictId"
                                        name="ded_district_id"
                                        class="form-select @error('ded_district_id') is-invalid @enderror js-no-searchable-select"
                                    >
                                        <option value="">Select district</option>
                                        @foreach ($assignedDedDistricts as $district)
                                            @php
                                                $districtName = $district->district_name ?? $district->name;
                                                $districtId = $district->district_id ?? null;
                                            @endphp
                                            <option
                                                value="{{ $districtId ?: $district->id }}"
                                                data-district-name="{{ $districtName }}"
                                                @selected((string) $selectedDedDistrictName === (string) $districtName || (string) $selectedDedDistrictId === (string) $districtId)
                                            >
                                                {{ $district->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="dedDistrictName" name="ded_district_name" value="{{ $selectedDedDistrictName }}">
                                    @error('ded_district_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-text">Only districts currently used by users, circles, or DED assignments are shown for the selected state.</div>
                                </div>
                            </div>
                            
                            <div id="industry-director-industry-group" class="row g-3 mt-2 d-none">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="industry-director-industry">Industry <span class="text-danger">*</span></label>
                                    <select id="industry-director-industry" name="industry_id" class="form-select @error('industry_id') is-invalid @enderror">
                                        <option value="">Select industry</option>
                                        @foreach ($industries as $industry)
                                            <option value="{{ $industry->id }}" @selected((string) $currentIndustryId === (string) $industry->id)>
                                                {{ $industry->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('industry_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text text-muted">Required only when Industry Director is selected.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="form-section-title"><i class="bi bi-info-circle text-primary me-2"></i>Read-only Metadata</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small">ID</label>
                            <input type="text" class="form-control bg-light" value="{{ $user->id }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Created At</label>
                            <input type="text" class="form-control bg-light" value="{{ optional($user->created_at)->toDateTimeString() }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Updated At</label>
                            <input type="text" class="form-control bg-light" value="{{ optional($user->updated_at)->toDateTimeString() }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Last Login</label>
                            <input type="text" class="form-control bg-light" value="{{ optional($user->last_login_at)->toDateTimeString() }}" disabled>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('membership-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-check-circle me-1"></i>Save Changes
                        </button>
                    </div>
                </div>

                <!-- Tab 5: Story Submissions -->
                <div class="tab-pane fade" id="stories-section" role="tabpanel" aria-labelledby="stories-tab">
                    <h5 class="form-section-title"><i class="bi bi-journal-text text-primary me-2"></i>Story Submissions</h5>
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-dark border">Total Submissions: {{ $storySubmissionsCount }}</span>
                    </div>

                    <div class="table-responsive border rounded mb-4">
                        <table class="table table-premium mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Submission Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($storySubmissions as $story)
                                    <tr>
                                        <td><strong>{{ $story->title ?: $story->business_name }}</strong></td>
                                        <td>
                                            @php
                                                $statusBadge = match (strtolower(trim($story->status))) {
                                                    'approved' => 'bg-success-subtle text-success border border-success-subtle',
                                                    'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                    'pending', 'new', 'in_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                                    default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                                };
                                            @endphp
                                            <span class="badge {{ $statusBadge }}">{{ ucfirst($story->status) }}</span>
                                        </td>
                                        <td>{{ $story->submitted_at ? $story->submitted_at->format('d M Y, h:i A') : $story->created_at->format('d M Y, h:i A') }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewStory{{ $story->id }}">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No story submissions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('circles-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                    </div>
                </div>

                <!-- Tab 6: Introduced Members -->
                <div class="tab-pane fade" id="introduced-section" role="tabpanel" aria-labelledby="introduced-tab">
                    <h5 class="form-section-title"><i class="bi bi-people-fill text-primary me-2"></i>Introduced Members</h5>

                    <!-- Introducer Details -->
                    <div class="card mb-4 bg-light border-0 shadow-none">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-check text-primary me-2"></i>Introducer Details</h6>
                            @if ($user->introducedBy)
                                @php
                                    $introducer = $user->introducedBy;
                                    $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                    
                                    // Parse city
                                    $introducerCityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
                                    $introducerRawCity = $introducerCityModel->name ?? $introducer->city ?? '';
                                    if (is_string($introducerRawCity)) {
                                        $introducerRawCity = trim($introducerRawCity);
                                        if (str_starts_with($introducerRawCity, '{')) {
                                            $decodedCity = json_decode($introducerRawCity, true);
                                            if (is_array($decodedCity)) {
                                                $introducerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $introducerRawCity;
                                            } elseif (preg_match('/name:\s*([^,}]+)/', $introducerRawCity, $matches)) {
                                                $introducerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                            } else {
                                                $introducerCityName = $introducerRawCity;
                                            }
                                        } else {
                                            $introducerCityName = $introducerRawCity;
                                        }
                                    } elseif (is_array($introducerRawCity)) {
                                        $introducerCityName = $introducerRawCity['name'] ?? $introducerRawCity['label'] ?? '';
                                    } elseif (is_object($introducerRawCity)) {
                                        $introducerCityName = $introducerRawCity->name ?? $introducerRawCity->label ?? '';
                                    } else {
                                        $introducerCityName = $introducerRawCity;
                                    }
                                    
                                    if (in_array(strtolower(trim((string)$introducerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                        $introducerCityName = null;
                                    }
                                    
                                    // Parse company
                                    $introducerCompany = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
                                    if (in_array(strtolower(trim((string)$introducerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                        $introducerCompany = null;
                                    }
                                @endphp
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <span class="small text-muted d-block fw-semibold">Name</span>
                                        <span class="text-dark fw-bold">{{ $introducerName ?: '—' }}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="small text-muted d-block fw-semibold">Company Name</span>
                                        @if ($introducerCompany)
                                            <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap">
                                                <i class="bi bi-building text-muted small"></i>{{ $introducerCompany }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <span class="small text-muted d-block fw-semibold">City</span>
                                        @if ($introducerCityName)
                                            <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap">
                                                <i class="bi bi-geo-alt text-muted small"></i>{{ $introducerCityName }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="text-muted"><i class="bi bi-info-circle me-1"></i>No introducer set for this peer.</div>
                            @endif
                        </div>
                    </div>

                    <!-- Pending Introduction Requests alert -->
                    @if (!empty($pendingIntroRequestsCount) && $pendingIntroRequestsCount > 0)
                        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                            <i class="bi bi-clock-history"></i>
                            <span>
                                This member has
                                <strong>{{ $pendingIntroRequestsCount }}</strong>
                                pending introduction
                                {{ Str::plural('request', $pendingIntroRequestsCount) }}.
                                <a href="{{ route('admin.introduction-requests.index') }}" class="alert-link ms-1">Review requests</a>
                            </span>
                        </div>
                    @endif

                    <!-- Introduced Peers List -->
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-dark border">Total Introduced: {{ $introducedPeersCount }}</span>
                        @if (!($isReadOnly ?? false))
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addIntroducedMemberModal">
                                <i class="bi bi-plus-lg"></i> Add Introduced Member
                            </button>
                        @endif
                    </div>


                    <div class="table-responsive border rounded mb-4">
                        <table class="table table-premium mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Peer Name</th>
                                    <th>Company Name</th>
                                    <th>City</th>
                                    <th>Designation</th>
                                    <th>Introduced Members</th>
                                    @if (!($isReadOnly ?? false))
                                        <th class="text-end">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($introducedPeers as $peer)
                                    @php
                                        $peerName = $peer->name ?? trim((($peer->first_name ?? '') . ' ' . ($peer->last_name ?? '')));
                                        $peerAvatar = $peer->profile_photo_url ?? ($peer->profile_photo_file_id ? url('/api/v1/files/' . $peer->profile_photo_file_id) : null);
                                        $peerGradientIndex = abs(crc32((string) $peer->id)) % 5;

                                        // Parse city
                                        $peerCityModel = $peer->getRelation('city') ?? $peer->cityRelation ?? null;
                                        $peerRawCity = $peerCityModel->name ?? $peer->city ?? '';
                                        if (is_string($peerRawCity)) {
                                            $peerRawCity = trim($peerRawCity);
                                            if (str_starts_with($peerRawCity, '{')) {
                                                $decodedCity = json_decode($peerRawCity, true);
                                                if (is_array($decodedCity)) {
                                                    $peerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $peerRawCity;
                                                } elseif (preg_match('/name:\s*([^,}]+)/', $peerRawCity, $matches)) {
                                                    $peerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                                } else {
                                                    $peerCityName = $peerRawCity;
                                                }
                                            } else {
                                                $peerCityName = $peerRawCity;
                                            }
                                        } elseif (is_array($peerRawCity)) {
                                            $peerCityName = $peerRawCity['name'] ?? $peerRawCity['label'] ?? '';
                                        } elseif (is_object($peerRawCity)) {
                                            $peerCityName = $peerRawCity->name ?? $peerRawCity->label ?? '';
                                        } else {
                                            $peerCityName = $peerRawCity;
                                        }
                                        
                                        if (in_array(strtolower(trim((string)$peerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                            $peerCityName = null;
                                        }
                                        
                                        // Parse company
                                        $peerCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
                                        if (in_array(strtolower(trim((string)$peerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                            $peerCompany = null;
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="peer-avatar-wrapper" style="width: 32px; height: 32px;">
                                                    @if ($peerAvatar)
                                                        <img src="{{ $peerAvatar }}" alt="{{ $peerName }}" class="peer-avatar-image" style="width: 32px; height: 32px;">
                                                    @else
                                                        <div class="peer-avatar-placeholder bg-gradient-peer-{{ $peerGradientIndex }}" style="width: 32px; height: 32px; font-size: 0.8rem; line-height: 32px;">
                                                            {{ strtoupper(substr($peerName !== '' ? $peerName : 'U', 0, 1)) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $peerName !== '' ? $peerName : '—' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($peerCompany)
                                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                                    <i class="bi bi-building text-muted small"></i>{{ $peerCompany }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($peerCityName)
                                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                                    <i class="bi bi-geo-alt text-muted small"></i>{{ $peerCityName }}
                                                </span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $peer->designation ?? '—' }}</td>
                                        <td>
                                            @php
                                                $peerIntroducedCount = (int) ($peer->introduced_members_count ?? 0);
                                            @endphp
                                            @if ($peerIntroducedCount > 0)
                                                <a href="{{ route('admin.users.edit', $peer->id) }}#introduced-tab" class="text-primary fw-semibold text-decoration-none">
                                                    {{ $peerIntroducedCount }}
                                                </a>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        @if (!($isReadOnly ?? false))
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.preventDefault(); if(confirm('Are you sure you want to remove this introduced member?')) { document.getElementById('remove-introduced-peer-{{ $peer->id }}').submit(); }">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ !($isReadOnly ?? false) ? 6 : 5 }}" class="text-center text-muted py-4">No introduced members found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-outline-secondary" onclick="switchTab('stories-tab')">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@foreach ($introducedPeers as $peer)
    <form
        id="remove-introduced-peer-{{ $peer->id }}"
        method="POST"
        action="{{ route('admin.users.introduced-members.destroy', [$user->id, $peer->id]) }}"
        class="d-none"
    >
        @csrf
        @method('DELETE')
    </form>
@endforeach

@foreach ($circleMemberships as $membership)
    <form
        id="remove-circle-membership-{{ $membership->id }}"
        method="POST"
        action="{{ route('admin.users.circle-members.destroy', [$user->id, $membership->id]) }}"
        class="d-none"
    >
        @csrf
        @method('DELETE')
    </form>

    @php
        $membershipTree = $joinedCircleCategoryTrees->firstWhere('membership.id', $membership->id) ?? [];
        $memSelectedIds = $membershipTree['selected_ids'] ?? [
            'level1' => $membership->level_1_category_id ?? 0,
            'level2' => $membership->level_2_category_id ?? 0,
            'level3' => $membership->level_3_category_id ?? 0,
            'level4' => $membership->level_4_category_id ?? 0,
        ];
        $memCircleId = (string) $membership->circle_id;
        $memCircleOptions = $circleCategoryOptionsByCircle[$memCircleId] ?? ['level1' => [], 'level2' => [], 'level3' => [], 'level4' => []];
        $firstL1 = $memCircleOptions['level1'][0] ?? null;
        $memLevel1Name = $membershipTree['selected_category_path']['level1']->name ?? ($firstL1['name'] ?? '—');
        $memLevel1Id = $memSelectedIds['level1'] ?: ($firstL1['id'] ?? '');
        $memCircleJoinedAt = optional($membership->joined_at)->format('Y-m-d');
        $memCircleExpiresAt = $membership->expires_at ? \Illuminate\Support\Carbon::parse($membership->expires_at)->format('Y-m-d') : '';

        $selectedL1Str = (string) $memLevel1Id;
        $modalL4List = collect($memCircleOptions['level4'] ?? [])->filter(function ($item) use ($selectedL1Str) {
            $itemL1 = (string) ($item['level1_id'] ?? $item['parent_id'] ?? $item['circle_category_id'] ?? '');
            return $selectedL1Str === '' || $itemL1 === $selectedL1Str;
        })->values();
        if ($modalL4List->isEmpty()) {
            $modalL4List = collect($memCircleOptions['level4'] ?? []);
        }
    @endphp
    <div class="modal fade" id="editCircleMembershipModal-{{ $membership->id }}" tabindex="-1" aria-labelledby="editCircleMembershipLabel-{{ $membership->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content text-start">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark" id="editCircleMembershipLabel-{{ $membership->id }}">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Circle Membership
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.users.circle-members.update', [$user->id, $membership->id]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="p-3 bg-light-subtle rounded border d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="small text-muted fw-semibold">Circle</div>
                                        <div class="fw-bold text-primary fs-6">{{ $membership->circle?->name ?: '—' }}</div>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                        {{ ucfirst($membership->status ?: 'Active') }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_level1_{{ $membership->id }}">Main Category</label>
                                <select
                                    name="level1_category_id"
                                    id="modal_level1_{{ $membership->id }}"
                                    class="form-select js-modal-level1 js-no-searchable-select js-no-select2"
                                    data-membership-id="{{ $membership->id }}"
                                    data-circle-id="{{ $memCircleId }}"
                                >
                                    <option value="">Select main category</option>
                                    @foreach ($memCircleOptions['level1'] as $l1)
                                        <option value="{{ $l1['id'] }}" @selected((int) $memLevel1Id === (int) $l1['id'])>
                                            {{ $l1['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <input type="hidden" name="level2_category_id" id="modal_level2_{{ $membership->id }}" value="{{ $memSelectedIds['level2'] ?: '' }}">
                            <input type="hidden" name="level3_category_id" id="modal_level3_{{ $membership->id }}" value="{{ $memSelectedIds['level3'] ?: '' }}">

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_level4_{{ $membership->id }}">Sub Category</label>
                                <select
                                    name="level4_category_id"
                                    id="modal_level4_{{ $membership->id }}"
                                    class="form-select js-modal-level4 js-no-searchable-select js-no-select2"
                                    data-membership-id="{{ $membership->id }}"
                                    data-circle-id="{{ $memCircleId }}"
                                    data-selected-level4="{{ $memSelectedIds['level4'] ?: '' }}"
                                >
                                    <option value="">Select sub category</option>
                                    @foreach ($modalL4List as $l4)
                                        <option
                                            value="{{ $l4['id'] }}"
                                            data-level1-id="{{ $l4['level1_id'] ?? '' }}"
                                            data-level2-id="{{ $l4['level2_id'] ?? '' }}"
                                            data-level3-id="{{ $l4['level3_id'] ?? '' }}"
                                            @selected((int) $memSelectedIds['level4'] === (int) $l4['id'])
                                        >
                                            {{ $l4['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_joined_at_{{ $membership->id }}">Circle Joined Date</label>
                                <input type="date" name="circle_joined_at" id="modal_joined_at_{{ $membership->id }}" class="form-control" value="{{ $memCircleJoinedAt }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_expires_at_{{ $membership->id }}">Circle Expiry Date</label>
                                <input type="date" name="circle_expires_at" id="modal_expires_at_{{ $membership->id }}" class="form-control" value="{{ $memCircleExpiresAt }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_status_{{ $membership->id }}">Member Status</label>
                                <select name="status" id="modal_status_{{ $membership->id }}" class="form-select">
                                    @foreach (['active' => 'Active', 'approved' => 'Approved', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected(strtolower((string) $membership->status) === $statusKey)>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="modal_payment_status_{{ $membership->id }}">Payment Status</label>
                                <select name="payment_status" id="modal_payment_status_{{ $membership->id }}" class="form-select">
                                    @foreach (['paid' => 'Paid', 'free' => 'Free', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'expired' => 'Expired'] as $payKey => $payLabel)
                                        <option value="{{ $payKey }}" @selected(strtolower((string) $membership->payment_status) === $payKey)>
                                            {{ $payLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@foreach ($storySubmissions as $story)
    @php
        $statusBadge = match (strtolower(trim($story->status))) {
            'approved' => 'bg-success-subtle text-success border border-success-subtle',
            'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'pending', 'new', 'in_review' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    @endphp
    <div class="modal fade" id="viewStory{{ $story->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content text-start">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Story Submission Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 text-start">
                            <label class="small text-muted d-block fw-semibold">Title</label>
                            <div class="text-dark fs-5 fw-bold">{{ $story->title ?: $story->business_name }}</div>
                        </div>
                        <div class="col-md-6 text-start">
                            <label class="small text-muted d-block fw-semibold">Status</label>
                            <span class="badge {{ $statusBadge }}">{{ ucfirst($story->status) }}</span>
                        </div>
                        <div class="col-md-6 text-start">
                            <label class="small text-muted d-block fw-semibold">Submission Date</label>
                            <div class="text-dark">{{ $story->submitted_at ? $story->submitted_at->format('d M Y, h:i A') : $story->created_at->format('d M Y, h:i A') }}</div>
                        </div>
                        @if($story->status === 'approved' && $story->approved_at)
                            <div class="col-md-6 text-start">
                                <label class="small text-muted d-block fw-semibold">Approved At</label>
                                <div class="text-dark">{{ \Carbon\Carbon::parse($story->approved_at)->format('d M Y, h:i A') }}</div>
                            </div>
                        @endif
                    </div>

                    @if($story->short_description)
                        <div class="mb-4 text-start">
                            <label class="small text-muted d-block fw-semibold mb-1">Short Description</label>
                            <div class="border rounded p-3 bg-light text-dark">{{ $story->short_description }}</div>
                        </div>
                    @endif

                    <div class="mb-4 text-start">
                        <label class="small text-muted d-block fw-semibold mb-1">Story Content</label>
                        <div class="border rounded p-3 bg-light text-dark" style="white-space: pre-wrap;">{{ $story->story ?: $story->company_introduction }}</div>
                    </div>

                    @if($story->cover_image)
                        <div class="mb-4 text-start text-center">
                            <label class="small text-muted d-block fw-semibold mb-1 text-start">Cover Image</label>
                            <div class="mt-2 text-center bg-light p-2 border rounded">
                                <img src="{{ url('/api/v1/files/' . $story->cover_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 300px; object-fit: contain;" alt="Cover image">
                            </div>
                        </div>
                    @endif

                    @if($story->attachments && count($story->attachments) > 0)
                        <div class="mb-4 text-start">
                            <label class="small text-muted d-block fw-semibold mb-1">Attachments</label>
                            <div class="list-group list-group-flush border rounded mt-2">
                                @foreach($story->attachments as $attachmentId)
                                    <a href="{{ url('/api/v1/files/' . $attachmentId) }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center py-2 text-dark">
                                        <i class="bi bi-file-earmark-check text-primary me-2"></i>
                                        <span class="small text-truncate">File ID: {{ $attachmentId }}</span>
                                        <i class="bi bi-box-arrow-up-right ms-auto small text-muted"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($story->rejected_reason)
                        <div class="mb-4 text-start">
                            <label class="small text-muted d-block fw-semibold mb-1">Rejection Reason</label>
                            <div class="border rounded p-3 bg-danger-subtle text-danger border-danger-subtle">{{ $story->rejected_reason }}</div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

@if (!($isReadOnly ?? false))
    <!-- Add Introduced Member Modal -->
    <div class="modal fade" id="addIntroducedMemberModal" tabindex="-1" aria-labelledby="addIntroducedMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.users.introduced-members.store', $user->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addIntroducedMemberModalLabel">
                            <i class="bi bi-person-plus text-primary me-2"></i>Add Introduced Member
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <div class="mb-3">
                            <label for="introduced_member_select" class="form-label fw-semibold">Select User</label>
                            <select id="introduced_member_select" name="introduced_member_id" class="form-select" style="width: 100%;" required></select>
                            <div class="form-text">Search by name, email, company, or phone. Only non-deleted users can be selected.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
function switchTab(tabId) {
    const tabEl = document.getElementById(tabId);
    if (!tabEl) return;
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        try {
            const bsTab = bootstrap.Tab.getOrCreateInstance(tabEl);
            bsTab.show();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        } catch (e) {}
    }

    const targetSelector = tabEl.getAttribute('data-bs-target');
    if (targetSelector) {
        document.querySelectorAll('#editPeerTabs .nav-link').forEach(t => {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('#editPeerTabsContent > .tab-pane').forEach(p => {
            p.classList.remove('show', 'active');
        });
        
        tabEl.classList.add('active');
        tabEl.setAttribute('aria-selected', 'true');
        const targetPane = document.querySelector(targetSelector);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#editPeerTabs .nav-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            switchTab(this.id);
        });
    });

    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        if (hash === 'introduced-section' || hash === 'introduced-tab') {
            switchTab('introduced-tab');
        }
    }
    if (window.jQuery && window.jQuery.fn.select2 && jQuery('#introduced_member_select').length) {
        jQuery('#introduced_member_select').select2({
            dropdownParent: jQuery('#addIntroducedMemberModal'),
            placeholder: 'Search and select user...',
            allowClear: true,
            width: '100%',
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
                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.id,
                                text: item.label_inline || item.label || item.name
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 1
        });
    }
    const roleCheckboxes = Array.from(document.querySelectorAll('input[name="role_ids[]"]'));
    const dedDistrictField = document.getElementById('dedDistrictField');
    const dedStateSelect = document.getElementById('dedStateId');
    const dedDistrictSelect = document.getElementById('dedDistrictId');
    const dedDistrictNameInput = document.getElementById('dedDistrictName');
    const dedStateNameInput = document.getElementById('dedStateName');
    const selectedDedDistrictName = @json((string) old('ded_district_name', $assignedDedDistrictName));
    const dedRoleFieldsLocked = false;
    const districtUrlTemplate = @json(route('admin.location.states.districts', ['state' => '__STATE__']));

    function setDistrictOptions(districts, selectedName = '') {
        if (!dedDistrictSelect) return;

        dedDistrictSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = districts.length ? 'Select district' : 'No districts found for selected state';
        dedDistrictSelect.appendChild(placeholder);

        const seenDistricts = new Set();
        districts.forEach((district) => {
            const option = document.createElement('option');
            const districtName = String(district.district_name || district.name || '').trim();
            const districtId = district.district_id || district.id;
            const districtKey = districtName
                .toLowerCase()
                .replace(/\([^)]*\)/g, ' ')
                .replace(/[,;|\/].*$/g, '')
                .replace(/\b(dist|district|city)\b/g, ' ')
                .replace(/[^a-z0-9]+/g, '');

            if (!districtName || !districtId || seenDistricts.has(districtKey)) {
                return;
            }

            seenDistricts.add(districtKey);
            option.value = districtId;
            option.textContent = districtName;
            option.dataset.districtName = districtName;
            option.selected = String(districtName).toLowerCase() === String(selectedName).trim().toLowerCase();
            dedDistrictSelect.appendChild(option);
        });
    }

    function syncDedHiddenFields() {
        if (dedDistrictNameInput && dedDistrictSelect) {
            dedDistrictNameInput.value = dedDistrictSelect.selectedOptions[0]?.dataset.districtName || '';
        }

        if (dedStateNameInput && dedStateSelect) {
            dedStateNameInput.value = dedStateSelect.value ? (dedStateSelect.selectedOptions[0]?.textContent?.trim() || '') : '';
        }
    }

    async function loadDistrictsForState(stateId, selectedName = '') {
        if (!dedDistrictSelect) return;

        if (!stateId) {
            setDistrictOptions([]);
            dedDistrictSelect.disabled = true;
            syncDedHiddenFields();
            return;
        }

        dedDistrictSelect.disabled = true;
        dedDistrictSelect.innerHTML = '<option value="">Loading districts...</option>';

        try {
            const response = await fetch(districtUrlTemplate.replace('__STATE__', encodeURIComponent(stateId)), {
                headers: { 'Accept': 'application/json' },
            });
            const payload = await response.json();
            const districts = Array.isArray(payload.data) ? payload.data : [];
            setDistrictOptions(districts, selectedName);
            dedDistrictSelect.disabled = false;
            syncDedHiddenFields();
        } catch (error) {
            setDistrictOptions([]);
            dedDistrictSelect.disabled = false;
            syncDedHiddenFields();
        }
    }

    function updateDedDistrictVisibility() {
        const dedSelected = roleCheckboxes.some((checkbox) => checkbox.dataset.roleKey === 'ded' && checkbox.checked);

        if (dedDistrictField) {
            dedDistrictField.classList.toggle('d-none', !dedSelected);
        }

        if (dedStateSelect) {
            dedStateSelect.required = dedSelected && !dedStateSelect.disabled;
            if (!dedSelected && !dedStateSelect.disabled) {
                dedStateSelect.value = '';
            }
        }

        if (dedDistrictSelect) {
            if (dedSelected && !dedRoleFieldsLocked) {
                dedDistrictSelect.disabled = false;
                if (dedDistrictSelect.options.length <= 1) {
                    loadDistrictsForState(dedStateSelect?.value || '', selectedDedDistrictName);
                }
            }

            dedDistrictSelect.required = dedSelected && !dedDistrictSelect.disabled;
            if (!dedSelected && !dedDistrictSelect.disabled) {
                setDistrictOptions([]);
                dedDistrictSelect.disabled = true;
                syncDedHiddenFields();
            }
        }
    }

    dedStateSelect?.addEventListener('change', function () {
        loadDistrictsForState(this.value);
        syncDedHiddenFields();
    });
    dedDistrictSelect?.addEventListener('change', syncDedHiddenFields);
    roleCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateDedDistrictVisibility));
    updateDedDistrictVisibility();

    if (dedStateSelect?.value && dedDistrictSelect && !dedDistrictSelect.disabled && dedDistrictSelect.options.length <= 1) {
        loadDistrictsForState(dedStateSelect.value, selectedDedDistrictName);
    }
    const roleInputs = Array.from(document.querySelectorAll('input[name="role_ids[]"]'));
    const industryDirectorRoleId = @json($industryDirectorRoleId);
    const industryGroup = document.getElementById('industry-director-industry-group');
    const industrySelect = document.getElementById('industry-director-industry');

    function syncIndustryDirectorIndustry() {
        const industryDirectorSelected = roleInputs.some((input) => input.checked && input.value === String(industryDirectorRoleId));

        if (industryGroup) {
            industryGroup.classList.toggle('d-none', ! industryDirectorSelected);
        }

        if (industrySelect) {
            industrySelect.required = industryDirectorSelected;
            if (! industryDirectorSelected) {
                industrySelect.value = '';
            }
        }
    }

    roleInputs.forEach((input) => {
        input.addEventListener('change', () => {
            if (input.checked) {
                roleInputs.forEach((otherInput) => {
                    if (otherInput !== input) {
                        otherInput.checked = false;
                    }
                });
            }
            syncIndustryDirectorIndustry();
        });
    });

    syncIndustryDirectorIndustry();
    const joinedInput = document.querySelector('[name="circle_joined_date"]')
        || document.querySelector('[name="circle_joined_at"]');
    const expiryInput = document.querySelector('[name="circle_expiry_date"]')
        || document.querySelector('[name="circle_expires_at"]');

    function parseDate(value) {
        if (!value) return null;

        const dmY = value.match(/^(\d{2})-(\d{2})-(\d{4})$/);
        if (dmY) {
            return new Date(Number(dmY[3]), Number(dmY[2]) - 1, Number(dmY[1]));
        }

        const ymD = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (ymD) {
            return new Date(Number(ymD[1]), Number(ymD[2]) - 1, Number(ymD[3]));
        }

        return null;
    }

    function formatDate(date, useNativeDateFormat) {
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();

        return useNativeDateFormat ? `${yyyy}-${mm}-${dd}` : `${dd}-${mm}-${yyyy}`;
    }

    if (joinedInput && expiryInput) {
        joinedInput.addEventListener('change', function () {
            if (expiryInput.value) return;

            const joinedDate = parseDate(joinedInput.value);
            if (!joinedDate) return;

            const expiryDate = new Date(joinedDate);
            expiryDate.setFullYear(expiryDate.getFullYear() + 1);

            expiryInput.value = formatDate(expiryDate, expiryInput.type === 'date');
        });
    }

    syncDedHiddenFields();
});
</script>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const uploadUrl = '{{ route('admin.files.upload') }}';
        const circleCategoryOptionsByCircle = @json($circleCategoryOptionsByCircle ?? []);
        const mainToSubCategoriesMap = @json($mainToSubCategoriesMap ?? []);
        const oldLevel1 = '{{ old('level1_category_id', old('level_1_category_id', '')) }}';
        const oldLevel2 = '{{ old('level2_category_id', old('level_2_category_id', '')) }}';
        const oldLevel3 = '{{ old('level3_category_id', old('level_3_category_id', '')) }}';
        const oldLevel4 = '{{ old('level4_category_id', old('level_4_category_id', '')) }}';

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

        const circleSelect = document.getElementById('additional_circle_id');
        const level1NameInput = document.getElementById('level1_category_name');
        const level1IdInput = document.getElementById('level1_category_id');
        const level2Select = document.getElementById('level2_category_id');
        const level3Select = document.getElementById('level3_category_id');
        const level4Select = document.getElementById('level4_category_id');

        const resetSelect = (selectEl, placeholder, disabled = true) => {
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            selectEl.disabled = disabled;
        };

        const fillSelect = (selectEl, options, placeholder, selectedValue = '') => {
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;
            (options || []).forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.name;
                if (item.level2_id) {
                    option.dataset.level2Id = String(item.level2_id);
                }
                if (item.level3_id) {
                    option.dataset.level3Id = String(item.level3_id);
                }
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

        const getCircleData = () => {
            const circleId = circleSelect?.value || '';
            return circleCategoryOptionsByCircle[String(circleId)] || { level1: [], level2: [], level3: [], level4: [] };
        };

        const syncHiddenLevelsFromLevel4 = () => {
            if (!level4Select) return;
            const selectedOption = level4Select.options[level4Select.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const data = getCircleData();
                const l4Id = selectedOption.value;
                const l4Item = (data.level4 || []).find((item) => String(item.id) === String(l4Id));
                if (level2Select) {
                    level2Select.value = l4Item?.level2_id || selectedOption.dataset.level2Id || '';
                }
                if (level3Select) {
                    level3Select.value = l4Item?.level3_id || selectedOption.dataset.level3Id || '';
                }
            } else {
                if (level2Select) level2Select.value = '';
                if (level3Select) level3Select.value = '';
            }
        };

        const handleCircleChange = (presetLevel4 = '') => {
            const data = getCircleData();
            const firstL1 = (data.level1 || [])[0] || null;

            if (firstL1) {
                if (level1NameInput) level1NameInput.value = firstL1.name || '';
                if (level1IdInput) level1IdInput.value = String(firstL1.id || '');

                const level4Options = (data.level4 || []).filter((item) => {
                    const itemL1 = item.level1_id || item.parent_id || item.circle_category_id;
                    return String(itemL1) === String(firstL1.id);
                });
                fillSelect(level4Select, level4Options, 'Select sub category', presetLevel4 || oldLevel4);
            } else {
                if (level1NameInput) level1NameInput.value = '';
                if (level1IdInput) level1IdInput.value = '';
                resetSelect(level4Select, 'Select sub category');
            }
            syncHiddenLevelsFromLevel4();
        };

        circleSelect?.addEventListener('change', () => {
            handleCircleChange();
        });

        level4Select?.addEventListener('change', () => syncHiddenLevelsFromLevel4());

        handleCircleChange(oldLevel4);

        // Initialize category pickers for each edit circle membership modal
        const initMembershipModalCategories = (modalEl) => {
            const modalL1Select = modalEl.querySelector('.js-modal-level1');
            const modalL4Select = modalEl.querySelector('.js-modal-level4');
            if (!modalL1Select || !modalL4Select) return;

            const memberId = modalL1Select.dataset.membershipId;
            const circleId = modalL1Select.dataset.circleId;
            const level2Input = document.getElementById(`modal_level2_${memberId}`);
            const level3Input = document.getElementById(`modal_level3_${memberId}`);

            const circleData = circleCategoryOptionsByCircle[String(circleId)] || { level1: [], level2: [], level3: [], level4: [] };

            const syncModalHiddenLevels = () => {
                const selectedOption = modalL4Select.options[modalL4Select.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    const l4Item = (circleData.level4 || []).find((item) => String(item.id) === String(selectedOption.value));
                    if (level2Input) level2Input.value = l4Item?.level2_id || selectedOption.dataset.level2Id || '';
                    if (level3Input) level3Input.value = l4Item?.level3_id || selectedOption.dataset.level3Id || '';
                } else {
                    if (level2Input) level2Input.value = '';
                    if (level3Input) level3Input.value = '';
                }
            };

            const updateModalLevel4 = (presetLevel4 = '') => {
                const selectedL1 = modalL1Select.value || '';
                if (!selectedL1) {
                    fillSelect(modalL4Select, circleData.level4 || [], 'Select sub category', presetLevel4);
                    syncModalHiddenLevels();
                    return;
                }
                let l4Options = (circleData.level4 || []).filter((item) => {
                    const itemL1 = item.level1_id || item.parent_id || item.circle_category_id;
                    return String(itemL1) === String(selectedL1);
                });
                if (l4Options.length === 0) {
                    l4Options = circleData.level4 || [];
                }
                fillSelect(modalL4Select, l4Options, 'Select sub category', presetLevel4);
                syncModalHiddenLevels();
            };

            const onL1Change = () => {
                updateModalLevel4();
            };

            modalL1Select.addEventListener('change', onL1Change);
            if (window.jQuery) {
                window.jQuery(modalL1Select).on('change select2:select select2:clear', onL1Change);
            }

            modalL4Select.addEventListener('change', () => syncModalHiddenLevels());
            if (window.jQuery) {
                window.jQuery(modalL4Select).on('change select2:select select2:clear', () => syncModalHiddenLevels());
            }

            modalEl.addEventListener('shown.bs.modal', () => {
                const selectedL4 = modalL4Select.dataset.selectedLevel4 || modalL4Select.value || '';
                updateModalLevel4(selectedL4);
            });

            const initialLevel4 = modalL4Select.dataset.selectedLevel4 || '';
            updateModalLevel4(initialLevel4);
        };

        document.querySelectorAll('[id^="editCircleMembershipModal-"]').forEach((modalEl) => {
            initMembershipModalCategories(modalEl);
        });

        // Tab 2 Business Classification Categories
        const businessMainCatSelect = document.getElementById('business_main_category_id');
        const businessSubCatSelect = document.getElementById('business_sub_category_id');

        const updateBusinessSubCategories = (presetSub = '') => {
            if (!businessMainCatSelect || !businessSubCatSelect) return;
            const selectedMainId = businessMainCatSelect.value || '';
            if (!selectedMainId) {
                fillSelect(businessSubCatSelect, [], 'Null', presetSub);
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

@if($isReadOnly ?? false)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#editPeerTabs .nav-link').forEach(function(tabBtn) {
            tabBtn.disabled = false;
            tabBtn.removeAttribute('disabled');
            tabBtn.style.pointerEvents = 'auto';
            tabBtn.style.cursor = 'pointer';
        });

        document.querySelectorAll('#userEditForm input, #userEditForm select, #userEditForm textarea, #userEditForm button').forEach(function(el) {
            if (el.classList.contains('nav-link') || el.getAttribute('role') === 'tab' || el.hasAttribute('data-bs-toggle') || el.closest('#editPeerTabs')) {
                return;
            }
            if (el.tagName === 'BUTTON' && (el.type === 'submit' || el.innerText.includes('Remove') || el.innerText.includes('Delete') || el.innerText.includes('Save') || el.innerText.includes('Update') || el.innerText.includes('Send') || el.classList.contains('btn-danger') || el.classList.contains('btn-success'))) {
                el.style.display = 'none';
            } else if (el.tagName !== 'A' && !el.classList.contains('btn-close') && !el.classList.contains('btn-outline-secondary')) {
                el.disabled = true;
            }
        });
        
        document.querySelectorAll('form button[type="submit"], form input[type="submit"], .card-header form, .card-header button').forEach(function(el) {
            el.style.display = 'none';
        });

        document.querySelectorAll('.btn-outline-primary, .btn-outline-danger').forEach(function(el) {
            if (el.innerText.includes('Send') || el.innerText.includes('Remove') || el.innerText.includes('Delete')) {
                el.style.display = 'none';
            }
        });
    });
</script>
@endif
@endpush
