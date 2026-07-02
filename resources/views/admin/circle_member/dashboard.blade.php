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
            <!-- Testimonials -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.testimonials.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Testimonials</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['testimonials'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-primary">
                                <i class="bi bi-chat-left-quote"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Requirements -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.requirements.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Requirements</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['requirements'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-success">
                                <i class="bi bi-card-checklist"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Referrals -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.referrals.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Referrals</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['referrals'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-warning">
                                <i class="bi bi-share"></i>
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
                            <div class="kpi-icon-wrapper bg-icon-info">
                                <i class="bi bi-chat-right-dots"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Business Deals -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.business-deals.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Business Deals</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['businessDeals'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-primary">
                                <i class="bi bi-briefcase"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Become A Leader -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.become-a-leader.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Leader Interest</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['becomeLeader'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-success">
                                <i class="bi bi-award"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Recommend A Peer -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.recommend-peer.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Recommendations</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['recommendPeer'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-warning">
                                <i class="bi bi-hand-thumbs-up"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Register A Visitor -->
            <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                <a href="{{ route('admin.activities.register-visitor.index') }}" class="text-decoration-none">
                    <div class="card glass-card p-3 h-100 border-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small fw-medium mb-1">Visitors</p>
                                <h3 class="mb-0 fw-bold text-dark">{{ number_format($data['activityCounts']['registerVisitor'] ?? 0) }}</h3>
                            </div>
                            <div class="kpi-icon-wrapper bg-icon-info">
                                <i class="bi bi-person-badge"></i>
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
@endsection
