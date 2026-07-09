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
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add Peer</h4>
        <p class="text-muted small mb-0">Create a new platform member</p>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Peers
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
                            <div id="profilePhotoExisting" class="d-none border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="profilePhotoPreview" src="#" alt="Profile preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 80px; object-fit: cover;">
                                    <div>
                                        <a href="#" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="profilePhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="profilePhotoUpload">
                                <input type="file" class="form-control" id="profilePhotoFile" accept="image/*">
                                <div class="form-text" id="profilePhotoStatus">Upload up to 10MB.</div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-semibold">Cover Photo</label>
                            <input type="hidden" name="cover_photo_file_id" id="coverPhotoFileId" value="{{ old('cover_photo_file_id') }}">
                            <div id="coverPhotoExisting" class="d-none border rounded p-3 bg-light mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="coverPhotoPreview" src="#" alt="Cover preview" class="rounded border shadow-sm" style="max-height: 80px; max-width: 140px; object-fit: cover;">
                                    <div>
                                        <a href="#" target="_blank" class="btn btn-outline-secondary btn-sm mb-1">View Image</a>
                                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-change-target="coverPhoto">Change</button>
                                    </div>
                                </div>
                            </div>
                            <div id="coverPhotoUpload">
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

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-primary" onclick="switchTab('business-tab')">
                            Next: Business Details <i class="bi bi-arrow-right ms-1"></i>
                        </button>
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
                                'interests' => null,
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
                        <button type="button" class="btn btn-primary" onclick="switchTab('membership-tab')">
                            Next: Membership & Coins <i class="bi bi-arrow-right ms-1"></i>
                        </button>
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
                                    'only_unity_peer' => 'Only Unity Peer',
                                    'Only Unity Peer' => 'Only Unity Peer',
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
                        <button type="button" class="btn btn-primary" onclick="switchTab('circles-tab')">
                            Next: Circles & Location <i class="bi bi-arrow-right ms-1"></i>
                        </button>
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
                    if (previewImg) previewImg.src = `/api/v1/files/${fileId}`;
                    if (viewLink) {
                        viewLink.href = `/api/v1/files/${fileId}`;
                        if (existing) {
                            existing.classList.remove('d-none');
                            existing.classList.add('d-block');
                        }
                    }

                    if (upload) upload.classList.add('d-none');
                    setStatus('Upload successful.');
                } catch (e) {
                    setStatus('Upload failed. Please try again.', true);
                }
            });
        };

        setupUploader('profilePhoto');
        setupUploader('coverPhoto');
    });
</script>
@endpush
