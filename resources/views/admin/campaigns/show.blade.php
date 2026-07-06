@extends('admin.layouts.app')

@section('title', 'Campaign Report')

@section('content')
    @include('admin.campaigns.partials.flash')
    @php $badge = fn ($status) => match ($status) { 'sent' => 'success', 'failed' => 'danger', 'partially_sent' => 'warning', default => 'secondary' }; @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $campaign->title }}</h1>
            <div class="text-muted small">Campaign Detail / Report</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">Back</a>
            @if ($campaign->isEditable())
                <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-outline-primary">Edit</a>
                <form method="POST" action="{{ route('admin.campaigns.send', $campaign) }}" onsubmit="return confirm('Send this campaign now? This cannot be undone.');">
                    @csrf
                    <button class="btn btn-success">Send Campaign</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Campaign Details</h2>
            <dl class="row mb-0 small">
                <dt class="col-5">Type</dt><dd class="col-7">{{ Str::headline($campaign->campaign_type) }}</dd>
                <dt class="col-5">Audience</dt><dd class="col-7">{{ Str::headline($campaign->audience_type) }}</dd>
                <dt class="col-5">Status</dt><dd class="col-7"><span class="badge bg-{{ $badge($campaign->status) }}">{{ Str::headline($campaign->status) }}</span></dd>
                <dt class="col-5">Sent At</dt><dd class="col-7">{{ $campaign->formatTimestamp($campaign->sent_at) ?? '-' }}</dd>
                <dt class="col-5">Created At</dt><dd class="col-7">{{ $campaign->formatTimestamp($campaign->created_at) ?? '-' }}</dd>
                
                @if($campaign->schedule)
                    <dt class="col-5 border-top pt-2 mt-2">Schedule Type</dt>
                    <dd class="col-7 border-top pt-2 mt-2 fw-semibold text-primary">{{ Str::headline($campaign->schedule->schedule_type) }}</dd>
                    
                    @if($campaign->schedule->schedule_type !== 'immediately')
                        <dt class="col-5">Next Run</dt>
                        <dd class="col-7 text-success">{{ $campaign->schedule->next_run_at ? $campaign->formatTimestamp($campaign->schedule->next_run_at) : 'Never' }}</dd>
                        
                        <dt class="col-5">Timezone</dt>
                        <dd class="col-7">{{ $campaign->schedule->timezone }}</dd>
                        
                        @if($campaign->schedule->schedule_type === 'recurring')
                            <dt class="col-5">Recurrence</dt>
                            <dd class="col-7">{{ Str::headline($campaign->schedule->recurrence_type) }} 
                                @if($campaign->schedule->recurrence_type === 'weekly' && $campaign->schedule->weekdays)
                                    ({{ $campaign->schedule->weekdays }})
                                  @endif
                            </dd>
                        @endif
                    @endif
                @endif
            </dl>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Totals</h2>
            <div class="row text-center g-2">
                <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Recipients</div><div class="h5 mb-0">{{ number_format($campaign->total_recipients) }}</div></div></div>
                <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Emails</div><div class="h5 mb-0">{{ number_format($campaign->total_email_sent) }}</div></div></div>
                <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Notifications</div><div class="h5 mb-0">{{ number_format($campaign->total_notification_sent) }}</div></div></div>
                <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Failed</div><div class="h5 mb-0">{{ number_format($campaign->total_failed) }}</div></div></div>
            </div>
        </div></div></div>
        <div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Selected Filters</h2>
            @if (! empty($campaign->email_template_snapshot) || $campaign->emailTemplate)
                @php
                    $templateName = data_get($campaign->email_template_snapshot, 'name', $campaign->emailTemplate?->name);
                    $templateType = data_get($campaign->email_template_snapshot, 'template_type', $campaign->emailTemplate?->template_type);
                @endphp
                <div class="mb-2">
                    <span class="text-muted small d-block">Selected Email Template</span>
                    <div class="d-flex align-items-center gap-2">
                        <div class="campaign-template-mini campaign-template-mini-{{ $templateType }}"><span></span><span></span><span></span><span></span></div>
                        <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $templateName ?? 'Simple Text' }}</span>
                    </div>
                </div>
            @endif
            @if (! empty($campaign->pamphlet_snapshot))
                <div class="mb-2">
                    <span class="text-muted small d-block">Selected Pamphlet</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $campaign->pamphlet_snapshot['title'] ?? $campaign->pamphlet_id }}</span>
                    @if (! empty($campaign->pamphlet_snapshot['image_url']))
                        <div class="small mt-1 text-break">Image: {{ $campaign->pamphlet_snapshot['image_url'] }}</div>
                    @endif
                </div>
            @endif
            @if (! empty($filterSummary['business_categories']))
                <div class="mb-2">
                    <span class="text-muted small d-block">Business Categories</span>
                    @foreach ($filterSummary['business_categories'] as $categoryName)
                        <span class="badge bg-light text-dark border me-1 mb-1">{{ $categoryName }}</span>
                    @endforeach
                </div>
            @endif
            @if($campaign->audience_type === 'specific_members' && !empty($filterSummary['selected_users']))
                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">Selected Members</span>
                    <div class="table-responsive border rounded" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.75rem;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Member ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filterSummary['selected_users'] as $u)
                                    <tr>
                                        <td>{{ $u['name'] }}</td>
                                        <td>{{ $u['email'] ?? '-' }}</td>
                                        <td class="text-muted">{{ $u['id'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <pre class="bg-light border rounded p-2 small mb-0" style="white-space:pre-wrap;">{{ json_encode($campaign->filters ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div></div></div>
    </div>

    @if(!empty($executionHistory))
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light"><strong>Recurring Execution History</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Run #</th>
                            <th>Scheduled Time</th>
                            <th>Actual Execution Time</th>
                            <th>Status</th>
                            <th>Emails Sent</th>
                            <th>Notifications Sent</th>
                            <th>Failed</th>
                            <th>Triggered By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($executionHistory as $run)
                            <tr>
                                <td class="fw-semibold">{{ $run['run_number'] }}</td>
                                <td>{{ $run['scheduled_time'] }}</td>
                                <td>{{ $run['actual_time'] }}</td>
                                <td>
                                    @php
                                        $runStatusLower = strtolower($run['status']);
                                        $runBadge = match ($runStatusLower) {
                                            'success' => 'success',
                                            'failed' => 'danger',
                                            'processing' => 'info',
                                            'pending' => 'secondary',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $runBadge }}">{{ $run['status'] }}</span>
                                </td>
                                <td>{{ is_numeric($run['emails_sent']) ? number_format($run['emails_sent']) : $run['emails_sent'] }}</td>
                                <td>{{ is_numeric($run['notifications_sent']) ? number_format($run['notifications_sent']) : $run['notifications_sent'] }}</td>
                                <td class="{{ is_numeric($run['failed']) && $run['failed'] > 0 ? 'text-danger' : '' }}">
                                    {{ is_numeric($run['failed']) ? number_format($run['failed']) : $run['failed'] }}
                                </td>
                                <td><span class="text-muted">{{ $run['triggered_by'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header"><strong>Recipient Logs</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member Name</th>
                        <th>Email</th>
                        <th>Scheduled Time</th>
                        <th>Sent Time</th>
                        <th>Email Status</th>
                        <th>Notification Status</th>
                        <th class="campaign-error-column">Error Message</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($recipients as $recipient)
                    <tr>
                        <td>{{ $recipient->user?->adminDisplayName() ?? $recipient->user_id }}</td>
                        <td>{{ $recipient->email ?? '-' }}</td>
                        <td>
                            @if (isset($recipient->delivery) && $recipient->delivery->scheduled_at)
                                {{ $campaign->formatTimestamp($recipient->delivery->scheduled_at) }}
                            @elseif (isset($recipient->scheduled_at))
                                {{ $campaign->formatTimestamp($recipient->scheduled_at) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ($recipient->sent_at)
                                {{ $campaign->formatTimestamp($recipient->sent_at) }}
                            @else
                                -
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $badge($recipient->email_status) }}">{{ Str::headline($recipient->email_status) }}</span></td>
                        <td><span class="badge bg-{{ $badge($recipient->notification_status) }}">{{ Str::headline($recipient->notification_status) }}</span></td>
                        <td class="text-danger small campaign-error-column">{{ $recipient->error_message ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No recipient logs yet. Logs appear after sending.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $recipients->links() }}</div>
@endsection


@push('styles')
    <style>
        .campaign-error-column {
            max-width: 360px;
            white-space: normal;
            word-break: break-word;
        }
        .campaign-template-mini {
            width: 52px;
            height: 38px;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            background: #f8fafc;
            padding: 4px;
            display: grid;
            gap: 3px;
        }
        .campaign-template-mini span {
            display: block;
            border-radius: 4px;
            background: #dbeafe;
        }
        .campaign-template-mini-blank span { display: none; }
        .campaign-template-mini-one_two_column,
        .campaign-template-mini-one_two_column_alternate,
        .campaign-template-mini-one_two_one_two_column { grid-template-columns: 1fr 1fr; }
        .campaign-template-mini-one_three_column { grid-template-columns: repeat(3, 1fr); }
    </style>
@endpush
