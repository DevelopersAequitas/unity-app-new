@extends('admin.layouts.app')
@section('title', 'Notification Campaigns')

@section('content')
@include('admin.notifications._helpers')
@include('admin.notifications._styles')
@include('admin.notifications._flash')

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Notification Campaigns</h1>
        <div class="text-muted small">Manage scheduled and manual notification campaigns.</div>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('admin.notifications.campaigns.seed-defaults') }}" class="m-0">
            @csrf
            <button class="btn btn-outline-success" onclick="return confirm('Seed/update the recommended 10 campaigns?')">
                <i class="bi bi-database-add me-1"></i>Seed Default Campaigns
            </button>
        </form>
        <a class="btn btn-primary" href="{{ route('admin.notifications.campaigns.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Create Campaign
        </a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2">
            <div class="col-md-3"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search name/code"></div>
            <div class="col-md-2">
                <select class="form-select" name="category">
                    <option value="">Category</option>
                    @foreach($filters['categories'] as $category)
                        <option @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="channel">
                    <option value="">Channel</option>
                    @foreach($filters['channels'] as $channel)
                        <option @selected(request('channel') === $channel)>{{ $channel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="priority">
                    <option value="">Priority</option>
                    @foreach($filters['priorities'] as $priority)
                        <option @selected(request('priority') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">Status</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle notification-admin-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th><th>Name</th><th>Category</th><th>Channel</th><th>Trigger</th><th>Frequency</th><th>Priority</th><th>Audience</th><th>Daily</th><th>Cooldown</th><th>Status</th><th>Created</th><th class="action-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                    <tr>
                        <td><code>{{ $campaign->code }}</code></td>
                        <td class="fw-semibold text-truncate" style="max-width: 180px;" title="{{ $campaign->name }}">{{ $campaign->name }}</td>
                        <td>{{ $campaign->category }}</td>
                        <td>{{ $campaign->channel }}</td>
                        <td>{{ $campaign->trigger_type }}</td>
                        <td>{{ $campaign->frequency ?: '-' }}</td>
                        <td><span class="badge bg-{{ notification_admin_priority_badge($campaign->priority) }}">{{ $campaign->priority }}</span></td>
                        <td>{{ $campaign->audience_type ?: '-' }}</td>
                        <td>{{ $campaign->daily_limit ?? '-' }}</td>
                        <td>{{ $campaign->cooldown_hours ?? '-' }}</td>
                        <td><span class="badge bg-{{ $campaign->is_active ? 'success' : 'secondary' }}">{{ $campaign->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ optional($campaign->created_at)->format('d M Y') }}</td>
                        <td class="text-nowrap action-cell">
                            <div class="d-flex align-items-center gap-1 flex-nowrap">
                                <a href="{{ route('admin.notifications.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-outline-secondary action-btn">Edit</a>
                                <form method="POST" action="{{ route('admin.notifications.campaigns.toggle', $campaign->id) }}" class="d-inline m-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $campaign->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} action-btn">
                                        {{ $campaign->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary action-btn" data-bs-toggle="modal" data-bs-target="#preview-{{ $campaign->id }}">Preview</button>
                                <form method="POST" action="{{ route('admin.notifications.campaigns.run', $campaign->id) }}" class="d-inline m-0" onsubmit="return confirm('Run this campaign now?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success action-btn" title="Run Now">Run</button>
                                </form>
                                <a href="{{ route('admin.notifications.send-test', ['type' => $campaign->code, 'title' => $campaign->title_template, 'body' => $campaign->body_template, 'screen' => $campaign->tap_screen]) }}" class="btn btn-sm btn-outline-info action-btn" title="Test Send">Test</a>
                            </div>
                            @include('admin.notifications.campaigns._preview-modal', ['campaign' => $campaign, 'modalId' => 'preview-' . $campaign->id])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">No campaigns found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $campaigns->links() }}</div>
@endsection
