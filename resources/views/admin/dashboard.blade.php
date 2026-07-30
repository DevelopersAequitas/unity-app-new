@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $greetingIcon = $hour < 12 ? 'bi-sun-fill' : ($hour < 17 ? 'bi-sun-fill' : 'bi-moon-fill');
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

                                $peerCircles = $peer->circleMembers
                                    ->map(fn($cm) => $cm->circle)
                                    ->filter()
                                    ->unique('id');

                                $joinDate = $peer->membership_approved_at
                                    ?? $peer->circle_joined_at
                                    ?? $peer->created_at;

                                $peerData = [
                                    'id' => $peer->id,
                                    'fullName' => $fullName,
                                    'designation' => $peer->designation ?? 'Member',
                                    'company' => $peer->company_name ?? '—',
                                    'location' => implode(', ', array_filter([$peer->city, $peer->state, $peer->country])) ?: '—',
                                    'email' => $peer->email ?? '—',
                                    'phone' => $peer->phone ?? ($peer->secondary_mobile ?? '—'),
                                    'photo' => $peer->profile_photo_url,
                                    'initials' => $initials,
                                    'color' => $color,
                                    'circles' => $peerCircles->pluck('name')->values()->toArray(),
                                    'joined' => $joinDate ? $joinDate->format('d M Y, h:i A') : '—',
                                    'joinedRelative' => $joinDate ? $joinDate->diffForHumans() : '—',
                                    'showUrl' => route('admin.users.show', $peer->id),
                                    'editUrl' => route('admin.users.edit', $peer->id),
                                ];
                            @endphp
                            <tr class="peer-row" style="border-bottom: 1px solid rgba(0,0,0,0.03); cursor: pointer;" data-peer="{{ json_encode($peerData) }}" onclick="openPeerModal(this, event)">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($peer->profile_photo_url)
                                            <img src="{{ $peer->profile_photo_url }}" alt="{{ $fullName }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                        @else
                                            <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold" style="width: 32px; height: 32px; background-color: {{ $color }}; font-size: 0.75rem;">
                                                {{ $initials }}
                                            </div>
                                        @endif
                                            <div class="fw-semibold text-dark">
                                                <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $peer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">{{ $fullName }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark fw-medium">{{ $peer->company_name ?? '—' }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">{{ $peer->city ?? '—' }}</div>
                                </td>
                                <td>
                                     @if($peerCircles->isNotEmpty())
                                         <div class="d-flex flex-column gap-1 align-items-start">
                                             @foreach($peerCircles as $circle)
                                                 <span class="badge rounded-pill bg-light text-primary border px-2 py-1" style="font-size: 0.7rem; font-weight: 550; white-space: nowrap;">
                                                     {{ $circle->name }}
                                                 </span>
                                             @endforeach
                                         </div>
                                     @else
                                         <span class="text-muted">—</span>
                                     @endif
                                 </td>
                                <td class="text-muted small">
                                    @if($joinDate)
                                        <span title="{{ $joinDate->format('d M Y, h:i A') }}">
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
                                    <button type="button" class="btn btn-sm btn-outline-info px-2 py-1 me-1" style="font-size: 0.75rem; border-radius: 8px;" title="View Details" onclick="event.stopPropagation(); openPeerModal(this.closest('tr'), event)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="{{ route('admin.users.edit', $peer->id) }}" class="btn btn-sm btn-outline-primary px-2 py-1" style="font-size: 0.75rem; border-radius: 8px;" title="Edit Peer" onclick="event.stopPropagation();">
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
    <div class="col-12 col-md-6">
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
                    <a href="{{ route('admin.contacts.index') }}" class="text-decoration-none d-block h-100">
                        <div class="p-3 h-100 hover-elevate" style="border-radius: 14px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.04), rgba(99, 102, 241, 0.02)); border: 1px solid rgba(99, 102, 241, 0.08); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: var(--primary-subtle); display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-people" style="color: var(--primary); font-size: 0.85rem;"></i>
                                </div>
                                <span class="text-muted small" style="font-size: 0.78rem;">Total Contacts</span>
                            </div>
                            <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif; color: var(--text-primary);">{{ number_format($totalContactPosts ?? 0) }}</h4>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6">
                    <a href="{{ route('admin.contacts.index') }}" class="text-decoration-none d-block h-100">
                        <div class="p-3 h-100 hover-elevate" style="border-radius: 14px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.04), rgba(16, 185, 129, 0.02)); border: 1px solid rgba(16, 185, 129, 0.08); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: var(--success-subtle); display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-calendar-check" style="color: var(--success); font-size: 0.85rem;"></i>
                                </div>
                                <span class="text-muted small" style="font-size: 0.78rem;">Today's Contacts</span>
                            </div>
                            <h4 class="mb-0 fw-bold" style="font-family: 'Outfit', sans-serif; color: var(--text-primary);">{{ number_format($todayContactPosts ?? 0) }}</h4>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Column: Pending Approvals --}}
    <div class="col-12 col-md-6">
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
            <div class="list-group list-group-flush gap-1">
                @foreach ($pendingItems as $item)
                    <a href="{{ $item['url'] ?? '#' }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-3 py-2.5 rounded-3" style="background: rgba(0,0,0,0.015); border: 1px solid rgba(0,0,0,0.04); transition: all 0.2s ease;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="status-dot" style="width: 7px; height: 7px; border-radius: 50%; display: inline-block; background-color: {{ $item['count'] > 0 ? 'var(--warning)' : 'var(--text-light)' }};"></span>
                            <span class="fw-medium small text-dark" style="font-size: 0.82rem;">{{ $item['title'] }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill" style="font-weight: 600; font-size: 0.7rem; padding: 4px 8px; {{ $item['count'] > 0 ? 'background: var(--warning-subtle); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.15);' : 'background: var(--bg-muted); color: var(--text-light); border: 1px solid var(--border-light);' }}">
                                {{ $item['count'] }}
                            </span>
                            <i class="bi bi-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Premium Peer Detail Popup Modal --}}
<div class="modal fade" id="peerDetailModal" tabindex="-1" aria-labelledby="peerDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden; background: #ffffff;">
            <!-- Modal Header Banner -->
            <div class="modal-header border-0 position-relative text-white p-4" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 60%, #818cf8 100%); padding-bottom: 3.5rem !important;">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px);" class="d-flex align-items-center justify-content-center">
                        <i class="bi bi-person-vcard-fill text-white" style="font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" id="peerDetailModalLabel" style="font-size: 1.05rem; letter-spacing: -0.01em;">Peer Basic Details</h6>
                        <span class="text-white-50" style="font-size: 0.75rem;">Platform Member Overview</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 opacity-75" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.8rem;"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body px-4 pb-4" style="margin-top: -2.5rem;">
                <!-- Profile Card Header -->
                <div class="card border-0 shadow-sm p-3 mb-3 bg-white" style="border-radius: 18px; border: 1px solid rgba(226, 232, 240, 0.8) !important;">
                    <div class="d-flex align-items-center gap-3">
                        <div id="modalPeerAvatarContainer" class="position-relative"></div>
                        <div class="overflow-hidden flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <h5 class="mb-0 fw-bold text-dark text-truncate" id="modalPeerName" style="font-family: 'Outfit', sans-serif; font-size: 1.15rem;"></h5>
                                <span class="badge rounded-pill px-2.5 py-1 fw-semibold" id="modalPeerDesignation" style="font-size: 0.72rem; background: rgba(99, 102, 241, 0.1); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.2);"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 small text-muted">
                                <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <i class="bi bi-circle-fill text-success" style="font-size: 0.45rem;"></i> Active Member
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="row g-2.5">
                    <!-- Company -->
                    <div class="col-6">
                        <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(99, 102, 241, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-building-fill" style="color: #6366f1; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Company</span>
                            </div>
                            <div class="fw-bold text-dark text-truncate" id="modalPeerCompany" style="font-size: 0.88rem;"></div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="col-6">
                        <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(244, 63, 94, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-geo-alt-fill" style="color: #f43f5e; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Location</span>
                            </div>
                            <div class="fw-bold text-dark text-truncate" id="modalPeerLocation" style="font-size: 0.88rem;"></div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-6">
                        <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(6, 182, 212, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-envelope-fill" style="color: #06b6d4; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Email</span>
                            </div>
                            <div class="fw-bold text-dark text-truncate" id="modalPeerEmail" style="font-size: 0.85rem;"></div>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="col-6">
                        <div class="p-3 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(16, 185, 129, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-telephone-fill" style="color: #10b981; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Phone</span>
                            </div>
                            <div class="fw-bold text-dark text-truncate" id="modalPeerPhone" style="font-size: 0.88rem;"></div>
                        </div>
                    </div>

                    <!-- Circles -->
                    <div class="col-12">
                        <div class="p-3 rounded-4" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(245, 158, 11, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-diagram-3-fill" style="color: #f59e0b; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Circle Memberships</span>
                            </div>
                            <div id="modalPeerCircles" class="d-flex flex-wrap gap-1.5"></div>
                        </div>
                    </div>

                    <!-- Joined Date -->
                    <div class="col-12">
                        <div class="p-3 rounded-4" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex align-items-center gap-2 mb-1.5">
                                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(168, 85, 247, 0.1);" class="d-flex align-items-center justify-content-center">
                                    <i class="bi bi-calendar-event-fill" style="color: #a855f7; font-size: 0.8rem;"></i>
                                </div>
                                <span class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em; color: #64748b;">Joined Date</span>
                            </div>
                            <div class="fw-bold text-dark" id="modalPeerJoined" style="font-size: 0.88rem;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-0 px-4 py-3 bg-light d-flex justify-content-between align-items-center" style="border-top: 1px solid #f1f5f9 !important;">
                <a href="#" id="modalPeerEditBtn" class="btn btn-outline-secondary rounded-pill px-3.5 py-2 d-inline-flex align-items-center gap-1.5 fw-semibold" style="font-size: 0.82rem; border-color: #cbd5e1; color: #475569;">
                    <i class="bi bi-pencil-square"></i> Edit Peer
                </a>
                <a href="#" id="modalPeerShowBtn" class="btn btn-primary rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 fw-semibold border-0 shadow-sm" style="font-size: 0.82rem; background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);">
                    <i class="bi bi-person-bounding-box"></i> Full Profile
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .peer-row {
        transition: background-color 0.15s ease-in-out;
    }
    .peer-row:hover {
        background-color: rgba(99, 102, 241, 0.04) !important;
    }
</style>

<script>
    function openPeerModal(rowElement, event) {
        if (event && event.target && (event.target.closest('a') || event.target.closest('button.btn-outline-primary'))) {
            return;
        }

        const rawData = rowElement.getAttribute('data-peer');
        if (!rawData) return;
        
        try {
            const peer = JSON.parse(rawData);
            
            const renderValue = (val) => {
                if (!val || val === '—' || val.trim() === '') {
                    return `<span class="text-muted fw-normal" style="font-size: 0.82rem; font-style: italic;">Not specified</span>`;
                }
                return val;
            };

            document.getElementById('modalPeerName').textContent = peer.fullName || 'User';
            document.getElementById('modalPeerDesignation').textContent = peer.designation || 'Member';
            document.getElementById('modalPeerCompany').innerHTML = renderValue(peer.company);
            document.getElementById('modalPeerLocation').innerHTML = renderValue(peer.location);
            document.getElementById('modalPeerEmail').innerHTML = renderValue(peer.email);
            document.getElementById('modalPeerPhone').innerHTML = renderValue(peer.phone);
            
            const joinedText = peer.joinedRelative 
                ? `<span class="text-dark">${peer.joinedRelative}</span> <span class="text-muted small fw-normal">(${peer.joined})</span>`
                : renderValue(peer.joined);
            document.getElementById('modalPeerJoined').innerHTML = joinedText;
            
            document.getElementById('modalPeerEditBtn').href = peer.editUrl || '#';
            document.getElementById('modalPeerShowBtn').href = peer.showUrl || '#';

            const avatarContainer = document.getElementById('modalPeerAvatarContainer');
            if (peer.photo) {
                avatarContainer.innerHTML = `<img src="${peer.photo}" alt="${peer.fullName}" class="rounded-circle shadow-sm" style="width: 56px; height: 56px; object-fit: cover; border: 3px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">`;
            } else {
                avatarContainer.innerHTML = `<div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold shadow-sm" style="width: 56px; height: 56px; background: linear-gradient(135deg, ${peer.color}, #4f46e5); font-size: 1.15rem; border: 3px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">${peer.initials || 'U'}</div>`;
            }

            const circlesContainer = document.getElementById('modalPeerCircles');
            if (peer.circles && peer.circles.length > 0) {
                circlesContainer.innerHTML = peer.circles.map(c => `
                    <span class="badge rounded-pill bg-white text-primary border px-2.5 py-1.5 shadow-2xs d-inline-flex align-items-center gap-1" style="font-size: 0.75rem; font-weight: 550;">
                        <i class="bi bi-diagram-3" style="font-size: 0.7rem;"></i> ${c}
                    </span>
                `).join(' ');
            } else {
                circlesContainer.innerHTML = `<span class="text-muted small fst-italic">No circle memberships</span>`;
            }

            const modalEl = document.getElementById('peerDetailModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        } catch (err) {
            console.error('Error opening peer modal:', err);
        }
    }
</script>
@endsection
