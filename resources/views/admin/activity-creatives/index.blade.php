@extends('admin.layouts.app')

@section('title', 'Activity Creatives')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Activity Creatives</h1>
        <p class="text-muted small mb-0">Automated and generated peer activity graphics, welcome creatives, and timeline certificates.</p>
    </div>
    <span class="badge bg-light text-dark border px-3 py-2 fs-6">Total: {{ number_format($items->total()) }}</span>
</div>

<!-- Quick Filter Pills -->
<div class="d-flex gap-2 flex-wrap mb-3">
    <a href="{{ route('admin.activity-creatives.index') }}" class="btn btn-sm {{ empty($filters['activity_type']) ? 'btn-primary' : 'btn-outline-secondary' }}">
        All Creatives
    </a>
    <a href="{{ route('admin.activity-creatives.index', array_merge(request()->query(), ['activity_type' => 'welcome'])) }}" class="btn btn-sm {{ ($filters['activity_type'] ?? '') === 'welcome' ? 'btn-primary' : 'btn-outline-secondary' }}">
        🎉 Welcome Creatives
    </a>
    <a href="{{ route('admin.activity-creatives.index', array_merge(request()->query(), ['activity_type' => 'badge'])) }}" class="btn btn-sm {{ ($filters['activity_type'] ?? '') === 'badge' ? 'btn-primary' : 'btn-outline-secondary' }}">
        🏅 Milestone Badges
    </a>
    <a href="{{ route('admin.activity-creatives.index', array_merge(request()->query(), ['activity_type' => 'introduction'])) }}" class="btn btn-sm {{ ($filters['activity_type'] ?? '') === 'introduction' ? 'btn-primary' : 'btn-outline-secondary' }}">
        🤝 Introduced Peers
    </a>
</div>

<form class="card card-body mb-3 shadow-xs" method="GET">
    <div class="row g-2">
        <div class="col-md-3">
            <input class="form-control" name="q" placeholder="Search by member name or email..." value="{{ $filters['q'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <select class="form-select" name="activity_type">
                <option value="">All Activity Types</option>
                <option value="welcome" {{ ($filters['activity_type'] ?? '') === 'welcome' ? 'selected' : '' }}>Welcome Creative</option>
                <option value="badge" {{ ($filters['activity_type'] ?? '') === 'badge' ? 'selected' : '' }}>Milestone Badge</option>
                <option value="introduction" {{ ($filters['activity_type'] ?? '') === 'introduction' ? 'selected' : '' }}>Introduced Peer</option>
                <option value="life_impact" {{ ($filters['activity_type'] ?? '') === 'life_impact' ? 'selected' : '' }}>Life Impact</option>
                <option value="certificate" {{ ($filters['activity_type'] ?? '') === 'certificate' ? 'selected' : '' }}>Certificate</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" name="from_date" title="From Date" value="{{ $filters['from_date'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" name="to_date" title="To Date" value="{{ $filters['to_date'] ?? '' }}">
        </div>
        <div class="col-md-2">
            <select class="form-select" name="status">
                <option value="">All Statuses</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 100px;">Creative</th>
                    <th>User / Peer</th>
                    <th>Activity Type</th>
                    <th>Title & Description</th>
                    <th>Timeline Post</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $userName = $item->user?->display_name ?? trim(($item->user?->first_name ?? '').' '.($item->user?->last_name ?? '')) ?: ($item->user?->email ?? '—');
                @endphp
                <tr>
                    <td>
                        @if($item->creative_url)
                            <a href="{{ $item->creative_url }}" target="_blank" title="View Full Creative">
                                <img src="{{ $item->creative_url }}" alt="Creative" class="rounded shadow-xs border" style="width:72px;height:90px;object-fit:cover;">
                            </a>
                        @else
                            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width:72px;height:90px;font-size:10px;">
                                No Image
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($item->user)
                            <a href="{{ route('admin.users.show', $item->user->id) }}" class="fw-semibold text-decoration-none">
                                {{ $userName }}
                            </a>
                            <div class="small text-muted">{{ $item->user->email }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($item->activity_type === 'welcome')
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-stars me-1"></i> Welcome
                            </span>
                        @elseif($item->activity_type === 'badge' || $item->activity_type === 'milestone')
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-award me-1"></i> Milestone
                            </span>
                        @elseif($item->activity_type === 'introduction')
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                <i class="bi bi-people me-1"></i> Introduction
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border">
                                {{ ucfirst((string) $item->activity_type) }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-medium text-dark">{{ $item->title ?? 'Activity Creative' }}</div>
                        @if($item->description)
                            <small class="text-muted d-block text-truncate" style="max-width: 320px;" title="{{ $item->description }}">
                                {{ $item->description }}
                            </small>
                        @endif
                    </td>
                    <td>
                        @if($item->post_id)
                            <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" href="{{ url('/admin/posts/'.$item->post_id) }}" target="_blank">
                                <i class="bi bi-newspaper"></i> View Post
                            </a>
                        @else
                            <span class="text-muted small">No Post</span>
                        @endif
                    </td>
                    <td>
                        <div class="small fw-semibold">{{ optional($item->created_at)->format('d M Y') }}</div>
                        <div class="small text-muted">{{ optional($item->created_at)->format('h:i A') }}</div>
                    </td>
                    <td>
                        @if($item->status === 'active')
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">{{ ucfirst((string) $item->status) }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            @if($item->creative_url)
                                <a class="btn btn-outline-secondary" href="{{ $item->creative_url }}" download="creative_{{ $item->id }}.png" target="_blank" title="Download Creative">
                                    <i class="bi bi-download"></i>
                                </a>
                            @endif
                            @if($item->user)
                                <a class="btn btn-outline-info" href="{{ route('admin.users.show', $item->user->id) }}" title="View Peer Profile">
                                    <i class="bi bi-person"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-images display-6 d-block mb-2 text-secondary"></i>
                        No activity creatives found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
