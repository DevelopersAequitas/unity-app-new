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

<div class="card-activities-wrapper mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-pills nav-fill bg-light border-bottom p-2 gap-1" id="editPeerTabs" role="tablist">
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
                    <i class="bi bi-circle me-1"></i>4. Circles & Admin
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-2 px-3 fw-semibold" id="stories-tab" data-bs-toggle="pill" data-bs-target="#stories-section" type="button" role="tab" aria-controls="stories-section" aria-selected="false">
                    <i class="bi bi-journal-text me-1"></i>5. Story Submissions ({{ $storySubmissionsCount }})
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
                            <div id="profilePhotoExisting" class="{{ $user->profile_photo_file_id ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($user->profile_photo_file_id)
                                        <img src="{{ url('/api/v1/files/' . $user->profile_photo_file_id) }}" alt="Profile preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 80px; object-fit: cover;">
                                    @endif
                                    <div>
                                        <a href="{{ $user->profile_photo_file_id ? url('/api/v1/files/' . $user->profile_photo_file_id) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="profilePhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="profilePhotoUpload" class="{{ $user->profile_photo_file_id ? 'd-none' : '' }}">
                                <input type="file" class="form-control" id="profilePhotoFile" accept="image/*">
                                <div class="form-text" id="profilePhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Cover Photo</label>
                            <input type="hidden" name="cover_photo_file_id" id="coverPhotoFileId" value="{{ old('cover_photo_file_id', $user->cover_photo_file_id) }}">
                            <div id="coverPhotoExisting" class="{{ $user->cover_photo_file_id ? '' : 'd-none' }} border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    @if ($user->cover_photo_file_id)
                                        <img src="{{ url('/api/v1/files/' . $user->cover_photo_file_id) }}" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 140px; object-fit: cover;">
                                    @endif
                                    <div>
                                        <a href="{{ $user->cover_photo_file_id ? url('/api/v1/files/' . $user->cover_photo_file_id) : '#' }}" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="coverPhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="coverPhotoUpload" class="{{ $user->cover_photo_file_id ? 'd-none' : '' }}">
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

                    <h5 class="form-section-title mt-4"><i class="bi bi-leaf text-success me-2"></i>Greenpreneur Sustainability Info</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="text" name="website" class="form-control" value="{{ old('website', $user->website) }}" placeholder="https://example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">List in Community Directory?</label>
                            <select name="community_directory_listing" class="form-select">
                                <option value="Yes" @selected(old('community_directory_listing', $user->community_directory_listing) === 'Yes')>Yes</option>
                                <option value="No" @selected(old('community_directory_listing', $user->community_directory_listing ?? 'No') === 'No')>No</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">How does your business contribute to sustainability?</label>
                            <textarea name="sustainability_contribution" class="form-control" rows="3" placeholder="Describe contribution...">{{ old('sustainability_contribution', $user->sustainability_contribution) }}</textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label d-block fw-semibold mb-2">Which sustainability areas do you focus on?</label>
                            <div class="row g-2">
                                @php
                                    $sustainabilityAreasOptions = [
                                        'Renewable Energy', 'Waste Management', 'Water Conservation', 'Sustainable Agriculture',
                                        'Green Construction', 'Circular Economy', 'ESG Consulting', 'Electric Mobility',
                                        'Carbon Reduction', 'Recycling', 'Climate Technology', 'Sustainable Packaging',
                                        'Biodiversity', 'Green Finance', 'Other'
                                    ];
                                    $userAreas = is_array($user->sustainability_areas) ? $user->sustainability_areas : [];
                                @endphp
                                @foreach($sustainabilityAreasOptions as $option)
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="sustainability_areas[]" value="{{ $option }}" id="area_{{ Str::slug($option) }}" @checked(in_array($option, (array) old('sustainability_areas', $userAreas)))>
                                            <label class="form-check-label" for="area_{{ Str::slug($option) }}">{{ $option }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label d-block fw-semibold mb-2">What are you looking for through Greenpreneur?</label>
                            <div class="row g-2">
                                @php
                                    $greenpreneurGoalsOptions = [
                                        'Business Growth', 'Partnerships', 'Investors', 'Customers',
                                        'Government Connect', 'Knowledge Sharing', 'Technology Partners',
                                        'Global Expansion', 'Sustainability Learning'
                                    ];
                                    $userGoals = is_array($user->greenpreneur_goals) ? $user->greenpreneur_goals : [];
                                @endphp
                                @foreach($greenpreneurGoalsOptions as $option)
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="greenpreneur_goals[]" value="{{ $option }}" id="goal_{{ Str::slug($option) }}" @checked(in_array($option, (array) old('greenpreneur_goals', $userGoals)))>
                                            <label class="form-check-label" for="goal_{{ Str::slug($option) }}">{{ $option }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label d-block fw-semibold mb-2">Are you interested in:</label>
                            <div class="row g-2">
                                @php
                                    $interestsOptions = [
                                        'Speaking Opportunities', 'Panel Discussions', 'Mentoring', 'Exhibiting',
                                        'Sponsorship', 'Investment Opportunities', 'Greenpreneur Awards',
                                        'Coffee Table Book Feature', 'Impact Story'
                                    ];
                                    $userInterests = is_array($user->interests) ? $user->interests : [];
                                @endphp
                                @foreach($interestsOptions as $option)
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interests[]" value="{{ $option }}" id="interest_{{ Str::slug($option) }}" @checked(in_array($option, (array) old('interests', $userInterests)))>
                                            <label class="form-check-label" for="interest_{{ Str::slug($option) }}">{{ $option }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
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
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Introduced By (User ID)</label>
                            <input type="text" name="introduced_by" class="form-control" value="{{ old('introduced_by', $user->introduced_by) }}">
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
                            <label class="form-label fw-semibold" for="level1_category_id">Level 1 Category</label>
                            <select name="level1_category_id" id="level1_category_id" class="form-select js-no-searchable-select">
                                <option value="">Select level 1 category</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="level2_category_id">Level 2 Category</label>
                            <select name="level2_category_id" id="level2_category_id" class="form-select js-no-searchable-select" disabled>
                                <option value="">Select level 2 category</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="level3_category_id">Level 3 Category</label>
                            <select name="level3_category_id" id="level3_category_id" class="form-select js-no-searchable-select" disabled>
                                <option value="">Select level 3 category</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="level4_category_id">Level 4 Category</label>
                            <select name="level4_category_id" id="level4_category_id" class="form-select js-no-searchable-select" disabled>
                                <option value="">Select level 4 category</option>
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
                                                    <button
                                                        type="submit"
                                                        form="remove-circle-membership-{{ $membership->id }}"
                                                        class="btn btn-sm btn-outline-danger py-0 px-2"
                                                        onclick="return confirm('Remove this circle membership for this peer?');"
                                                    >
                                                        Remove
                                                    </button>
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
                                                    <div><strong>Level 1:</strong> {{ $selectedPath['level1']->name ?? '—' }}</div>
                                                    <div><strong>Level 2:</strong> {{ $selectedPath['level2']->name ?? '—' }}</div>
                                                    <div><strong>Level 3:</strong> {{ $selectedPath['level3']->name ?? '—' }}</div>
                                                    <div><strong>Level 4:</strong> {{ $selectedPath['level4']->name ?? '—' }}</div>
                                                </div>

                                                @if(($circleTree['categories'] ?? collect())->isEmpty())
                                                    <div class="text-muted small">—</div>
                                                @else
                                                    @foreach($circleTree['categories'] as $mainCategoryTree)
                                                        <div class="mb-2">
                                                            <span class="badge bg-light text-dark border">
                                                                Category: {{ $mainCategoryTree['node']->name }}
                                                            </span>

                                                            @if(($mainCategoryTree['children'] ?? collect())->isEmpty())
                                                                <div class="text-muted ms-2 small">—</div>
                                                            @else
                                                                <ul class="mb-0 small mt-1">
                                                                    @foreach($mainCategoryTree['children'] as $level2Tree)
                                                                        <li>
                                                                            {{ $level2Tree['node']->name }}
                                                                            @if(($level2Tree['children'] ?? collect())->isNotEmpty())
                                                                                <ul>
                                                                                    @foreach($level2Tree['children'] as $level3Tree)
                                                                                        <li>
                                                                                            {{ $level3Tree['node']->name }}
                                                                                            @if(($level3Tree['children'] ?? collect())->isNotEmpty())
                                                                                                <ul>
                                                                                                    @foreach($level3Tree['children'] as $level4Node)
                                                                                                        <li>{{ $level4Node->name }}</li>
                                                                                                    @endforeach
                                                                                                </ul>
                                                                                            @endif
                                                                                        </li>
                                                                                    @endforeach
                                                                                </ul>
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
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
                                <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong>Currently assigned role:</strong>
                                        <span>{{ $assignedAdminRoleNames }}</span>
                                    </div>
                                    <button type="submit"
                                            form="removeAdminRoleForm"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Remove the current admin role from this user?');">
                                        Remove Role
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
                                                   @checked(in_array($role->id, $currentRoleIds))
                                                   @disabled($hasAssignedAdminRole)>
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
                                        @disabled($hasAssignedAdminRole)
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
                                        @disabled($hasAssignedAdminRole)
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

                            @if ($hasAssignedAdminRole)
                                <div class="form-text text-muted">
                                    Remove the existing admin role to assign a new one.
                                </div>
                            @endif
                            
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
            </div>
        </form>
    </div>
</div>

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

document.addEventListener('DOMContentLoaded', function () {
    const roleCheckboxes = Array.from(document.querySelectorAll('input[name="role_ids[]"]'));
    const dedDistrictField = document.getElementById('dedDistrictField');
    const dedStateSelect = document.getElementById('dedStateId');
    const dedDistrictSelect = document.getElementById('dedDistrictId');
    const dedDistrictNameInput = document.getElementById('dedDistrictName');
    const dedStateNameInput = document.getElementById('dedStateName');
    const selectedDedDistrictName = @json((string) old('ded_district_name', $assignedDedDistrictName));
    const dedRoleFieldsLocked = @json((bool) $hasAssignedAdminRole);
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

            const setStatus = (text, isError = false) => {
                if (!status) return;
                status.textContent = text;
                status.classList.toggle('text-danger', isError);
            };

            changeBtn?.addEventListener('click', () => {
                if (existing) existing.classList.add('d-none');
                if (upload) upload.classList.remove('d-none');
                if (hiddenInput) hiddenInput.value = '';
                if (fileInput) fileInput.value = '';
                setStatus('Select a file to upload.');
            });

            fileInput?.addEventListener('change', async () => {
                const file = fileInput.files?.[0];
                if (!file) return;
                setStatus('Uploading...');

                const formData = new FormData();
                formData.append('file', file);

                try {
                    const response = await fetch(uploadUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
                    });

                    if (!response.ok) {
                        setStatus('Upload failed. Please try again.', true);
                        return;
                    }

                    const json = await response.json();
                    const fileId = json?.data?.id ?? json?.data?.[0]?.id;
                    if (!fileId) {
                        setStatus('Upload failed. Missing file id.', true);
                        return;
                    }

                    if (hiddenInput) hiddenInput.value = fileId;
                    if (viewLink) viewLink.href = `/api/v1/files/${fileId}`;

                    if (upload) upload.classList.add('d-none');
                    if (existing) existing.classList.remove('d-none');
                    setStatus('Upload successful.');
                } catch (e) {
                    setStatus('Upload failed. Please try again.', true);
                }
            });
        };

        setupUploader('profilePhoto');
        setupUploader('coverPhoto');

        const circleSelect = document.getElementById('additional_circle_id');
        const level1Select = document.getElementById('level1_category_id');
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
                if (selectedValue !== '' && String(selectedValue) === String(item.id)) {
                    option.selected = true;
                }
                selectEl.appendChild(option);
            });
            selectEl.disabled = (options || []).length === 0;
        };

        const getCircleData = () => {
            const circleId = circleSelect?.value || '';
            return circleCategoryOptionsByCircle[String(circleId)] || { level1: [], level2: [], level3: [], level4: [] };
        };

        const handleLevel1Change = (presetLevel2 = '') => {
            const data = getCircleData();
            const level1Id = level1Select?.value || '';
            const level2Options = (data.level2 || []).filter((item) => String(item.parent_id) === String(level1Id));
            fillSelect(level2Select, level2Options, 'Select level 2 category', presetLevel2);
            resetSelect(level3Select, 'Select level 3 category');
            resetSelect(level4Select, 'Select level 4 category');
        };

        const handleLevel2Change = (presetLevel3 = '') => {
            const data = getCircleData();
            const level2Id = level2Select?.value || '';
            const level3Options = (data.level3 || []).filter((item) => String(item.parent_id) === String(level2Id));
            fillSelect(level3Select, level3Options, 'Select level 3 category', presetLevel3);
            resetSelect(level4Select, 'Select level 4 category');
        };

        const handleLevel3Change = (presetLevel4 = '') => {
            const data = getCircleData();
            const level3Id = level3Select?.value || '';
            const level4Options = (data.level4 || []).filter((item) => String(item.parent_id) === String(level3Id));
            fillSelect(level4Select, level4Options, 'Select level 4 category', presetLevel4);
        };

        const handleCircleChange = () => {
            const data = getCircleData();
            fillSelect(level1Select, data.level1 || [], 'Select level 1 category', oldLevel1);
            resetSelect(level2Select, 'Select level 2 category');
            resetSelect(level3Select, 'Select level 3 category');
            resetSelect(level4Select, 'Select level 4 category');

            if (oldLevel1 && level1Select?.value) {
                handleLevel1Change(oldLevel2);
                if (oldLevel2 && level2Select?.value) {
                    handleLevel2Change(oldLevel3);
                    if (oldLevel3 && level3Select?.value) {
                        handleLevel3Change(oldLevel4);
                    }
                }
            } else if ((data.level1 || []).length === 1 && level1Select) {
                level1Select.value = String(data.level1[0].id);
                handleLevel1Change();
            }
        };

        circleSelect?.addEventListener('change', () => {
            resetSelect(level2Select, 'Select level 2 category');
            resetSelect(level3Select, 'Select level 3 category');
            resetSelect(level4Select, 'Select level 4 category');

            const data = getCircleData();
            fillSelect(level1Select, data.level1 || [], 'Select level 1 category');
            if ((data.level1 || []).length === 1 && level1Select) {
                level1Select.value = String(data.level1[0].id);
                handleLevel1Change();
            }
        });
        level1Select?.addEventListener('change', () => handleLevel1Change());
        level2Select?.addEventListener('change', () => handleLevel2Change());
        level3Select?.addEventListener('change', () => handleLevel3Change());

        handleCircleChange();
    });
</script>

@if($isReadOnly ?? false)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input, select, textarea, button').forEach(function(el) {
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
