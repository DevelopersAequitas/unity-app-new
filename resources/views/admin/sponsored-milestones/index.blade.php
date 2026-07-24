@extends('admin.layouts.app')

@section('title', 'Sponsored Member Milestone Awards')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card p-3">
    <div class="card border bg-white shadow-none">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy-fill text-warning me-2"></i>Sponsored Member Milestone Awards</h6>
        </div>
        <div class="card-body p-3">
            {{-- Filter Form --}}
            <form id="milestonesFiltersForm" method="GET" class="border rounded-3 p-3 mb-3 bg-white">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted" for="milestoneSearch">Search</label>
                        <input type="text" id="milestoneSearch" name="q" class="form-control form-control-sm" placeholder="Search by name, email, company, phone..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted" for="milestoneFilter">Milestone Threshold</label>
                        <select id="milestoneFilter" name="milestone" class="form-select form-select-sm">
                            <option value="">All Milestones</option>
                            @foreach([0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25] as $val)
                                <option value="{{ $val }}" @selected(($filters['milestone'] !== null && $filters['milestone'] !== '') && (int)$filters['milestone'] === $val)>{{ $val === 0 ? '0 (No Milestone)' : $val . '+ Sponsored' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted" for="awardFilter">Award Name</label>
                        <select id="awardFilter" name="award_name" class="form-select form-select-sm">
                            <option value="">All Awards</option>
                            @foreach([
                                'The Connector Award',
                                'Rising Voice Award',
                                'Community Catalyst Award',
                                'Voice of Change Award',
                                'Influencer Award',
                                'Inspiration Icon Award',
                                'Super Star Award',
                                'Global Star',
                                'Legacy Creator',
                                'Impact Creator Award',
                                'Nation Builder Award',
                                'Peers Global Hall of Fame 👑'
                            ] as $award)
                                <option value="{{ $award }}" @selected(($filters['award_name'] ?? '') === $award)>{{ $award }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                        <a href="{{ route('admin.sponsored-milestones.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle table-sm mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>
                                <a href="{{ route('admin.sponsored-milestones.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    Member Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Contact / Info</th>
                            <th>Company Name</th>
                            <th class="text-center">
                                <a href="{{ route('admin.sponsored-milestones.index', array_merge(request()->query(), ['sort' => 'total_sponsored_members', 'dir' => ($filters['sort'] ?? '') === 'total_sponsored_members' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                    Sponsored Members
                                    @if (($filters['sort'] ?? '') === 'total_sponsored_members')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short fs-6"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Current Milestone</th>
                            <th>Award Name</th>
                            <th>Recognition / Benefits</th>
                            <th>Milestone Progress</th>
                            <th class="text-end" style="padding-right: 15px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse ($members as $member)
                            @php
                                $name = $member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
                                $avatar = $member->profile_photo_url ?? ($member->profile_photo_file_id ? url('/api/v1/files/' . $member->profile_photo_file_id) : null);
                                $gradientIndex = abs(crc32((string) $member->id)) % 5;
                                $details = $member->milestoneDetails;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="peer-avatar-wrapper" style="width: 32px; height: 32px;">
                                            @if ($avatar)
                                                <img src="{{ $avatar }}" alt="{{ $name }}" class="peer-avatar-image" style="width: 32px; height: 32px; object-fit: cover; border-radius: 50%;">
                                            @else
                                                <div class="peer-avatar-placeholder bg-gradient-peer-{{ $gradientIndex }} rounded-circle text-center text-white" style="width: 32px; height: 32px; font-size: 0.8rem; line-height: 32px;">
                                                    {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark">{{ $name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column lh-sm">
                                        <span class="text-muted" style="font-size: 0.75rem;">{{ $member->email }}</span>
                                        @if ($member->phone)
                                            <span class="text-muted" style="font-size: 0.75rem;">{{ $member->phone }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="text-secondary">{{ $member->company_name ?: '—' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">{{ $member->total_sponsored_members }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $details['current_milestone'] }}</span>
                                </td>
                                <td>
                                    @if ($details['award_name'])
                                        <span class="text-primary fw-semibold">{{ $details['award_name'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($details['recognition'])
                                        <span class="text-secondary" style="font-size: 0.75rem;">{{ $details['recognition'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($details['next_milestone'])
                                        <div class="d-flex flex-column" style="width: 100px;">
                                            <div class="progress" style="height: 6px;">
                                                @php
                                                    $prevMilestone = $details['current_milestone'];
                                                    $target = $details['next_milestone'];
                                                    $progressVal = $member->total_sponsored_members - $prevMilestone;
                                                    $totalNeeded = $target - $prevMilestone;
                                                    $percent = $totalNeeded > 0 ? ($progressVal / $totalNeeded) * 100 : 0;
                                                @endphp
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted mt-1" style="font-size: 0.7rem;">{{ $details['members_remaining'] }} remaining to {{ $details['next_milestone'] }}</span>
                                        </div>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-patch-check-fill me-1"></i>Max Milestone</span>
                                    @endif
                                </td>
                                <td class="text-end" style="padding-right: 15px;">
                                    <a href="{{ route('admin.sponsored-milestones.show', $member->id) }}" class="btn btn-xs btn-outline-secondary py-1 px-2" style="font-size: 0.75rem;">
                                        <i class="bi bi-eye-fill me-1"></i>View Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted mb-2"><i class="bi bi-inbox fs-3"></i></div>
                                    <div class="fw-semibold">No sponsored milestone records found</div>
                                    <div class="small text-muted mt-1">Try adjusting your filters or search terms.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $members->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
