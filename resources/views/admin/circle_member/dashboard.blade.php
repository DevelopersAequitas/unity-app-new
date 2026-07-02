@extends('admin.layouts.app')

@section('title', 'Circle Dashboard')

@push('styles')
<style>
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    .dashboard-header {
        background: linear-gradient(-45deg, #0f172a, #1e293b, #0d6efd, #0b58ca);
        background-size: 400% 400%;
        animation: gradientShift 12s ease infinite;
        border-radius: 16px;
        color: #ffffff;
        box-shadow: 0 10px 15px -3px rgba(13, 110, 253, 0.15);
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .glass-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .kpi-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .bg-icon-primary { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .bg-icon-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .bg-icon-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .bg-icon-info { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
    .bg-icon-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    
    .peer-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #ffffff;
        box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
    .request-row:hover {
        background-color: #f9fafb;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="dashboard-header p-4 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="badge bg-white text-primary mb-2 fw-semibold px-3 py-1.5 rounded-pill">Circle Dashboard</div>
            <h2 class="mb-1 fw-bold text-white">Welcome back, {{ $data['user']->display_name }}</h2>
            <p class="mb-0 text-white-50">Here is a scoped overview of your circles, peers, and pending approvals.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.activities.index') }}" class="btn btn-light text-primary fw-semibold px-4 py-2 rounded-3">
                <i class="bi bi-activity me-2"></i>View Activities
            </a>
        </div>
    </div>

    <!-- KPI Summary Grid -->
    <div class="row g-3 mb-4">
        <!-- Scoped Peers -->
        <div class="col-12 col-sm-6 col-md-4 col-xl-3">
            <div class="card glass-card p-3 h-100 border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small fw-medium mb-1">Total Scoped Peers</p>
                        <h3 class="mb-0 fw-bold">{{ number_format($data['totalPeers']) }}</h3>
                    </div>
                    <div class="kpi-icon-wrapper bg-icon-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actionable Pending Requests -->
        <div class="col-12 col-sm-6 col-md-4 col-xl-3">
            <div class="card glass-card p-3 h-100 border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted small fw-medium mb-1">Pending Requests</p>
                        <h3 class="mb-0 fw-bold">{{ number_format($data['pendingCounts']['total']) }}</h3>
                    </div>
                    <div class="kpi-icon-wrapper bg-icon-info">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Circle Activities Overview -->
    <div class="mb-4">
        <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-activity text-primary me-2"></i>Circle Activities Overview</h5>
        <div class="row g-3">
            <!-- Total Lives Impacted -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.life-impact.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Total Lives Impacted</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['totalLivesImpacted'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-info">
                                <i class="bi bi-heart-fill"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Total Coins Earned -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.coins.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Total Coins Earned</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['totalCoinsEarned'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-warning">
                                <i class="bi bi-coin"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Business Deals Count -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.business-deals.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Business Deals Count</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['businessDealsCount'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-primary">
                                <i class="bi bi-briefcase"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Business Deals Amount -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.business-deals.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Business Deals Amount</p>
                                <h3 class="mb-0 fw-bold text-dark">₹ {{ number_format($data['activityCounts']['businessDealsAmount'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-success">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Referrals Passed -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.referrals.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Referrals Passed</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['referralsPassed'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-info">
                                <i class="bi bi-share"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Connections Made -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.connections.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Connections Made</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['connectionsMade'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-primary">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- P2P Meetings -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.p2p-meetings.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">P2P Meetings</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['p2pMeetings'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-success">
                                <i class="bi bi-chat-right-dots"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Requirements Posted -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.requirements.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Requirements Posted</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['requirementsPosted'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-warning">
                                <i class="bi bi-card-checklist"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Testimonials Exchanged -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.testimonials.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Testimonials Exchanged</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['testimonialsExchanged'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-info">
                                <i class="bi bi-chat-left-quote"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Visitors -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.visitor-registrations.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Visitors</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['visitors'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-primary">
                                <i class="bi bi-person-badge"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Circle Left Members -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="javascript:void(0);" onclick="handleLeftMembersClick()" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Circle Left Members</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['circleLeftMembers'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-danger">
                                <i class="bi bi-person-dash"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Circle Joined Members -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.users.index', ['joined_filter' => 'last_month']) }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Circle Joined Members</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['circleJoinedMembers'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-success">
                                <i class="bi bi-person-check"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Grid Section -->
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <!-- Circles Details Section -->
            <div class="card border-0 shadow-sm p-4 mb-4 rounded-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="kpi-icon-wrapper bg-icon-success me-3">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">My Circles & Details</h5>
                        <p class="text-muted small mb-0">Overview of circles you are associated with, including launch dates and leadership.</p>
                    </div>
                </div>

                <div class="row g-3">
                    @forelse($data['joinedCircles'] as $memberRecord)
                        @php
                            $circle = $memberRecord->circle;
                        @endphp
                        @if($circle)
                            <div class="col-12 col-md-6">
                                <div class="card border border-light-subtle rounded-3 p-3 h-100 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="fw-bold text-primary mb-1">{{ $circle->name }}</h6>
                                            <span class="badge bg-primary-subtle text-primary text-capitalize px-2 py-1 fs-8">
                                                {{ str_replace('_', ' ', $memberRecord->role) }}
                                            </span>
                                        </div>
                                        @if($circle->status)
                                            <span class="badge bg-{{ $circle->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $circle->status === 'active' ? 'success' : 'secondary' }} text-capitalize border border-{{ $circle->status === 'active' ? 'success' : 'secondary' }}-subtle px-2.5 py-1 fs-8">
                                                {{ $circle->status }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-muted fs-7 mb-3" style="min-height: 42px;">
                                        {{ Str::limit($circle->description ?? $circle->purpose ?? 'No description provided.', 120) }}
                                    </div>

                                    <div class="border-top pt-3 fs-7">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="text-muted small fs-8">Circle Founder</div>
                                                <div class="fw-semibold text-dark text-truncate">
                                                    {{ $circle->founder ? $circle->founder->display_name : '—' }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small fs-8">Circle Director</div>
                                                <div class="fw-semibold text-dark text-truncate">
                                                    {{ $circle->director ? $circle->director->display_name : '—' }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small fs-8">Launch Date</div>
                                                <div class="fw-semibold text-dark">
                                                    {{ $circle->launch_date ? \Illuminate\Support\Carbon::parse($circle->launch_date)->format('M d, Y') : '—' }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small fs-8">DED</div>
                                                <div class="fw-semibold text-dark text-truncate">
                                                    {{ $circle->ded ? $circle->ded->display_name : '—' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="col-12 text-center py-4">
                            <p class="text-muted mb-0">You are not associated with any circles yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- 1. Circle Peers Details -->
            <div class="card border-0 shadow-sm p-4 mb-4 rounded-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="kpi-icon-wrapper bg-icon-primary me-3">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Circle Peers Details</h5>
                        <p class="text-muted small mb-0">Peers belonging to the same circles as you.</p>
                    </div>
                </div>

                @if($data['recentPeers']->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="text-muted fs-7">
                                    <th>Peer Name</th>
                                    <th>Circle</th>
                                    <th>Circle Role</th>
                                    <th>Joined At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['recentPeers'] as $peerMember)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($peerMember->user->profile_photo_url)
                                                    <img src="{{ $peerMember->user->profile_photo_url }}" alt="avatar" class="peer-avatar me-2">
                                                @else
                                                    <div class="peer-avatar bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold me-2" style="font-size:0.8rem;">
                                                        {{ strtoupper(substr($peerMember->user->first_name ?? 'P', 0, 1)) }}{{ strtoupper(substr($peerMember->user->last_name ?? '', 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="fw-semibold text-dark fs-7">{{ $peerMember->user->display_name }}</div>
                                                    <div class="text-muted fs-8">{{ $peerMember->user->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fs-7">{{ $peerMember->circle->name ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary text-capitalize fw-semibold px-2 py-1 fs-8">{{ str_replace('_', ' ', $peerMember->role) }}</span>
                                        </td>
                                        <td class="text-muted fs-8">
                                            {{ $peerMember->joined_at ? $peerMember->joined_at->format('Y-m-d') : 'N/A' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary btn-sm px-3 rounded-2 fs-7">View All Peers</a>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted display-4"></i>
                        <p class="text-muted mt-2 mb-0">No peers found in your circles.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 3. Actionable Pending Requests Details -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm p-4 h-100 rounded-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="kpi-icon-wrapper bg-icon-info me-3">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Actionable Pending Requests</h5>
                        <p class="text-muted small mb-0">Requests requiring your review and approval.</p>
                    </div>
                </div>

                <div class="list-group list-group-flush gap-2 mt-2">
                    <!-- Circle joining requests -->
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 rounded-3 bg-light px-3 py-2.5 request-row">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-diagram-3-fill text-primary me-3 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-dark small">Circle Join Requests</div>
                                <div class="text-muted fs-8">Members seeking to join circles</div>
                            </div>
                        </div>
                        <a href="{{ route('admin.circle-joining-requests.index') }}" class="btn btn-sm text-decoration-none">
                            <span class="badge {{ $data['pendingCounts']['circleJoin'] > 0 ? 'bg-primary' : 'bg-secondary' }} px-2.5 py-1.5 fs-7">{{ $data['pendingCounts']['circleJoin'] }}</span>
                        </a>
                    </div>

                    <!-- Coin Claims -->
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 rounded-3 bg-light px-3 py-2.5 request-row">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-coin text-warning me-3 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-dark small">Coin Claims</div>
                                <div class="text-muted fs-8">Pending coins requests</div>
                            </div>
                        </div>
                        <a href="{{ route('admin.coin-claims.index') }}" class="btn btn-sm text-decoration-none">
                            <span class="badge {{ $data['pendingCounts']['coinClaims'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' }} px-2.5 py-1.5 fs-7">{{ $data['pendingCounts']['coinClaims'] }}</span>
                        </a>
                    </div>

                    <!-- Visitor Registrations -->
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 rounded-3 bg-light px-3 py-2.5 request-row">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-person-plus-fill text-success me-3 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-dark small">Visitor Registrations</div>
                                <div class="text-muted fs-8">Invited guests registrations</div>
                            </div>
                        </div>
                        <a href="{{ route('admin.visitor-registrations.index') }}" class="btn btn-sm text-decoration-none">
                            <span class="badge {{ $data['pendingCounts']['visitorRegistrations'] > 0 ? 'bg-success' : 'bg-secondary' }} px-2.5 py-1.5 fs-7">{{ $data['pendingCounts']['visitorRegistrations'] }}</span>
                        </a>
                    </div>

                    <!-- Event Joining Requests -->
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0 rounded-3 bg-light px-3 py-2.5 request-row">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-event-fill text-info me-3 fs-5"></i>
                            <div>
                                <div class="fw-semibold text-dark small">Event Join Requests</div>
                                <div class="text-muted fs-8">Event access approvals</div>
                            </div>
                        </div>
                        <a href="{{ route('admin.event-joining-requests.index') }}" class="btn btn-sm text-decoration-none">
                            <span class="badge {{ $data['pendingCounts']['eventJoining'] > 0 ? 'bg-info' : 'bg-secondary' }} px-2.5 py-1.5 fs-7">{{ $data['pendingCounts']['eventJoining'] }}</span>
                        </a>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top text-center text-muted small">
                    Total pending tasks: <strong class="text-dark">{{ $data['pendingCounts']['total'] }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Left Members Modal -->
<div class="modal fade" id="leftMembersModal" tabindex="-1" aria-labelledby="leftMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center" id="leftMembersModalLabel">
                    <i class="bi bi-person-x-fill me-2 fs-4"></i> Members Left in Last Month
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-muted fs-8">
                                <th>Name</th>
                                <th>Circle</th>
                                <th>Left At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['leftMembers'] as $leftMember)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark fs-7">
                                            {{ $leftMember->user->display_name ?? '—' }}
                                        </div>
                                        <div class="text-muted fs-8">
                                            {{ $leftMember->user->email ?? '—' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fs-7 text-dark">{{ $leftMember->circle->name ?? '—' }}</span>
                                    </td>
                                    <td class="text-muted fs-8">
                                        {{ $leftMember->left_at ? \Illuminate\Support\Carbon::parse($leftMember->left_at)->format('Y-m-d') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function handleLeftMembersClick() {
        const leftCount = {{ (int)($data['activityCounts']['circleLeftMembers'] ?? 0) }};
        if (leftCount === 0) {
            alert('No members left the circle in the last month.');
        } else {
            const modal = new bootstrap.Modal(document.getElementById('leftMembersModal'));
            modal.show();
        }
    }
</script>
@endsection
