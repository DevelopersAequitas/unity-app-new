@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $greetingEmoji = $hour < 12 ? '☀️' : ($hour < 17 ? '🌤️' : '🌙');
@endphp

{{-- Simple Page Header --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 fade-in-up">
    <div>
        <h4 class="mb-1 section-heading fw-bold" style="color: var(--text-primary);">Dashboard Overview</h4>
        <p class="text-muted mb-0" style="font-size: 0.82rem;">Today is <strong>{{ now()->format('l, M d, Y') }}</strong></p>
    </div>
    <div>
        <button class="btn btn-sm btn-light border d-inline-flex align-items-center gap-2 px-3 py-2" style="font-weight: 600; font-size: 0.82rem; border-radius: 10px;" onclick="window.location.reload();">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

{{-- Quick Action Grid --}}
<div class="row g-2 mb-4 fade-in-up fade-in-up-delay-1">
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.users.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-people-fill" style="color: var(--stat-indigo);"></i>
            <span>View Peers</span>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.circles.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-diagram-3-fill" style="color: var(--stat-emerald);"></i>
            <span>Circles</span>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.coins.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-coin" style="color: var(--stat-amber);"></i>
            <span>Coins</span>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.circle-joining-requests.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-hourglass-split" style="color: var(--stat-rose);"></i>
            <span>Requests</span>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.activities.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-activity" style="color: var(--stat-cyan);"></i>
            <span>Activities</span>
        </a>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <a href="{{ route('admin.contacts.index') }}" class="quick-action-btn w-100">
            <i class="bi bi-person-lines-fill" style="color: var(--stat-purple);"></i>
            <span>Unity Contacts</span>
        </a>
    </div>
</div>

{{-- Platform Pulse Metrics --}}
<div class="row g-3 mb-4">
    {{-- Total Peers --}}
    <div class="col-12 col-sm-6 col-xl-3 fade-in-up fade-in-up-delay-1">
        <a href="{{ route('admin.users.index') }}" class="card hover-elevate p-3 h-100 text-decoration-none text-reset">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Total Peers</span>
                    <span class="stat-number">{{ number_format($stats['total_users'] ?? 0) }}</span>
                </div>
                <div class="stat-icon-wrapper stat-indigo">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="mt-auto">
                <div class="stat-progress mb-2">
                    <div class="stat-progress-bar" style="width: 75%; background: linear-gradient(90deg, #6366f1, #818cf8);"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between small text-muted">
                    <span style="font-size: 0.75rem;">Registered directory</span>
                    <span class="trend-badge" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="bi bi-arrow-up-short"></i>+12%
                    </span>
                </div>
            </div>
        </a>
    </div>

    {{-- Active Circles --}}
    <div class="col-12 col-sm-6 col-xl-3 fade-in-up fade-in-up-delay-2">
        <a href="{{ route('admin.circles.index') }}" class="card hover-elevate p-3 h-100 text-decoration-none text-reset">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Active Circles</span>
                    <span class="stat-number">{{ number_format($stats['active_circles'] ?? 0) }}</span>
                </div>
                <div class="stat-icon-wrapper stat-emerald">
                    <i class="bi bi-diagram-3-fill"></i>
                </div>
            </div>
            <div class="mt-auto">
                <div class="stat-progress mb-2">
                    <div class="stat-progress-bar" style="width: 85%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between small text-muted">
                    <span style="font-size: 0.75rem;">Operational hubs</span>
                    <span class="trend-badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="bi bi-check-circle-fill"></i> Healthy
                    </span>
                </div>
            </div>
        </a>
    </div>

    {{-- Pending Approvals --}}
    <div class="col-12 col-sm-6 col-xl-3 fade-in-up fade-in-up-delay-3">
        <a href="{{ route('admin.circles.index', ['status' => 'pending']) }}" class="card hover-elevate p-3 h-100 text-decoration-none text-reset">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Awaiting Review</span>
                    <span class="stat-number">{{ number_format($stats['pending_approvals'] ?? 0) }}</span>
                </div>
                <div class="stat-icon-wrapper stat-amber">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="mt-auto">
                <div class="stat-progress mb-2">
                    <div class="stat-progress-bar" style="width: 40%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between small text-muted">
                    <span style="font-size: 0.75rem;">Circles awaiting action</span>
                    <span class="trend-badge" style="background: rgba(245, 158, 11, 0.1); color: #d97706;">
                        <i class="bi bi-clock"></i> Pending
                    </span>
                </div>
            </div>
        </a>
    </div>

    {{-- New Signups --}}
    <div class="col-12 col-sm-6 col-xl-3 fade-in-up fade-in-up-delay-4">
        <a href="{{ route('admin.users.index', ['joined_filter' => 'custom', 'joined_from' => now()->toDateString(), 'joined_to' => now()->toDateString()]) }}" class="card hover-elevate p-3 h-100 text-decoration-none text-reset">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">New Signups</span>
                    <span class="stat-number">{{ number_format($stats['new_signups'] ?? 0) }}</span>
                </div>
                <div class="stat-icon-wrapper stat-purple">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
            </div>
            <div class="mt-auto">
                <div class="stat-progress mb-2">
                    <div class="stat-progress-bar" style="width: 60%; background: linear-gradient(90deg, #a855f7, #c084fc);"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between small text-muted">
                    <span style="font-size: 0.75rem;">Registered today</span>
                    <span class="trend-badge" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                        <i class="bi bi-lightning-charge-fill"></i> Active
                    </span>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        {{-- Recently Joined Peers --}}
        <div class="card p-4 fade-in-up fade-in-up-delay-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="mb-1 section-heading" style="font-size: 1.05rem;">
                        <i class="bi bi-people-fill me-2" style="color: var(--primary); font-size: 0.95rem;"></i>Recently Joined Peers
                    </h5>
                    <p class="section-subheading mb-0" style="font-size: 0.82rem;">Lately registered members on the platform</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-light border d-flex align-items-center gap-1" style="border-radius: 10px; font-size: 0.8rem;">
                    <i class="bi bi-arrow-up-right" style="font-size: 0.7rem;"></i> View All Peers
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size: 0.85rem;">
                    <thead>
                        <tr class="text-muted" style="font-size: 0.75rem; border-bottom: 1px solid var(--border-light);">
                            <th>Member</th>
                            <th>Company</th>
                            <th>Circle</th>
                            <th>Joined</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPeers as $peer)
                            @php
                                $firstName = trim((string) ($peer->first_name ?? ''));
                                $lastName = trim((string) ($peer->last_name ?? ''));
                                $fullName = trim($firstName . ' ' . $lastName);
                                if ($fullName === '') {
                                    $fullName = trim((string) ($peer->display_name ?? ''));
                                }
                                if ($fullName === '') {
                                    $fullName = $peer->email ?? 'User';
                                }
                                
                                $initials = '';
                                if ($firstName !== '') {
                                    $initials .= strtoupper(substr($firstName, 0, 1));
                                }
                                if ($lastName !== '') {
                                    $initials .= strtoupper(substr($lastName, 0, 1));
                                }
                                if ($initials === '') {
                                    $initials = strtoupper(substr($fullName, 0, 2));
                                }
                                $colors = ['#4f46e5', '#0891b2', '#0d9488', '#ea580c', '#db2777', '#7c3aed', '#2563eb'];
                                $color = $colors[abs(crc32($fullName)) % count($colors)];
                            @endphp
                            <tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($peer->profile_photo_url)
                                            <img src="{{ $peer->profile_photo_url }}" alt="{{ $fullName }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                        @else
                                            <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold" style="width: 32px; height: 32px; background-color: {{ $color }}; font-size: 0.75rem;">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $fullName }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $peer->designation ?? 'Member' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark fw-medium">{{ $peer->company_name ?? '—' }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">{{ $peer->city ?? '—' }}</div>
                                </td>
                                <td>
                                    @php
                                        $circleName = $peer->circleMembers->first()?->circle?->name ?? null;
                                    @endphp
                                    @if($circleName)
                                        <span class="badge rounded-pill bg-light text-primary border px-2 py-1" style="font-size: 0.7rem; font-weight: 550;">
                                            {{ $circleName }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    @php
                                        $joinDate = $peer->membership_approved_at
                                            ?? $peer->circle_joined_at
                                            ?? $peer->created_at;
                                    @endphp
                                    @if($joinDate)
                                        <span title="{{ $joinDate->format('d M Y, h:i A') }}" style="cursor:default;">
                                            {{ $joinDate->diffForHumans() }}
                                        </span>
                                        <div style="font-size:0.68rem; color:var(--text-light);">
                                            {{ $joinDate->format('d M Y') }}
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.users.edit', $peer->id) }}" class="btn btn-sm btn-outline-primary px-2 py-1" style="font-size: 0.75rem; border-radius: 8px;">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No recently joined peers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Left Column: Contacts --}}
    <div class="col-12 col-lg-6">
        {{-- Directory Contacts Summary --}}
        <div class="card p-4 fade-in-up fade-in-up-delay-3 h-100">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="mb-1 section-heading" style="font-size: 1.05rem;">
                        <i class="bi bi-person-lines-fill me-2" style="color: var(--stat-cyan); font-size: 0.95rem;"></i>Directory Contacts
                    </h5>
                    <p class="section-subheading mb-0" style="font-size: 0.82rem;">Communications database entries from administrative inquiries</p>
                </div>
                <a href="{{ route('admin.contacts.index') }}" class="btn btn-sm btn-primary px-3 d-flex align-items-center gap-1" style="border-radius: 10px; font-size: 0.8rem;">
                    <i class="bi bi-arrow-up-right" style="font-size: 0.7rem;"></i> View
                </a>
            </div>
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="p-3 h-100" style="border-radius: 14px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.04), rgba(99, 102, 241, 0.02)); border: 1px solid rgba(99, 102, 241, 0.08);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div style="width: 32px; height: 32px; border-radius: 10px; background: var(--primary-subtle); display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-people" style="color: var(--primary); font-size: 0.85rem;"></i>
                            </div>
                            <span class="text-muted small" style="font-size: 0.78rem;">Total Contacts</span>
                        </div>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif; color: var(--text-primary);">{{ number_format($totalContactPosts ?? 0) }}</h4>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="p-3 h-100" style="border-radius: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.04), rgba(16, 185, 129, 0.02)); border: 1px solid rgba(16, 185, 129, 0.08);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div style="width: 32px; height: 32px; border-radius: 10px; background: var(--success-subtle); display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-calendar-check" style="color: var(--success); font-size: 0.85rem;"></i>
                            </div>
                            <span class="text-muted small" style="font-size: 0.78rem;">Today's Contacts</span>
                        </div>
                        <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif; color: var(--text-primary);">{{ number_format($todayContactPosts ?? 0) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Middle Column: Pending Actions --}}
    <div class="col-12 col-md-6 col-lg-3">
        {{-- Pending Approvals --}}
        <div class="card p-4 fade-in-up fade-in-up-delay-2 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 section-heading" style="font-size: 0.95rem;">
                    <i class="bi bi-hourglass-split me-2" style="color: var(--stat-amber); font-size: 0.85rem;"></i>Pending Approvals
                </h6>
                <a href="{{ route('admin.circle-joining-requests.index') }}" class="btn btn-sm btn-light border d-flex align-items-center gap-1" style="border-radius: 8px; font-size: 0.75rem; padding: 5px 12px;">
                    View <i class="bi bi-chevron-right" style="font-size: 0.65rem;"></i>
                </a>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($pendingItems as $item)
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2" style="background: transparent; border-bottom: 1px solid rgba(0,0,0,0.03);">
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-dot" style="width: 6px; height: 6px; border-radius: 50%; display: inline-block; background-color: {{ $item['count'] > 0 ? 'var(--warning)' : 'var(--text-light)' }};"></span>
                            <span class="fw-medium small text-secondary" style="font-size: 0.8rem;">{{ $item['title'] }}</span>
                        </div>
                        <span class="badge rounded-pill" style="font-weight: 600; font-size: 0.7rem; padding: 4px 8px; {{ $item['count'] > 0 ? 'background: var(--warning-subtle); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.15);' : 'background: var(--bg-muted); color: var(--text-light); border: 1px solid var(--border-light);' }}">
                            {{ $item['count'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right Column: System Health --}}
    <div class="col-12 col-md-6 col-lg-3">
        {{-- System Health Monitor --}}
        <div class="card p-4 fade-in-up fade-in-up-delay-3 h-100">
            <h6 class="mb-3 section-heading" style="font-size: 0.95rem;">
                <i class="bi bi-heart-pulse me-2" style="color: var(--success); font-size: 0.85rem;"></i>System Health
            </h6>
            <div class="d-flex flex-column gap-2">
                <div class="health-indicator d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-database-fill text-success" style="font-size: 0.9rem;"></i>
                        <span class="text-secondary small" style="font-size: 0.8rem;">DB</span>
                    </div>
                    <span class="badge rounded-pill bg-success-subtle text-success" style="font-size: 0.68rem; font-weight: 600; padding: 3px 8px;">PostgreSQL</span>
                </div>
                <div class="health-indicator d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-paper-fill text-warning" style="font-size: 0.9rem;"></i>
                        <span class="text-secondary small" style="font-size: 0.8rem;">Mailer</span>
                    </div>
                    <span class="badge rounded-pill bg-warning-subtle text-warning" style="font-size: 0.68rem; font-weight: 600; padding: 3px 8px; color: #b45309 !important;">Log</span>
                </div>
                <div class="health-indicator d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-broadcast text-info" style="font-size: 0.9rem;"></i>
                        <span class="text-secondary small" style="font-size: 0.8rem;">WS</span>
                    </div>
                    <span class="badge rounded-pill bg-info-subtle text-info" style="font-size: 0.68rem; font-weight: 600; padding: 3px 8px;">Active</span>
                </div>
                <div class="health-indicator d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-fill-check text-success" style="font-size: 0.9rem;"></i>
                        <span class="text-secondary small" style="font-size: 0.8rem;">Env</span>
                    </div>
                    <span class="badge rounded-pill bg-success-subtle text-success" style="font-size: 0.68rem; font-weight: 600; padding: 3px 8px;">{{ app()->environment() }}</span>
                </div>
            </div>
            <div class="text-center mt-3 pt-2" style="border-top: 1px solid var(--border-light);">
                <span class="text-muted" style="font-size: 0.68rem;"><i class="bi bi-code-square me-1"></i>v{{ app()->version() }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
