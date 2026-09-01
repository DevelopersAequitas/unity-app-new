@extends('admin.layouts.app')

@section('title', 'Campaign Dashboard')

@include('admin.partials.grid-head')

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
        $badgeClass = fn ($status) => match ($status) {
            'sent', 'completed' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
            'failed' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
            'partially_sent', 'paused' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
            'active', 'scheduled' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200',
            default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
        };
    @endphp

    @include('admin.campaigns.partials.flash')

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Notifications & Email Campaigns</h2>
                <p class="text-xs t3 m-0 mt-0.5">Campaign Dashboard & Metrics Overview</p>
            </div>
            @if (Route::has('admin.campaigns.create'))
                <a href="{{ route('admin.campaigns.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                    <i class="bi bi-plus-lg admin-icon me-1" aria-hidden="true"></i>Create Campaign
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach ([
                ['label' => 'Total Campaigns', 'value' => $stats['total'], 'color' => 'text-indigo-600'],
                ['label' => 'Draft Campaigns', 'value' => $stats['draft'], 'color' => 't2'],
                ['label' => 'Sent Campaigns', 'value' => $stats['sent'], 'color' => 'text-emerald-600'],
                ['label' => 'Failed Campaigns', 'value' => $stats['failed'], 'color' => 'text-rose-600'],
                ['label' => 'Total Emails Sent', 'value' => $stats['emails_sent'], 'color' => 'text-sky-600'],
                ['label' => 'Total Notifications', 'value' => $stats['notifications_sent'], 'color' => 'text-amber-600'],
            ] as $card)
                <div class="p-3 rounded-lg border bs surface-2">
                    <div class="text-[11px] uppercase tracking-wider font-semibold t3 mb-1">{{ $card['label'] }}</div>
                    <div class="text-lg font-bold {{ $card['color'] }}">{{ number_format($card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <!-- Filters & Search Bar -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.campaigns.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label for="search" class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search Campaigns</label>
                    <input type="text" name="search" id="search" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by campaign title..." value="{{ request('search') }}">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        @foreach([
                            'all' => 'All Statuses',
                            'draft' => 'Draft',
                            'scheduled' => 'Scheduled',
                            'active' => 'Active',
                            'paused' => 'Paused',
                            'sent' => 'Sent',
                            'failed' => 'Failed'
                        ] as $key => $label)
                            <option value="{{ $key }}" @selected(request('status', 'all') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring flex-1">Search</button>
                    <a href="{{ route('admin.campaigns.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Campaign Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Audience</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Recipients</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Email Sent</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Notification Sent</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Failed</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Sent At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($campaigns as $campaign)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs font-semibold t1">{{ $campaign->title }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ Str::headline($campaign->campaign_type) }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ Str::headline($campaign->audience_type) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($campaign->total_recipients) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($campaign->total_email_sent) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($campaign->total_notification_sent) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium text-rose-600">{{ number_format($campaign->total_failed) }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="{{ $badgeClass($campaign->status) }}">{{ Str::headline($campaign->status) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $campaign->formatTimestamp($campaign->sent_at) ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $campaign->formatTimestamp($campaign->created_at) ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="dropdown">
                                        <button class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition dropdown-toggle" type="button" id="dropdownMenuButton{{ $campaign->id }}" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dropdownMenuButton{{ $campaign->id }}">
                                            @php
                                                $actions = [];
                                                $status = $campaign->status;

                                                if (in_array($status, ['sent', 'completed'], true)) {
                                                    $actions[] = 'view_report';
                                                } else {
                                                    $actions[] = 'view';
                                                }

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

                                                if (empty($actions)) {
                                                    $actions[] = 'view';
                                                }
                                            @endphp

                                            @foreach ($actions as $index => $action)
                                                @if ($action === 'view' && Route::has('admin.campaigns.show'))
                                                    <li><a class="dropdown-item text-xs" href="{{ route('admin.campaigns.show', $campaign) }}">View</a></li>
                                                @elseif ($action === 'view_report' && Route::has('admin.campaigns.show'))
                                                    <li><a class="dropdown-item text-xs" href="{{ route('admin.campaigns.show', $campaign) }}">View Report</a></li>
                                                @elseif ($action === 'edit' && Route::has('admin.campaigns.edit'))
                                                    <li><a class="dropdown-item text-xs" href="{{ route('admin.campaigns.edit', $campaign) }}">Edit</a></li>
                                                @elseif ($action === 'duplicate' && Route::has('admin.campaigns.duplicate'))
                                                    <li>
                                                        <form method="POST" action="{{ route('admin.campaigns.duplicate', $campaign) }}" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item text-xs">Duplicate</button>
                                                        </form>
                                                    </li>
                                                @elseif ($action === 'pause' && Route::has('admin.campaigns.pause'))
                                                    <li>
                                                        <button class="dropdown-item text-xs text-warning" type="button" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#campaignConfirmModal"
                                                            data-action-url="{{ route('admin.campaigns.pause', $campaign) }}"
                                                            data-title="Pause Campaign"
                                                            data-message="Are you sure you want to pause the campaign '{{ $campaign->title }}'?"
                                                            data-button-text="Pause"
                                                            data-button-class="btn-warning">
                                                            Pause
                                                        </button>
                                                    </li>
                                                @elseif ($action === 'resume' && Route::has('admin.campaigns.resume'))
                                                    <li>
                                                        <button class="dropdown-item text-xs text-success" type="button" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#campaignConfirmModal"
                                                            data-action-url="{{ route('admin.campaigns.resume', $campaign) }}"
                                                            data-title="Resume Campaign"
                                                            data-message="Are you sure you want to resume the campaign '{{ $campaign->title }}'?"
                                                            data-button-text="Resume"
                                                            data-button-class="btn-success">
                                                            Resume
                                                        </button>
                                                    </li>
                                                @elseif ($action === 'stop' && Route::has('admin.campaigns.stop'))
                                                    <li>
                                                        <button class="dropdown-item text-xs text-warning" type="button" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#campaignConfirmModal"
                                                            data-action-url="{{ route('admin.campaigns.stop', $campaign) }}"
                                                            data-title="Stop Campaign"
                                                            data-message="Are you sure you want to stop the campaign '{{ $campaign->title }}'?"
                                                            data-button-text="Stop"
                                                            data-button-class="btn-warning">
                                                            Stop Campaign
                                                        </button>
                                                    </li>
                                                @elseif ($action === 'retry' && Route::has('admin.campaigns.retry'))
                                                    <li>
                                                        <button class="dropdown-item text-xs text-success" type="button" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#campaignConfirmModal"
                                                            data-action-url="{{ route('admin.campaigns.retry', $campaign) }}"
                                                            data-title="Retry Campaign"
                                                            data-message="Are you sure you want to retry sending the campaign '{{ $campaign->title }}'?"
                                                            data-button-text="Retry"
                                                            data-button-class="btn-success">
                                                            Retry
                                                        </button>
                                                    </li>
                                                @elseif ($action === 'delete' && Route::has('admin.campaigns.destroy'))
                                                    @if ($index > 0)
                                                        <li><hr class="dropdown-divider"></li>
                                                    @endif
                                                    <li>
                                                        <button class="dropdown-item text-xs text-danger" type="button" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#campaignConfirmModal"
                                                            data-action-url="{{ route('admin.campaigns.destroy', $campaign) }}"
                                                            data-title="Delete Campaign"
                                                            data-message="Are you sure you want to delete the campaign '{{ $campaign->title }}'?"
                                                            data-button-text="Delete"
                                                            data-button-class="btn-danger"
                                                            data-method="DELETE">
                                                            Delete
                                                        </button>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-8 text-xs t3">No campaigns found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $campaigns->links() }}
            </div>
        </div>
    </div>


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
