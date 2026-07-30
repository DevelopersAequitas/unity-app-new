@extends('admin.layouts.app')

@section('title', 'DED Master Command Center')

@section('content')
<style>
    .kpi-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 12px;
        transition: all 0.25s ease-in-out;
        border: 1px solid rgba(0,0,0,0.06);
        cursor: pointer;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
        border-color: #0d6efd;
    }
    .text-primary-gradient {
        background: linear-gradient(45deg, #0d6efd, #0dcaf0);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .activity-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .bg-referral { background-color: #0d6efd; }
    .bg-meeting { background-color: #198754; }
    .bg-deal { background-color: #ffc107; }
    .bg-circle_join { background-color: #6f42c1; }
    
    .quick-finder-tab {
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s;
    }
    .quick-finder-tab.active {
        background-color: #0d6efd;
        color: #ffffff;
    }
</style>

<div class="card border-0 mb-4 shadow-sm position-relative overflow-hidden" style="background: linear-gradient(135deg, #F5FFFD 0%, #F7FFF9 50%, #F4FFFF 100%); border-radius: 16px; min-height: 160px; display: flex; align-items: center; border: 1px solid rgba(16, 185, 129, 0.08) !important;">
    <div class="card-body p-4 w-100 position-relative" style="z-index: 1;">
        <div class="d-flex align-items-center" style="gap: 32px;">
            <!-- Left Circular Icon -->
            <div class="d-none d-sm-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; min-width: 70px; min-height: 70px; background-color: #E6F8F5; border-radius: 50%; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.08) !important; font-size: 2.2rem;">
                <i class="bi bi-person-circle text-emerald-600 admin-icon" aria-hidden="true"></i>
            </div>
            <!-- Text Content -->
            <div class="flex-grow-1">
                <span class="text-secondary small fw-medium d-block mb-1" style="font-size: 0.9rem; color: #475569 !important;">Welcome back,</span>
                <h2 class="fw-bold mb-1" style="font-size: 32px; color: #0f172a; line-height: 1.15; font-family: 'Plus Jakarta Sans', sans-serif;">
                    {{ Auth::guard('admin')->user()?->name ?? 'District Executive Director' }}
                </h2>
                <div class="fw-semibold mb-1" style="font-size: 0.95rem; color: #10b981 !important;">
                    District Executive Director Dashboard @if($districtName) &bull; {{ $districtName }} District @endif
                </div>
                <p class="mb-0 text-muted" style="font-size: 0.85rem; color: #64748b !important;">
                    Manage your district activities, members, referrals and leadership from one place.
                </p>
            </div>
        </div>
    </div>
    <!-- Right side decorative pattern -->
    <div class="position-absolute d-none d-md-block" style="right: 32px; top: 50%; transform: translateY(-50%); pointer-events: none;">
        <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.12;">
            <circle cx="6" cy="6" r="2.5" fill="#10b981"/>
            <circle cx="22" cy="6" r="2.5" fill="#10b981"/>
            <circle cx="38" cy="6" r="2.5" fill="#10b981"/>
            <circle cx="54" cy="6" r="2.5" fill="#10b981"/>
            <circle cx="6" cy="22" r="2.5" fill="#10b981"/>
            <circle cx="22" cy="22" r="2.5" fill="#10b981"/>
            <circle cx="38" cy="22" r="2.5" fill="#10b981"/>
            <circle cx="54" cy="22" r="2.5" fill="#10b981"/>
            <circle cx="6" cy="38" r="2.5" fill="#10b981"/>
            <circle cx="22" cy="38" r="2.5" fill="#10b981"/>
            <circle cx="38" cy="38" r="2.5" fill="#10b981"/>
            <circle cx="54" cy="38" r="2.5" fill="#10b981"/>
            <circle cx="6" cy="54" r="2.5" fill="#10b981"/>
            <circle cx="22" cy="54" r="2.5" fill="#10b981"/>
            <circle cx="38" cy="54" r="2.5" fill="#10b981"/>
            <circle cx="54" cy="54" r="2.5" fill="#10b981"/>
        </svg>
    </div>
</div>

@if (! $districtName)
    <div class="alert alert-warning">No district assigned. Please contact Global Admin.</div>
@else
    @php
        $overview = $dashboardData['master_overview'] ?? [];
        $health = $dashboardData['health_score'] ?? [];
        $leadership = $dashboardData['leadership_overview'] ?? [];
        $breakdown = $dashboardData['role_breakdown'] ?? [];
        $circles = $dashboardData['circle_overview'] ?? [];
        $pending = $dashboardData['pending_requests'] ?? [];
        $feed = $dashboardData['activity_feed'] ?? [];
        $quickFinder = $dashboardData['leadership_quick_finder'] ?? [];
    @endphp

    <!-- Circle Filter Section -->
    <div class="card p-3 mb-4 shadow-sm border-0">
        <form method="GET" action="{{ route('admin.ded.dashboard') }}" class="row g-2 align-items-end">
            <div class="col-md-6 col-xl-4">
                <label for="dedDashboardCircleFilter" class="form-label small text-muted mb-1 fw-bold">Circle Scope Filter</label>
                <select id="dedDashboardCircleFilter" name="circle_id" class="form-select">
                    <option value="all" @selected(($selectedCircleId ?? '') === '')>District-Wide Overview</option>
                    @foreach (($districtCircles ?? collect()) as $circle)
                        <option value="{{ $circle->id }}" @selected(($selectedCircleId ?? '') === $circle->id)>
                            {{ $circle->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary px-4">Apply Filter</button>
            </div>
            @if (($selectedCircleId ?? '') !== '')
                <div class="col-md-auto">
                    <a href="{{ route('admin.ded.dashboard') }}" class="btn btn-outline-secondary">Clear</a>
                </div>
                <div class="col-md-auto text-success small fw-medium">
                    <i class="bi bi-info-circle-fill"></i> Scoped to circle: {{ $selectedCircle?->name ?? 'selected circle' }}.
                </div>
            @else
                <div class="col-md-auto text-muted small">Showing district-wide statistics.</div>
            @endif
        </form>
    </div>

    <!-- Health Scores Section (Section 8) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <a href="{{ route('admin.ded.dashboard.health.active-members', ['circle_id' => $selectedCircleId ?: 'all']) }}" class="card p-3 border-0 shadow-sm text-center text-decoration-none kpi-card h-100 d-block">
                <span class="text-muted small fw-bold">Active Members</span>
                <h3 class="my-2 fw-bold text-success">{{ $health['active_members_pct'] ?? 0 }}%</h3>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $health['active_members_pct'] ?? 0 }}%"></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="{{ route('admin.ded.dashboard.health.leadership-spots', ['circle_id' => $selectedCircleId ?: 'all']) }}" class="card p-3 border-0 shadow-sm text-center text-decoration-none kpi-card h-100 d-block">
                <span class="text-muted small fw-bold">Leadership Spots Filled</span>
                <h3 class="my-2 fw-bold text-primary">{{ $health['leadership_filled_pct'] ?? 0 }}%</h3>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $health['leadership_filled_pct'] ?? 0 }}%"></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="{{ route('admin.ded.dashboard.health.membership-conversion', ['circle_id' => $selectedCircleId ?: 'all']) }}" class="card p-3 border-0 shadow-sm text-center text-decoration-none kpi-card h-100 d-block">
                <span class="text-muted small fw-bold">Membership Conversion</span>
                <h3 class="my-2 fw-bold text-info">{{ $health['membership_conversion_pct'] ?? 0 }}%</h3>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $health['membership_conversion_pct'] ?? 0 }}%"></div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="{{ route('admin.ded.dashboard.health.referral-activity', ['circle_id' => $selectedCircleId ?: 'all']) }}" class="card p-3 border-0 shadow-sm text-center text-decoration-none kpi-card h-100 d-block">
                <span class="text-muted small fw-bold">Referral Activity (30d)</span>
                <h3 class="my-2 fw-bold text-warning">{{ $health['referral_activity_pct'] ?? 0 }}%</h3>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $health['referral_activity_pct'] ?? 0 }}%"></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Master Overview (Section 1) -->
    <div class="row g-3 mb-4">
        @php
            $kpis = [
                'total_members' => ['title' => 'Total Peers', 'route' => 'admin.users.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_circles' => ['title' => 'Total Circles', 'route' => 'admin.circles.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_industries' => ['title' => 'Total Industries', 'route' => 'admin.ded.dashboard.industries', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_revenue' => ['title' => 'Membership Subscription Revenue', 'route' => null, 'params' => [], 'color' => 'text-success', 'format' => 'currency'],
                'total_lives_impacted' => ['title' => 'Lives Impacted', 'route' => 'admin.life-impact.index', 'params' => [], 'color' => 'text-info', 'format' => 'number'],
                'upcoming_events' => ['title' => 'Upcoming Events', 'route' => 'admin.events.index', 'params' => [], 'color' => 'text-primary', 'format' => 'number'],
                'pending_approvals' => ['title' => 'Pending Approvals', 'route' => 'admin.circle-joining-requests.index', 'params' => [], 'color' => 'text-danger', 'format' => 'number'],
                'pending_payments' => ['title' => 'Pending Payments', 'route' => 'admin.circle-joining-requests.index', 'params' => ['status' => 'pending_circle_fee'], 'color' => 'text-warning', 'format' => 'number'],
                'total_coins' => ['title' => 'Coins Earned', 'route' => 'admin.coins.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_meetings' => ['title' => 'P2P Meetings', 'route' => 'admin.activities.p2p-meetings.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_deals' => ['title' => 'Business Deals', 'route' => 'admin.activities.business-deals.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_testimonials' => ['title' => 'Testimonials', 'route' => 'admin.activities.testimonials.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_requirements' => ['title' => 'Requirements', 'route' => 'admin.activities.requirements.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
                'total_referrals' => ['title' => 'Referrals', 'route' => 'admin.activities.referrals.index', 'params' => [], 'color' => 'text-dark', 'format' => 'number'],
            ];
        @endphp

        @foreach ($kpis as $key => $meta)
            @php
                $item = $overview[$key] ?? ['value' => 0, 'trend' => ''];
                $val = $item['value'] ?? 0;
                $trend = $item['trend'] ?? '';
                $isNeg = str_contains($trend, '↓');
                $isPos = str_contains($trend, '↑');
                $trendClass = $isNeg ? 'text-danger' : ($isPos ? 'text-success' : 'text-muted');
                
                $routeParams = $meta['params'];
                if ($selectedCircleId) {
                    $routeParams['circle_id'] = $selectedCircleId;
                }
                $href = $meta['route'] ? route($meta['route'], $routeParams) : '#';
            @endphp
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ $href }}" class="kpi-card p-3 d-block text-decoration-none text-reset shadow-sm h-100">
                    <p class="text-muted mb-1 small fw-bold">{{ $meta['title'] }}</p>
                    <h2 class="mb-0 fw-bold {{ $meta['color'] }}">
                        @if ($meta['format'] === 'currency')
                            ₹{{ number_format($val) }}
                        @else
                            {{ number_format($val) }}
                        @endif
                    </h2>
                    @if ($trend !== '')
                        <div class="small {{ $trendClass }} mt-1 fw-semibold">{{ $trend }}</div>
                    @endif
                </a>
            </div>
        @endforeach
    </div>

    <!-- Leadership Summary (Section 2) -->
    <h5 class="fw-bold mb-3">District Leadership Overview</h5>
    <div class="row g-3 mb-4">
        @php
            $leaders = [
                'industry_director' => ['title' => 'Industry Directors', 'count' => $leadership['industry_directors']['count'] ?? 0],
                'founder' => ['title' => 'Circle Founders', 'count' => $leadership['circle_founders']['count'] ?? 0],
                'director' => ['title' => 'Circle Directors', 'count' => $leadership['circle_direct']['count'] ?? 0],
                'chair' => ['title' => 'Chairs', 'count' => $leadership['leadership_team']['breakdown']['chair'] ?? 0],
                'vice_chair' => ['title' => 'Vice Chairs', 'count' => $leadership['leadership_team']['breakdown']['vice_chair'] ?? 0],
                'secretary' => ['title' => 'Secretaries', 'count' => $leadership['leadership_team']['breakdown']['secretary'] ?? 0],
                'member' => ['title' => 'Members', 'count' => $leadership['members']['count'] ?? 0],
            ];
        @endphp

        @foreach ($leaders as $roleKey => $meta)
            @php
                $routeParams = ['role' => $roleKey];
                if ($selectedCircleId) {
                    $routeParams['circle_id'] = $selectedCircleId;
                }
            @endphp
            <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                <a href="{{ route('admin.ded.dashboard.leadership', $routeParams) }}" class="kpi-card p-3 d-block text-decoration-none text-reset shadow-sm h-100">
                    <p class="text-muted mb-1 small fw-bold">{{ $meta['title'] }}</p>
                    <h3 class="mb-2 fw-bold text-dark">{{ number_format($meta['count']) }}</h3>
                    <div class="text-primary small fw-semibold">View Details →</div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <!-- Left Side: Table Lists -->
        <div class="col-12 col-xl-8">

            <!-- Section 9: Recent District Activity Feed -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Recent District Activity Feed</h5>
                <div class="list-group list-group-flush">
                    @forelse ($feed as $item)
                        <div class="list-group-item px-0 py-3 d-flex align-items-start gap-3 border-light">
                            <span class="activity-indicator bg-{{ $item['type'] }} mt-2"></span>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1 fw-bold">{{ $item['title'] }}</h6>
                                    <small class="text-muted">{{ Carbon\Carbon::parse($item['timestamp'])->diffForHumans() }}</small>
                                </div>
                                <p class="mb-0 text-muted small">{{ $item['description'] }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted py-3 text-center">No recent activities found for this district.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Side: Leadership Finder & Breakdown -->
        <div class="col-12 col-xl-4">
            <!-- Section 7: Action Center -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Action Center</h5>
                <div class="d-flex flex-column gap-2">
                    <a href="{{ route('admin.circle-joining-requests.index') }}" class="d-flex justify-content-between align-items-center p-3 rounded border text-decoration-none text-reset bg-light hover-bg">
                        <span class="fw-medium text-danger">Pending Join Requests</span>
                        <span class="badge bg-danger">{{ $pending['circle_joining_requests'] ?? 0 }}</span>
                    </a>
                    <a href="#" class="d-flex justify-content-between align-items-center p-3 rounded border text-decoration-none text-reset bg-light hover-bg">
                        <span class="fw-medium text-warning">Pending Coin Claims</span>
                        <span class="badge bg-warning">{{ $pending['coin_claims'] ?? 0 }}</span>
                    </a>
                    <a href="#" class="d-flex justify-content-between align-items-center p-3 rounded border text-decoration-none text-reset bg-light hover-bg">
                        <span class="fw-medium text-primary">Pending Impact Submissions</span>
                        <span class="badge bg-primary">{{ $pending['pending_impacts'] ?? 0 }}</span>
                    </a>
                    <a href="#" class="d-flex justify-content-between align-items-center p-3 rounded border text-decoration-none text-reset bg-light hover-bg">
                        <span class="fw-medium text-info">Pending Event Requests</span>
                        <span class="badge bg-info">{{ $pending['event_joining_requests'] ?? 0 }}</span>
                    </a>
                </div>
            </div>

            <!-- Section 3: Role Breakdown Chart / List -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Role Distribution</h5>
                <div class="d-flex flex-column gap-3">
                    @foreach ($breakdown as $item)
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                                <span>{{ $item['role'] }}</span>
                                <span class="fw-bold">{{ $item['count'] }} ({{ $item['percentage'] }}%)</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $item['percentage'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Section 6: Leadership Quick Finder -->
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold mb-3">Leadership Quick Finder</h5>
                
                <nav class="nav nav-pills flex-column gap-1 mb-3" id="quick-finder-tabs" role="tablist">
                    <button class="nav-link active text-start py-2" id="nav-founders-tab" data-bs-toggle="tab" data-bs-target="#nav-founders" type="button" role="tab">Circle Founders</button>
                    <button class="nav-link text-start py-2" id="nav-directors-tab" data-bs-toggle="tab" data-bs-target="#nav-directors" type="button" role="tab">Circle Directors</button>
                    <button class="nav-link text-start py-2" id="nav-ind-directors-tab" data-bs-toggle="tab" data-bs-target="#nav-ind-directors" type="button" role="tab">Industry Directors</button>
                    <button class="nav-link text-start py-2" id="nav-chairs-tab" data-bs-toggle="tab" data-bs-target="#nav-chairs" type="button" role="tab">Chairs & VC</button>
                </nav>
                
                <div class="tab-content" id="quick-finder-content">
                    <!-- Circle Founders Tab -->
                    <div class="tab-pane fade show active" id="nav-founders" role="tabpanel">
                        <div class="list-group list-group-flush">
                            @forelse ($quickFinder['circle_founders'] as $founder)
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="fw-bold text-dark">
                                        <a href="#" class="text-primary text-decoration-none" onclick="event.preventDefault(); openActivityPeerModal('{{ $founder['id'] }}', event);">
                                            {{ $founder['name'] }}
                                        </a>
                                    </div>
                                    <div class="text-muted small">{{ $founder['company'] }} | {{ $founder['email'] }}</div>
                                </div>
                            @empty
                                <div class="text-muted small">No Circle Founders linked.</div>
                            @endforelse
                        </div>
                    </div>
                    
                    <!-- Circle Directors Tab -->
                    <div class="tab-pane fade" id="nav-directors" role="tabpanel">
                        <div class="list-group list-group-flush">
                            @forelse ($quickFinder['circle_directors'] as $director)
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="fw-bold text-dark">
                                        <a href="#" class="text-primary text-decoration-none" onclick="event.preventDefault(); openActivityPeerModal('{{ $director['id'] }}', event);">
                                            {{ $director['name'] }}
                                        </a>
                                    </div>
                                    <div class="text-muted small">{{ $director['company'] }} | {{ $director['email'] }}</div>
                                </div>
                            @empty
                                <div class="text-muted small">No Circle Directors linked.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Industry Directors Tab -->
                    <div class="tab-pane fade" id="nav-ind-directors" role="tabpanel">
                        <div class="list-group list-group-flush">
                            @forelse ($quickFinder['industry_directors'] as $idr)
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="fw-bold text-dark">
                                        <a href="#" class="text-primary text-decoration-none" onclick="event.preventDefault(); openActivityPeerModal('{{ $idr['id'] }}', event);">
                                            {{ $idr['name'] }}
                                        </a>
                                    </div>
                                    <div class="text-muted small">{{ $idr['company'] }} | {{ $idr['email'] }}</div>
                                </div>
                            @empty
                                <div class="text-muted small">No Industry Directors linked.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Chairs & VC Tab -->
                    <div class="tab-pane fade" id="nav-chairs" role="tabpanel">
                        <div class="list-group list-group-flush">
                            @forelse ($quickFinder['chairs'] as $ch)
                                <div class="list-group-item px-0 py-2 border-0">
                                    <div class="fw-bold text-dark">
                                        <a href="#" class="text-primary text-decoration-none" onclick="event.preventDefault(); openActivityPeerModal('{{ $ch['id'] }}', event);">
                                            {{ $ch['name'] }}
                                        </a>
                                        <span class="badge bg-primary-subtle text-primary">Chair</span>
                                    </div>
                                    <div class="text-muted small">{{ $ch['company'] }} | {{ $ch['email'] }}</div>
                                </div>
                            @empty
                                <div class="text-muted small">No Chairs found.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
