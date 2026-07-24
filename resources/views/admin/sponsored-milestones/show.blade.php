@extends('admin.layouts.app')

@section('title', 'Milestone Award Details - ' . ($member->display_name ?: $member->first_name))

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.sponsored-milestones.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Milestone Awards
    </a>
</div>

<div class="row g-4">
    {{-- Member Profile Details & Award Summary Card --}}
    <div class="col-12 col-lg-4">
        <div class="card border bg-white shadow-none mb-4">
            <div class="card-header bg-light py-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-card-text me-2"></i>Sponsor Profile Details</h6>
            </div>
            <div class="card-body text-center py-4">
                @php
                    $name = $member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
                    $avatar = $member->profile_photo_url ?? ($member->profile_photo_file_id ? url('/api/v1/files/' . $member->profile_photo_file_id) : null);
                    $gradientIndex = abs(crc32((string) $member->id)) % 5;
                @endphp

                <div class="peer-avatar-wrapper mx-auto mb-3" style="width: 90px; height: 90px;">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="{{ $name }}" class="peer-avatar-image rounded-circle border shadow-sm" style="width: 90px; height: 90px; object-fit: cover;">
                    @else
                        <div class="peer-avatar-placeholder bg-gradient-peer-{{ $gradientIndex }} rounded-circle text-center text-white border shadow-sm" style="width: 90px; height: 90px; font-size: 2.2rem; line-height: 90px;">
                            {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>

                <h5 class="fw-bold mb-1 text-dark">{{ $name }}</h5>
                <p class="text-muted small mb-3">{{ $member->designation ?: 'Sponsor' }}</p>

                <hr>

                <div class="text-start">
                    <div class="mb-2">
                        <span class="text-muted small d-block">Email Address</span>
                        <span class="text-dark fw-medium small">{{ $member->email }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small d-block">Phone Number</span>
                        <span class="text-dark fw-medium small">{{ $member->phone ?: '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Company Name</span>
                        <span class="text-dark fw-medium small">{{ $member->company_name ?: '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Award Card --}}
        <div class="card border border-warning-subtle bg-warning-subtle bg-opacity-10 shadow-none">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-award-fill text-warning" style="font-size: 3rem;"></i>
                </div>
                
                @if ($milestoneDetails['award_name'])
                    <span class="text-warning-emphasis small fw-bold text-uppercase tracking-wider d-block mb-1">Current Milestone: {{ $milestoneDetails['current_milestone'] }}</span>
                    <h4 class="fw-bold text-dark mb-2">{{ $milestoneDetails['award_name'] }}</h4>
                    <div class="alert alert-light border border-warning-subtle text-dark-emphasis p-2 small mb-0 rounded-3">
                        <i class="bi bi-gift-fill text-warning me-1"></i>{{ $milestoneDetails['recognition'] }}
                    </div>
                @else
                    <span class="text-muted small fw-bold text-uppercase tracking-wider d-block mb-1">No Milestone Reached Yet</span>
                    <h4 class="fw-bold text-secondary mb-2">No Active Awards</h4>
                    <p class="text-muted small mb-0">Milestones are unlocked starting at 1 sponsored member.</p>
                @endif

                @if ($milestoneDetails['next_milestone'])
                    <div class="mt-4 text-start border-top pt-3 border-warning-subtle border-opacity-50">
                        @php
                            $prevMilestone = $milestoneDetails['current_milestone'];
                            $target = $milestoneDetails['next_milestone'];
                            $progressVal = $sponsoredCount - $prevMilestone;
                            $totalNeeded = $target - $prevMilestone;
                            $percent = $totalNeeded > 0 ? ($progressVal / $totalNeeded) * 100 : 0;
                        @endphp
                        <div class="d-flex justify-content-between small mb-1 fw-medium text-dark">
                            <span>Next: {{ $target }} Sponsored</span>
                            <span>{{ $sponsoredCount }} / {{ $target }}</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="text-muted mt-2 d-block small" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1"></i>Sponsor <strong>{{ $milestoneDetails['members_remaining'] }} more</strong> member{{ $milestoneDetails['members_remaining'] > 1 ? 's' : '' }} to unlock the next milestone.
                        </span>
                    </div>
                @else
                    <div class="mt-4 border-top pt-3 border-warning-subtle border-opacity-50">
                        <span class="badge bg-success text-white py-1 px-3 fs-7 border border-success"><i class="bi bi-star-fill me-1"></i>Maximum Milestone Achieved</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sponsored Members List Card --}}
    <div class="col-12 col-lg-8">
        <div class="card border bg-white shadow-none">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill me-2"></i>Sponsored Members List ({{ $sponsoredCount }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-sm mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="padding-left: 15px;">Member Name</th>
                                <th>Email</th>
                                <th>Company Name</th>
                                <th>City</th>
                                <th>Sponsored At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($sponsoredMembers as $sMember)
                                @php
                                    $sName = $sMember->display_name ?: trim(($sMember->first_name ?? '') . ' ' . ($sMember->last_name ?? ''));
                                    $sAvatar = $sMember->profile_photo_url ?? ($sMember->profile_photo_file_id ? url('/api/v1/files/' . $sMember->profile_photo_file_id) : null);
                                    $sGradientIndex = abs(crc32((string) $sMember->id)) % 5;
                                @endphp
                                <tr>
                                    <td style="padding-left: 15px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="peer-avatar-wrapper" style="width: 28px; height: 28px;">
                                                @if ($sAvatar)
                                                    <img src="{{ $sAvatar }}" alt="{{ $sName }}" class="peer-avatar-image rounded-circle" style="width: 28px; height: 28px; object-fit: cover;">
                                                @else
                                                    <div class="peer-avatar-placeholder bg-gradient-peer-{{ $sGradientIndex }} rounded-circle text-center text-white" style="width: 28px; height: 28px; font-size: 0.75rem; line-height: 28px;">
                                                        {{ strtoupper(substr($sName ?: 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <span class="fw-semibold text-dark">{{ $sName }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $sMember->email }}</td>
                                    <td>{{ $sMember->company_name ?: '—' }}</td>
                                    <td>{{ $sMember->city ?: '—' }}</td>
                                    <td>{{ $sMember->created_at ? $sMember->created_at->format('M d, Y h:i A') : '—' }}</td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-capitalize">{{ $sMember->status ?? 'active' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="text-muted mb-2"><i class="bi bi-people fs-3"></i></div>
                                        <div class="fw-semibold text-muted">No sponsored members listed</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top">
                    {{ $sponsoredMembers->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
