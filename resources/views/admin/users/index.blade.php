@extends('admin.layouts.app')

@section('title', 'Peers')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif


<div class="card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: 90px;">
                    @foreach ([10, 20, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="small text-muted">
                @if($users->total() > 0)
                    Records {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }}
                @else
                    No records found
                @endif
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <a href="{{ route('admin.users.import') }}" class="btn btn-outline-primary btn-sm">Import</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="exportCsvBtn">Export CSV</button>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">Add Peer</a>
        </div>
    </div>

    <div class="border rounded-3 bg-light-subtle p-3 mb-3">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
            <div class="flex-shrink-0">
                <div class="fw-semibold text-dark">Membership Approval</div>
                <div class="small text-muted">Select peers and approve their membership as Global Peer.</div>
            </div>
            <div class="d-flex flex-column flex-md-row align-items-md-end gap-2 gap-md-3 flex-grow-1 justify-content-xl-end">
                <div>
                    <label for="approvalMembershipStartsAt" class="form-label small text-muted mb-1">Membership Starts At</label>
                    <input id="approvalMembershipStartsAt" type="date" name="approval_membership_starts_at" class="form-control form-control-sm" value="{{ old('approval_membership_starts_at', '') }}">
                </div>
                <div>
                    <label for="approvalMembershipEndsAt" class="form-label small text-muted mb-1">Membership Ends At</label>
                    <input id="approvalMembershipEndsAt" type="date" name="approval_membership_ends_at" class="form-control form-control-sm" value="{{ old('approval_membership_ends_at', '') }}">
                </div>
                <button type="button" class="btn btn-success btn-sm" id="openApproveMembershipModal">
                    <i class="bi bi-check-circle me-1"></i>Approve Selected
                </button>
            </div>
        </div>
    </div>

    <form id="usersFiltersForm" method="GET" class="border rounded-3 p-3 mb-3 bg-white">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small text-muted" for="peerSearch">Search</label>
                <select id="peerSearch" name="q" class="form-select form-select-sm js-no-searchable-select">
                    <option value="">Peer, company, city</option>
                    @if($selectedUser)
                        <option value="{{ $selectedUser->id }}" selected>{{ $selectedUserLabel }}</option>
                    @endif
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small text-muted" for="circleFilter">Circle</label>
                <select id="circleFilter" name="circle_id" class="form-select form-select-sm admin-filter-dropdown">
                    <option value="all">All Circles</option>
                    @foreach($circles as $c)
                        <option value="{{ $c->id }}" @selected(($circleId ?? 'all') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label small text-muted" for="membershipFilter">Membership</label>
                <select id="membershipFilter" name="membership_status" class="form-select form-select-sm admin-filter-dropdown">
                    <option value="">All</option>
                    @foreach ($membershipStatuses as $status)
                        <option value="{{ $status }}" @selected(request('membership_status') === $status)>{{ $membershipStatusLabels[$status] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label small text-muted" for="startDateFilter">Start Date</label>
                <input id="startDateFilter" type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label small text-muted" for="endDateFilter">End Date</label>
                <input id="endDateFilter" type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small text-muted" for="joinedFilter">Date Filter</label>
                <select name="joined_filter" id="joinedFilter" class="form-select form-select-sm admin-filter-dropdown">
                    <option value="all" @selected(($filters['joined_filter'] ?? 'all') === 'all')>All Joined Dates</option>
                    <option value="last_month" @selected(($filters['joined_filter'] ?? 'all') === 'last_month')>Last Month</option>
                    <option value="last_week" @selected(($filters['joined_filter'] ?? 'all') === 'last_week')>Last Week</option>
                    <option value="yesterday" @selected(($filters['joined_filter'] ?? 'all') === 'yesterday')>Yesterday</option>
                    <option value="custom" @selected(($filters['joined_filter'] ?? 'all') === 'custom')>Custom Range</option>
                </select>
            </div>
            <div id="joinedCustomRange" @class(['col-12 col-md-6 col-xl-3', 'd-none' => ($filters['joined_filter'] ?? 'all') !== 'custom'])>
                <div class="row g-2">
                    <div class="col-6">
                        <label for="joinedFrom" class="form-label small text-muted">Joined From</label>
                        <input id="joinedFrom" type="date" name="joined_from" class="form-control form-control-sm" value="{{ request('joined_from', $filters['joined_from'] ?? '') }}" @disabled(($filters['joined_filter'] ?? 'all') !== 'custom')>
                    </div>
                    <div class="col-6">
                        <label for="joinedTo" class="form-label small text-muted">Joined To</label>
                        <input id="joinedTo" type="date" name="joined_to" class="form-control form-control-sm" value="{{ request('joined_to', $filters['joined_to'] ?? '') }}" @disabled(($filters['joined_filter'] ?? 'all') !== 'custom')>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
                <a class="btn btn-sm btn-outline-secondary flex-fill" id="resetFiltersBtn" href="{{ route('admin.users.index') }}">Reset</a>
            </div>
        </div>
    </form>
    <form id="exportCsvForm" method="POST" action="{{ route('admin.users.export.csv') }}" class="d-none">
        @csrf
        <input type="hidden" name="q" value="{{ $filters['search'] }}">
        <input type="hidden" name="membership_status" value="{{ $filters['membership_status'] ?? '' }}">
        <input type="hidden" name="circle_id" value="{{ $filters['circle_id'] ?? 'all' }}">

        <input type="hidden" name="joined_filter" value="{{ $filters['joined_filter'] ?? 'all' }}">
        <input type="hidden" name="joined_from" value="{{ $filters['joined_from'] ?? '' }}">
        <input type="hidden" name="joined_to" value="{{ $filters['joined_to'] ?? '' }}">
        <input type="hidden" name="approve_filter" value="{{ $filters['approve_filter'] ?? 'all' }}">
        <input type="hidden" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
        <input type="hidden" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" value="{{ $filters['dir'] }}">
    </form>
    <div class="table-responsive premium-table-card">
        <table class="table premium-table align-middle">
            <thead>
                <tr>
                    <th style="width: 40px; padding-left: 20px !important;">
                        <input type="checkbox" class="form-check-input" id="selectAllPeers">
                    </th>
                    <th>
                        <a href="{{ route('admin.users.index', array_merge(request()->except('approval_status'), ['sort' => 'display_name', 'dir' => $filters['sort'] === 'display_name' && $filters['dir'] === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Peer Name
                            @if ($filters['sort'] === 'display_name')
                                <i class="bi bi-arrow-{{ $filters['dir'] === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                            @endif
                        </a>
                    </th>
                    <th>Company Name</th>
                    <th>City</th>
                    <th>Circle</th>
                    <th>Phone</th>
                    <th>Membership</th>
                    <th>Membership Ends At</th>
                    <th>
                        <a href="{{ route('admin.users.index', array_merge(request()->except('approval_status'), ['sort' => 'coins_balance', 'dir' => $filters['sort'] === 'coins_balance' && $filters['dir'] === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Coins
                            @if ($filters['sort'] === 'coins_balance')
                                <i class="bi bi-arrow-{{ $filters['dir'] === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('admin.users.index', array_merge(request()->except('approval_status'), ['sort' => 'last_login_at', 'dir' => $filters['sort'] === 'last_login_at' && $filters['dir'] === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                            Last Login
                            @if ($filters['sort'] === 'last_login_at')
                                <i class="bi bi-arrow-{{ $filters['dir'] === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                            @endif
                        </a>
                    </th>
                    <th>Status</th>
                    <th class="text-end" style="padding-right: 20px !important;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $name = $user->name ?? trim((($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));
                        $avatar = $user->profile_photo_url ?? ($user->profile_photo_file_id ? url('/api/v1/files/' . $user->profile_photo_file_id) : null);
                        
                        // Parse values and strip out standard empty string placeholders
                        $rawCity = $user->city->name ?? $user->city ?? '';
                        if (is_string($rawCity)) {
                            $rawCity = trim($rawCity);
                            if (str_starts_with($rawCity, '{')) {
                                $decodedCity = json_decode($rawCity, true);
                                if (is_array($decodedCity)) {
                                    $cityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $rawCity;
                                } elseif (preg_match('/name:\s*([^,}]+)/', $rawCity, $matches)) {
                                    $cityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                } else {
                                    $cityName = $rawCity;
                                }
                            } else {
                                $cityName = $rawCity;
                            }
                        } elseif (is_array($rawCity)) {
                            $cityName = $rawCity['name'] ?? $rawCity['label'] ?? '';
                        } elseif (is_object($rawCity)) {
                            $cityName = $rawCity->name ?? $rawCity->label ?? '';
                        } else {
                            $cityName = $rawCity;
                        }
                        
                        if (in_array(strtolower(trim((string)$cityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                            $cityName = null;
                        }
                        
                        $company = $user->company_name ?? $user->company ?? $user->business_name ?? '';
                        if (in_array(strtolower(trim((string)$company)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                            $company = null;
                        }
                        
                        $userCircles = $user->circleMembers
                            ->map(fn($cm) => $cm->circle)
                            ->filter()
                            ->unique('id');

                        $statusValue = $user->status ?? 'active';
                        $isActive = $statusValue === 'active';
                        $detailsId = 'details-' . $user->id;
                        $canApproveMembership = $canEditUsers && in_array((string) $user->membership_status, ['free_peer', 'free_trial_peer'], true);
                        
                        // Pick membership class and labels
                        $membershipStatus = (string) ($user->membership_status ?? 'free_peer');
                        $membershipLabel = $membershipStatusLabels[$user->membership_status] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $membershipStatus));
                        $membershipBadgeClass = 'badge-membership-free';
                        if (in_array(strtolower(trim($membershipStatus)), ['only_unity_peer', 'unity_peer', 'only unity peer'], true)) {
                            $membershipBadgeClass = 'badge-membership-unity';
                        } elseif (in_array(strtolower(trim($membershipStatus)), ['circle_peer', 'circle peer'], true)) {
                            $membershipBadgeClass = 'badge-membership-circle';
                        }
                        
                        $gradientIndex = abs(crc32((string) $user->id)) % 5;
                    @endphp
                    <tr>
                        <td style="padding-left: 20px !important;">
                            <input type="checkbox" class="form-check-input peer-checkbox" value="{{ $user->id }}">
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="peer-avatar-wrapper">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" alt="{{ $name }}" class="peer-avatar-image">
                                    @else
                                        <div class="peer-avatar-placeholder bg-gradient-peer-{{ $gradientIndex }}">
                                            {{ strtoupper(substr($name !== '' ? $name : 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $name !== '' ? $name : '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($company)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-building text-muted small"></i>{{ $company }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($cityName)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-geo-alt text-muted small"></i>{{ $cityName }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($userCircles->isNotEmpty())
                                <div class="d-flex flex-column gap-1">
                                    @foreach ($userCircles as $circle)
                                        <span class="text-primary fw-semibold d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                            <i class="bi bi-people text-primary small"></i>{{ $circle->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->phone)
                                <span class="text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-telephone text-muted small"></i>{{ $user->phone }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="{{ $membershipBadgeClass }}">{{ $membershipLabel }}</span>
                        </td>
                        <td>
                            @if ($user->membership_ends_at)
                                <span class="text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.85rem;">
                                    <i class="bi bi-calendar3 text-muted small"></i>{{ $user->membership_ends_at->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold text-dark d-inline-flex align-items-center gap-1" style="font-size: 0.85rem;">
                                <i class="bi bi-coin text-warning"></i>{{ number_format($user->coins_balance ?? 0) }}
                            </span>
                        </td>
                        <td>
                            @if ($user->last_login_at)
                                <span class="text-secondary d-inline-flex align-items-center gap-1" style="font-size: 0.85rem;" title="{{ $user->last_login_at->format('Y-m-d H:i') }}">
                                    <i class="bi bi-clock text-muted small"></i>{{ $user->last_login_at->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge-status-active">
                                    <span class="status-pulse-dot"></span>Active
                                </span>
                            @else
                                <span class="badge-status-inactive">
                                    <span class="status-pulse-dot"></span>Inactive
                                </span>
                            @endif
                        </td>
                        <td class="text-end" style="padding-right: 20px !important;">
                            <div class="d-flex justify-content-end gap-2">
                                @if ($canEditUsers)
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-outline-secondary btn-action-custom" target="_blank" rel="noopener">
                                        <i class="bi bi-pencil"></i>Edit
                                    </a>
                                @else
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-secondary btn-action-custom" target="_blank" rel="noopener">
                                        <i class="bi bi-eye"></i>View Profile
                                    </a>
                                @endif
                                <button class="btn btn-outline-primary btn-action-custom btn-details-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailsId }}" aria-expanded="false" aria-controls="{{ $detailsId }}">
                                    Details<i class="bi bi-chevron-down details-chevron ms-1"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="collapse-row">
                        <td colspan="12" class="p-0 border-0">
                            <div class="collapse" id="{{ $detailsId }}">
                                <div class="p-3 bg-light border-top">
                                    @php
                                        // $userCircles was computed at the top of the loop
                                        $joinedCircleCategoryTrees = collect($joinedCircleCategoryTreesByUserId[(string) $user->id] ?? []);

                                        $fields = [
                                            ['label' => 'ID', 'value' => $user->id],
                                            ['label' => 'Email', 'value' => $user->email],
                                            ['label' => 'Phone', 'value' => $user->phone],
                                            ['label' => 'First Name', 'value' => $user->first_name],
                                            ['label' => 'Last Name', 'value' => $user->last_name],
                                            ['label' => 'Display Name', 'value' => $user->display_name],
                                            ['label' => 'Designation', 'value' => $user->designation],
                                            ['label' => 'Company Name', 'value' => $user->company_name],
                                            ['label' => 'Profile Photo URL', 'value' => $user->profile_photo_url],
                                            ['label' => 'Profile Photo File ID', 'value' => $user->profile_photo_file_id],
                                            ['label' => 'Cover Photo File ID', 'value' => $user->cover_photo_file_id],
                                            ['label' => 'Short Bio', 'value' => $user->short_bio],
                                            ['label' => 'Long Bio (HTML)', 'value' => $user->long_bio_html],
                                            ['label' => 'Industry Tags', 'value' => $user->industry_tags, 'type' => 'json'],
                                            ['label' => 'Business Type', 'value' => $user->business_type],
                                            ['label' => 'Turnover Range', 'value' => $user->turnover_range],
                                            ['label' => 'City ID', 'value' => $user->city_id],
                                            ['label' => 'City (string)', 'value' => $user->city],
                                            ['label' => 'Membership Status', 'value' => $user->membership_status],
                                            ['label' => 'Membership Ends At', 'value' => $user->membership_ends_at, 'type' => 'membership_date'],
                                            ['label' => 'Circles', 'value' => $userCircles, 'type' => 'user_circles'],
                                            ['label' => 'Zoho Customer ID', 'value' => $user->zoho_customer_id],
                                            ['label' => 'Zoho Subscription ID', 'value' => $user->zoho_subscription_id],
                                            ['label' => 'Zoho Plan Code', 'value' => $user->zoho_plan_code],
                                            ['label' => 'Zoho Last Invoice ID', 'value' => $user->zoho_last_invoice_id],
                                            ['label' => 'Membership Starts At', 'value' => $user->membership_starts_at, 'type' => 'membership_date'],
                                            ['label' => 'Last Payment At', 'value' => $user->last_payment_at, 'type' => 'membership_date'],
                                            ['label' => 'Coins Balance', 'value' => $user->coins_balance],
                                            ['label' => 'Total Life Impacted', 'value' => $user->life_impacted_count],
                                            ['label' => 'Medal Rank', 'value' => $user->coin_medal_rank],
                                            ['label' => 'Title', 'value' => $user->coin_milestone_title],
                                            ['label' => 'Meaning & Vibe', 'value' => $user->coin_milestone_meaning],
                                            ['label' => 'Introduced By', 'value' => $user->introduced_by],
                                            ['label' => 'Members Introduced Count', 'value' => $user->members_introduced_count],
                                            ['label' => 'Contribution Award Name', 'value' => $user->contribution_award_name],
                                            ['label' => 'Contribution Recognition', 'value' => $user->contribution_award_recognition],
                                            ['label' => 'Influencer Stars', 'value' => $user->influencer_stars],
                                            ['label' => 'Target Regions', 'value' => $user->target_regions, 'type' => 'json'],
                                            ['label' => 'Target Business Categories', 'value' => $user->target_business_categories, 'type' => 'json'],
                                            ['label' => 'Hobbies / Interests', 'value' => $user->hobbies_interests, 'type' => 'json'],
                                            ['label' => 'Leadership Roles', 'value' => $user->leadership_roles, 'type' => 'json'],
                                            ['label' => 'Is Sponsored Member', 'value' => $user->is_sponsored_member, 'type' => 'bool'],
                                            ['label' => 'Public Profile Slug', 'value' => $user->public_profile_slug],
                                            ['label' => 'Special Recognitions', 'value' => $user->special_recognitions, 'type' => 'json'],
                                            ['label' => 'Gender', 'value' => $user->gender],
                                            ['label' => 'Date of Birth', 'value' => $user->dob, 'type' => 'date'],
                                            ['label' => 'Experience (years)', 'value' => $user->experience_years],
                                            ['label' => 'Experience Summary', 'value' => $user->experience_summary],
                                            ['label' => 'Skills', 'value' => $user->skills, 'type' => 'json'],
                                            ['label' => 'Interests', 'value' => $user->interests, 'type' => 'json'],
                                            ['label' => 'GDPR Deleted At', 'value' => $user->gdpr_deleted_at, 'type' => 'date'],
                                            ['label' => 'Anonymized At', 'value' => $user->anonymized_at, 'type' => 'date'],
                                            ['label' => 'Is GDPR Exported', 'value' => $user->is_gdpr_exported, 'type' => 'bool'],
                                            ['label' => 'Last Login', 'value' => $user->last_login_at, 'type' => 'date'],
                                            ['label' => 'Created At', 'value' => $user->created_at, 'type' => 'date'],
                                            ['label' => 'Updated At', 'value' => $user->updated_at, 'type' => 'date'],
                                            ['label' => 'Deleted At', 'value' => $user->deleted_at, 'type' => 'date'],
                                        ];

                                        $chunks = array_chunk($fields, (int) ceil(count($fields) / 3));
                                        $renderValue = function ($value, $type = 'text') {
                                            $normalizeText = static function ($input) {
                                                if (! is_string($input)) {
                                                    return $input;
                                                }

                                                $trimmed = trim($input);

                                                return $trimmed === '' ? null : $trimmed;
                                            };

                                            if ($type === 'bool') {
                                                $class = $value ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary';
                                                $label = $value ? 'Yes' : 'No';
                                                return '<span class="badge ' . $class . '">' . $label . '</span>';
                                            }

                                            if ($type === 'date') {
                                                $value = $normalizeText($value);

                                                if (! $value) {
                                                    return '—';
                                                }

                                                $isDate = $value instanceof \DateTimeInterface;
                                                $formatted = $isDate ? $value->format('Y-m-d H:i') : (string) $value;
                                                $raw = $isDate && method_exists($value, 'toDateTimeString') ? $value->toDateTimeString() : (string) $value;
                                                return e($formatted) . ' <span class="text-muted small">(' . e($raw) . ')</span>';
                                            }

                                            if ($type === 'membership_date') {
                                                $value = $normalizeText($value);

                                                if (! $value) {
                                                    return '—';
                                                }

                                                return e($value instanceof \DateTimeInterface ? $value->format('d-m-Y H:i') : (string) $value);
                                            }

                                            if ($type === 'json') {
                                                if (is_null($value)) {
                                                    return '—';
                                                }

                                                if (is_array($value) && $value !== []) {
                                                    $isAssoc = array_keys($value) !== range(0, count($value) - 1);
                                                    if ($isAssoc) {
                                                        $rendered = collect($value)->map(fn ($v, $k) => $k . ': ' . $v)->implode(', ');
                                                    } else {
                                                        $rendered = implode(', ', $value);
                                                    }
                                                    return e($rendered);
                                                }

                                                return '—';
                                            }

                                            $value = $normalizeText($value);

                                            if ($value === null) {
                                                return '—';
                                            }

                                            return e((string) $value);
                                        };
                                    @endphp
                                    <div class="row g-3">
                                        @foreach ($chunks as $chunk)
                                            <div class="col-md-4">
                                                <table class="table table-sm mb-0">
                                                    @foreach ($chunk as $field)
                                                        <tr>
                                                            <th class="w-50 text-muted">{{ $field['label'] }}</th>
                                                            <td class="text-break">
                                                                @if (($field['type'] ?? null) === 'user_circles')
                                                                    <div class="d-flex flex-column gap-2">
                                                                        @forelse ($field['value'] as $circle)
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <span>{{ $circle->name }}</span>
                                                                                <a href="{{ route('admin.circles.edit', $circle->id) }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size: 0.75rem;">View</a>
                                                                            </div>
                                                                        @empty
                                                                            <span class="text-muted">—</span>
                                                                        @endforelse
                                                                    </div>
                                                                @else
                                                                    {!! $renderValue($field['value'], $field['type'] ?? 'text') !!}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-3">
                                        <h6 class="mb-2">Joined Circle Categories</h6>
                                        @php
                                            $registeredMainBusinessCategory = $user->mainBusinessCategory;
                                            $registeredBusinessCategory = $user->businessCategory;
                                            $hasRegisteredBusinessCategory = $registeredMainBusinessCategory || $registeredBusinessCategory;
                                        @endphp
                                        @if($joinedCircleCategoryTrees->isEmpty() && ! $hasRegisteredBusinessCategory)
                                            <div class="text-muted">—</div>
                                        @else
                                            @if($hasRegisteredBusinessCategory)
                                                <div class="border rounded p-3 bg-light-subtle mb-3">
                                                    <div class="fw-semibold mb-2">Registered Business Category</div>
                                                    <div class="small">
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
                                                            <div class="fw-semibold mb-2">
                                                                Joined Circle: {{ $circleTree['circle']?->name ?: ($circleTree['membership']->circle?->name ?? '—') }}
                                                            </div>

                                                            @if(($circleTree['categories'] ?? collect())->isEmpty())
                                                                <div class="text-muted">—</div>
                                                            @else
                                                                @foreach($circleTree['categories'] as $mainCategoryTree)
                                                                    <div class="mb-0">
                                                                        <span class="badge bg-light text-dark border mb-2">
                                                                            Category: {{ $mainCategoryTree['node']->name }}
                                                                        </span>
                                                                        @if(($mainCategoryTree['children'] ?? collect())->isNotEmpty())
                                                                            <ul class="mb-0">
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
                                    @include('admin.users.partials.membership_welcome_email_card', [
                                        'user' => $user,
                                        'showSendButton' => $canEditUsers,
                                        'cardClass' => 'mt-3 border-0 shadow-sm',
                                        'headerClass' => 'bg-white',
                                        'bodyClass' => '',
                                        'sendButtonClass' => 'btn btn-outline-primary btn-sm',
                                    ])
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
        <div>
            {{ $users->appends(request()->except('approval_status'))->links() }}
        </div>
        <div class="small text-muted">
            @if($users->total() > 0)
                Showing {{ $users->firstItem() }}-{{ $users->lastItem() }} of {{ $users->total() }} records
            @else
                No records
            @endif
        </div>
    </div>
</div>


<form id="bulkApproveMembershipDatesForm" method="POST" action="{{ route('admin.users.bulk-approve-membership') }}">
    @csrf
    <div class="modal fade" id="approveMembershipDatesModal" tabindex="-1" aria-labelledby="approveMembershipDatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="approveMembershipDatesModalLabel">Approve Selected Peers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="alert alert-success-subtle border-success-subtle mb-3">
                        <div class="fw-semibold">Selected peers: <span id="selectedPeersCount">0</span></div>
                        <div class="small text-muted">Membership Upgrade: <strong>Global Peer</strong></div>
                    </div>
                    <div class="border rounded-3 p-3 bg-light-subtle mb-3">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Membership Starts At:</span>
                            <strong class="text-end" id="modalMembershipStartsAtText">—</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Membership Ends At:</span>
                            <strong class="text-end" id="modalMembershipEndsAtText">—</strong>
                        </div>
                    </div>
                    <p class="mb-0">Are you sure you want to approve the selected peers?</p>
                    <input type="hidden" name="membership_starts_at" id="modalMembershipStartsAt">
                    <input type="hidden" name="membership_ends_at" id="modalMembershipEndsAt">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const $peerSearch = jQuery('#peerSearch');
        if ($peerSearch.length && jQuery.fn.select2) {
            $peerSearch.select2({
                width: '100%',
                placeholder: 'Peer, company, city',
                allowClear: true,
                containerCssClass: 'admin-filter-dropdown-container',
                dropdownCssClass: 'admin-filter-dropdown-menu',
                ajax: {
                    url: '/admin/users/search',
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
                language: {
                    noResults: function () {
                        return "No peers found.";
                    }
                }
            });
        }

        const selectAll = document.getElementById('selectAllPeers');
        const perPage = document.getElementById('perPage');
        const filterForm = document.getElementById('usersFiltersForm');
        const exportBtn = document.getElementById('exportCsvBtn');
        const exportForm = document.getElementById('exportCsvForm');
        const joinedFilter = document.getElementById('joinedFilter');
        const joinedCustomRange = document.getElementById('joinedCustomRange');
        const resetFiltersBtn = document.getElementById('resetFiltersBtn');
        const approveSelectedPeersBtn = document.getElementById('openApproveMembershipModal');
        const bulkApproveDatesForm = document.getElementById('bulkApproveMembershipDatesForm');
        const approveMembershipDatesModal = document.getElementById('approveMembershipDatesModal');
        const selectedCountEl = document.getElementById('selectedPeersCount');
        const membershipStartDate = document.getElementById('approvalMembershipStartsAt');
        const membershipEndDate = document.getElementById('approvalMembershipEndsAt');
        const modalMembershipStartsAt = document.getElementById('modalMembershipStartsAt');
        const modalMembershipEndsAt = document.getElementById('modalMembershipEndsAt');
        const modalMembershipStartsAtText = document.getElementById('modalMembershipStartsAtText');
        const modalMembershipEndsAtText = document.getElementById('modalMembershipEndsAtText');
        const modal = approveMembershipDatesModal && window.bootstrap ? new window.bootstrap.Modal(approveMembershipDatesModal) : null;
        const peerCheckboxes = () => Array.from(document.querySelectorAll('.peer-checkbox'));
        const selectedPeerIds = () => peerCheckboxes().filter(cb => cb.checked).map(cb => cb.value).filter(Boolean);
        const updateSelectedCount = () => {
            const selected = selectedPeerIds();
            if (selectedCountEl) selectedCountEl.textContent = selected.length;
            if (selectAll) {
                const boxes = peerCheckboxes();
                selectAll.checked = boxes.length > 0 && boxes.every(cb => cb.checked);
                selectAll.indeterminate = boxes.some(cb => cb.checked) && !selectAll.checked;
            }
            return selected.length;
        };
        const appendSelectedPeerInputs = (form) => {
            if (!form) return false;
            form.querySelectorAll('input[name="user_ids[]"]').forEach(el => el.remove());
            const selected = selectedPeerIds();
            if (selected.length === 0) {
                alert('Please select at least one peer.');
                return false;
            }
            selected.forEach(id => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'user_ids[]';
                hidden.value = id;
                form.appendChild(hidden);
            });
            return true;
        };
        const submitFilters = (form) => {
            const params = new URLSearchParams(window.location.search);
            
            form.querySelectorAll('input:disabled, select:disabled').forEach(input => {
                if (input.name) {
                    params.delete(input.name);
                }
            });

            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                if (value === '') {
                    params.delete(key);
                } else {
                    params.set(key, value);
                }
            }
            params.delete('page');
            params.delete('approval_status');
            const query = params.toString();
            window.location = query ? `${window.location.pathname}?${query}` : window.location.pathname;
        };
        function formatDateValue(date) {
            return date.toISOString().slice(0, 10);
        }

        function getTodayDateValue() {
            const today = new Date();
            return formatDateValue(today);
        }

        function addOneYear(dateValue) {
            const date = new Date(`${dateValue}T00:00:00`);
            date.setFullYear(date.getFullYear() + 1);
            return formatDateValue(date);
        }

        function formatDisplayDate(dateValue) {
            if (!dateValue) return '—';

            const date = new Date(`${dateValue}T00:00:00`);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }).replace(/ /g, ' ');
        }

        resetFiltersBtn?.addEventListener('click', () => {
            if (membershipStartDate) {
                membershipStartDate.value = '';
            }

            if (membershipEndDate) {
                membershipEndDate.value = '';
            }

            $peerSearch.val(null).trigger('change');
        });

        selectAll?.addEventListener('change', () => {
            peerCheckboxes().forEach(cb => cb.checked = selectAll.checked);
            updateSelectedCount();
        });
        peerCheckboxes().forEach(cb => cb.addEventListener('change', updateSelectedCount));
        updateSelectedCount();

        approveSelectedPeersBtn?.addEventListener('click', () => {
            const selectedCount = updateSelectedCount();
            if (selectedCount === 0) {
                alert('Please select at least one peer.');
                return;
            }

            let startsAt = membershipStartDate?.value || '';
            let endsAt = membershipEndDate?.value || '';

            if (!startsAt) {
                startsAt = getTodayDateValue();
            }

            if (!endsAt) {
                endsAt = addOneYear(startsAt);
            }

            if (endsAt < startsAt) {
                alert('Membership Ends At must be same or after Membership Starts At.');
                return;
            }

            if (!appendSelectedPeerInputs(bulkApproveDatesForm)) {
                return;
            }

            if (modalMembershipStartsAt) modalMembershipStartsAt.value = startsAt;
            if (modalMembershipEndsAt) modalMembershipEndsAt.value = endsAt;

            if (modalMembershipStartsAtText) {
                modalMembershipStartsAtText.textContent = formatDisplayDate(startsAt);
            }
            if (modalMembershipEndsAtText) {
                modalMembershipEndsAtText.textContent = formatDisplayDate(endsAt);
            }

            modal?.show();
        });

        if (perPage) {
            perPage.addEventListener('change', () => {
                const params = new URLSearchParams(window.location.search);
                params.set('per_page', perPage.value);
                params.delete('page');
                params.delete('approval_status');
                window.location = `${window.location.pathname}?${params.toString()}`;
            });
        }

        filterForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            submitFilters(filterForm);
        });

        const toggleJoinedDateRange = () => {
            if (!joinedCustomRange || !joinedFilter) return;
            const isCustom = joinedFilter.value === 'custom';
            joinedCustomRange.classList.toggle('d-none', !isCustom);
            joinedCustomRange.querySelectorAll('input').forEach((input) => {
                input.disabled = !isCustom;
            });
        };

        joinedFilter?.addEventListener('change', toggleJoinedDateRange);
        toggleJoinedDateRange();

        exportBtn?.addEventListener('click', () => {
            if (!exportForm) return;
            exportForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
            selectedPeerIds().forEach(id => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = id;
                exportForm.appendChild(hidden);
            });
            exportForm.submit();
        });

        bulkApproveDatesForm?.addEventListener('submit', (e) => {
            if (!appendSelectedPeerInputs(bulkApproveDatesForm)) {
                e.preventDefault();
                return;
            }

            const startsAt = modalMembershipStartsAt?.value || membershipStartDate?.value || '';
            const endsAt = modalMembershipEndsAt?.value || membershipEndDate?.value || '';
            if (startsAt && endsAt && endsAt < startsAt) {
                e.preventDefault();
                alert('Membership Ends At must be same or after Membership Starts At.');
            }
        });

        const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
@endsection
