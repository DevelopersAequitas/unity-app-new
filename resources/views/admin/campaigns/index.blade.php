@extends('admin.layouts.app')

@section('title', 'Campaign Dashboard')

@push('styles')
<style>
    /* Ensure dropdowns rendered with fixed strategy are layered correctly above tables, cards and filters */
    .dropdown-menu {
        z-index: 1060 !important;
    }
</style>
@endpush

@section('content')
    @php
        $badge = fn ($status) => match ($status) {
            'sent' => 'success',
            'completed' => 'success',
            'failed' => 'danger',
            'partially_sent' => 'warning',
            'active' => 'info',
            'scheduled' => 'primary',
            'paused' => 'warning',
            'stopped' => 'secondary',
            'deleted' => 'dark',
            default => 'secondary',
        };
    @endphp

    @include('admin.campaigns.partials.flash')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Notifications &amp; Email Campaigns</h1>
            <div class="text-muted small">Campaign Dashboard</div>
        </div>
        <div class="d-flex gap-2">
            @if (Route::has('admin.campaigns.create'))
                <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Campaign</a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Total Campaigns', 'value' => $stats['total'], 'class' => 'primary'],
            ['label' => 'Draft Campaigns', 'value' => $stats['draft'], 'class' => 'secondary'],
            ['label' => 'Sent Campaigns', 'value' => $stats['sent'], 'class' => 'success'],
            ['label' => 'Failed Campaigns', 'value' => $stats['failed'], 'class' => 'danger'],
            ['label' => 'Total Emails Sent', 'value' => $stats['emails_sent'], 'class' => 'info'],
            ['label' => 'Total Notifications Sent', 'value' => $stats['notifications_sent'], 'class' => 'warning'],
        ] as $card)
            <div class="col-md-4 col-xl-2">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted">{{ $card['label'] }}</div>
                        <div class="h4 mb-0 text-{{ $card['class'] }}">{{ number_format($card['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters & Search Bar -->
    <form method="GET" action="{{ route('admin.campaigns.index') }}" class="mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label small text-muted fw-semibold">Search Campaigns</label>
                        <div class="input-group">
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search by campaign title..." value="{{ request('search') }}">
                            @if(request('search'))
                                <a href="{{ route('admin.campaigns.index', array_merge(request()->except('search'), ['search' => ''])) }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-semibold d-block">Status Filter</label>
                        <div class="btn-group w-100" role="group" aria-label="Campaign status filter">
                            @foreach([
                                'all' => 'All',
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'sent' => 'Sent',
                                'failed' => 'Failed'
                            ] as $key => $label)
                                <a href="{{ route('admin.campaigns.index', array_merge(request()->except('status'), ['status' => $key])) }}" 
                                   class="btn btn-sm btn-outline-primary {{ (request('status', 'all') === $key) ? 'active' : '' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                        <input type="hidden" name="status" value="{{ request('status', 'all') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Campaign Title</th>
                    <th>Type</th>
                    <th>Audience</th>
                    <th>Total Recipients</th>
                    <th>Email Sent</th>
                    <th>Notification Sent</th>
                    <th>Failed</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($campaigns as $campaign)
                    <tr>
                        <td class="fw-semibold">{{ $campaign->title }}</td>
                        <td>{{ Str::headline($campaign->campaign_type) }}</td>
                        <td>{{ Str::headline($campaign->audience_type) }}</td>
                        <td>{{ number_format($campaign->total_recipients) }}</td>
                        <td>{{ number_format($campaign->total_email_sent) }}</td>
                        <td>{{ number_format($campaign->total_notification_sent) }}</td>
                        <td>{{ number_format($campaign->total_failed) }}</td>
                        <td><span class="badge bg-{{ $badge($campaign->status) }}">{{ Str::headline($campaign->status) }}</span></td>
                        <td>{{ $campaign->formatTimestamp($campaign->sent_at) ?? '-' }}</td>
                        <td>{{ $campaign->formatTimestamp($campaign->created_at) ?? '-' }}</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton{{ $campaign->id }}" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $campaign->id }}">
                                    @php
                                        $actions = [];
                                        $status = $campaign->status;

                                        // View is always present
                                        if (in_array($status, ['sent', 'completed'], true)) {
                                            $actions[] = 'view_report';
                                        } else {
                                            $actions[] = 'view';
                                        }

                                        // Other actions based on status
                                        switch ($status) {
                                            case 'draft':
                                                $actions[] = 'edit';
                                                $actions[] = 'delete';
                                                break;
                                            case 'scheduled':
                                                $actions[] = 'edit';
                                                $actions[] = 'pause';
                                                $actions[] = 'stop';
                                                $actions[] = 'delete';
                                                break;
                                            case 'active':
                                                $actions[] = 'pause';
                                                $actions[] = 'stop';
                                                break;
                                            case 'paused':
                                                $actions[] = 'resume';
                                                $actions[] = 'stop';
                                                $actions[] = 'delete';
                                                break;
                                            case 'stopped':
                                            case 'failed':
                                                $actions[] = 'retry';
                                                $actions[] = 'delete';
                                                break;
                                            case 'sent':
                                            case 'completed':
                                                $actions[] = 'duplicate';
                                                $actions[] = 'delete';
                                                break;
                                        }

                                        // Fallback safeguard: if no actions are generated, automatically show View
                                        if (empty($actions)) {
                                            $actions[] = 'view';
                                        }
                                    @endphp

                                    @foreach ($actions as $index => $action)
                                        @if ($action === 'view' && Route::has('admin.campaigns.show'))
                                            <li><a class="dropdown-item" href="{{ route('admin.campaigns.show', $campaign) }}"><i class="bi bi-eye me-2"></i>View</a></li>
                                        @elseif ($action === 'view_report' && Route::has('admin.campaigns.show'))
                                            <li><a class="dropdown-item" href="{{ route('admin.campaigns.show', $campaign) }}"><i class="bi bi-bar-chart-fill me-2"></i>View Report</a></li>
                                        @elseif ($action === 'edit' && Route::has('admin.campaigns.edit'))
                                            <li><a class="dropdown-item" href="{{ route('admin.campaigns.edit', $campaign) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                        @elseif ($action === 'duplicate' && Route::has('admin.campaigns.duplicate'))
                                            <li>
                                                <form method="POST" action="{{ route('admin.campaigns.duplicate', $campaign) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-files me-2"></i>Duplicate</button>
                                                </form>
                                            </li>
                                        @elseif ($action === 'pause' && Route::has('admin.campaigns.pause'))
                                            <li>
                                                <button class="dropdown-item text-warning" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#campaignConfirmModal"
                                                    data-action-url="{{ route('admin.campaigns.pause', $campaign) }}"
                                                    data-title="Pause Campaign"
                                                    data-message="Are you sure you want to pause the campaign '{{ $campaign->title }}'? Future runs will not execute until resumed."
                                                    data-button-text="Pause"
                                                    data-button-class="btn-warning">
                                                    <i class="bi bi-pause-fill me-2"></i>Pause
                                                </button>
                                            </li>
                                        @elseif ($action === 'resume' && Route::has('admin.campaigns.resume'))
                                            <li>
                                                <button class="dropdown-item text-success" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#campaignConfirmModal"
                                                    data-action-url="{{ route('admin.campaigns.resume', $campaign) }}"
                                                    data-title="Resume Campaign"
                                                    data-message="Are you sure you want to resume the campaign '{{ $campaign->title }}'? Sending will continue from the next valid occurrence."
                                                    data-button-text="Resume"
                                                    data-button-class="btn-success">
                                                    <i class="bi bi-play-fill me-2"></i>Resume
                                                </button>
                                            </li>
                                        @elseif ($action === 'stop' && Route::has('admin.campaigns.stop'))
                                            <li>
                                                <button class="dropdown-item text-warning" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#campaignConfirmModal"
                                                    data-action-url="{{ route('admin.campaigns.stop', $campaign) }}"
                                                    data-title="Stop Campaign"
                                                    data-message="Are you sure you want to stop the campaign '{{ $campaign->title }}'? All future scheduled runs will be cancelled, preserving history."
                                                    data-button-text="Stop"
                                                    data-button-class="btn-warning">
                                                    <i class="bi bi-stop-fill me-2"></i>Stop Campaign
                                                </button>
                                            </li>
                                        @elseif ($action === 'retry' && Route::has('admin.campaigns.retry'))
                                            <li>
                                                <button class="dropdown-item text-success" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#campaignConfirmModal"
                                                    data-action-url="{{ route('admin.campaigns.retry', $campaign) }}"
                                                    data-title="Retry Campaign"
                                                    data-message="Are you sure you want to retry sending the campaign '{{ $campaign->title }}'?"
                                                    data-button-text="Retry"
                                                    data-button-class="btn-success">
                                                    <i class="bi bi-arrow-clockwise me-2"></i>Retry
                                                </button>
                                            </li>
                                        @elseif ($action === 'delete' && Route::has('admin.campaigns.destroy'))
                                            @if ($index > 0)
                                                <li><hr class="dropdown-divider"></li>
                                            @endif
                                            <li>
                                                <button class="dropdown-item text-danger" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#campaignConfirmModal"
                                                    data-action-url="{{ route('admin.campaigns.destroy', $campaign) }}"
                                                    data-title="Delete Campaign"
                                                    data-message="Are you sure you want to delete the campaign '{{ $campaign->title }}'? This will soft-delete the campaign record, preserving logs and statistics."
                                                    data-button-text="Delete"
                                                    data-button-class="btn-danger"
                                                    data-method="DELETE">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </button>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No campaigns yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $campaigns->links() }}</div>

    <!-- Reusable Confirmation Modal -->
    <div class="modal fade" id="campaignConfirmModal" tabindex="-1" aria-labelledby="campaignConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="confirmForm" method="POST" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="campaignConfirmModalLabel">Confirm Action</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="confirmModalBody">
                        Are you sure you want to perform this action?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmSubmitBtn">Proceed</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const confirmModal = document.getElementById('campaignConfirmModal');
            if (confirmModal) {
                confirmModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const actionUrl = button.getAttribute('data-action-url');
                    const title = button.getAttribute('data-title');
                    const message = button.getAttribute('data-message');
                    const buttonText = button.getAttribute('data-button-text');
                    const buttonClass = button.getAttribute('data-button-class') || 'btn-primary';
                    const method = button.getAttribute('data-method') || 'POST';

                    const form = confirmModal.querySelector('#confirmForm');
                    form.setAttribute('action', actionUrl);
                    
                    // Handle dynamic method matching (e.g. DELETE requests)
                    let methodField = form.querySelector('input[name="_method"]');
                    if (method === 'DELETE') {
                        if (!methodField) {
                            methodField = document.createElement('input');
                            methodField.setAttribute('type', 'hidden');
                            methodField.setAttribute('name', '_method');
                            methodField.setAttribute('value', 'DELETE');
                            form.appendChild(methodField);
                        }
                    } else {
                        if (methodField) {
                            methodField.remove();
                        }
                    }

                    confirmModal.querySelector('#campaignConfirmModalLabel').textContent = title;
                    confirmModal.querySelector('#confirmModalBody').textContent = message;
                    
                    const submitBtn = confirmModal.querySelector('#confirmSubmitBtn');
                    submitBtn.textContent = buttonText;
                    submitBtn.className = 'btn ' + buttonClass;
                });
            }

        });
    </script>
@endsection
