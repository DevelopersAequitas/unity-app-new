@extends('admin.layouts.app')

@section('title', 'Email Log Detail')

@section('content')
    @php
        $payload = is_array($emailLog->payload) ? $emailLog->payload : [];
        $statusBadgeClass = match ((string) $emailLog->status) {
            'sent' => 'bg-success-subtle text-success border border-success-subtle',
            'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'queued', 'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
        $value = static fn ($v) => filled($v) ? $v : '—';
        $json = static fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $attachments = data_get($payload, 'attachments', []);
        if (! is_array($attachments)) { $attachments = []; }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Email Log #{{ $emailLog->id }}</h1>
        <a href="{{ route('admin.email-logs.index', request()->except('id')) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Email Information</strong></div><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><span class="text-muted small d-block">Log ID</span>{{ $emailLog->id }}</div><div class="col-md-3"><span class="text-muted small d-block">Subject</span>{{ $value($emailLog->subject) }}</div><div class="col-md-3"><span class="text-muted small d-block">Template Key</span>{{ $value($emailLog->template_key) }}</div><div class="col-md-3"><span class="text-muted small d-block">Source Module</span>{{ $value($emailLog->source_module) }}</div>
        <div class="col-md-3"><span class="text-muted small d-block">Status</span><span class="badge {{ $statusBadgeClass }}">{{ ucfirst((string) $emailLog->status) }}</span></div><div class="col-md-3"><span class="text-muted small d-block">Mail Provider</span>{{ $value($emailLog->mail_provider) }}</div><div class="col-md-3"><span class="text-muted small d-block">Queue ID</span>{{ $value(data_get($payload, 'queue_id')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Message ID</span>{{ $value(data_get($payload, 'message_id')) }}</div>
        <div class="col-md-3"><span class="text-muted small d-block">Sent At</span>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</div><div class="col-md-3"><span class="text-muted small d-block">Created At</span>{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div><div class="col-md-3"><span class="text-muted small d-block">Updated At</span>{{ optional($emailLog->updated_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
    </div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Recipient Information</strong></div><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><span class="text-muted small d-block">Recipient Name</span>{{ $value($emailLog->to_name) }}</div><div class="col-md-3"><span class="text-muted small d-block">Recipient Email</span>{{ $value($emailLog->to_email) }}</div><div class="col-md-3"><span class="text-muted small d-block">User ID</span>{{ $value($emailLog->user_id) }}</div><div class="col-md-3"><span class="text-muted small d-block">User Type</span>{{ $value(data_get($payload, 'user_type', 'User')) }}</div>
    </div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Trigger Information</strong></div><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><span class="text-muted small d-block">Triggered By</span>{{ $emailLog->triggered_by_label }}</div><div class="col-md-3"><span class="text-muted small d-block">Trigger User Name</span>{{ $value($emailLog->trigger_user_name) }}</div><div class="col-md-3"><span class="text-muted small d-block">Trigger User Email</span>{{ $value($emailLog->trigger_user_email) }}</div><div class="col-md-3"><span class="text-muted small d-block">Trigger User Role</span>{{ $value($emailLog->trigger_user_role) }}</div>
        <div class="col-md-3"><span class="text-muted small d-block">Trigger User ID</span>{{ $value($emailLog->source_id ?: $emailLog->user_id) }}</div><div class="col-md-3"><span class="text-muted small d-block">IP Address</span>{{ $value(data_get($payload, 'ip_address')) }}</div><div class="col-md-6"><span class="text-muted small d-block">User Agent</span>{{ $value(data_get($payload, 'user_agent')) }}</div>
    </div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Admin Information</strong></div><div class="card-body"><div class="row g-3">
        @if ($emailLog->source_type === 'admin' || data_get($payload, 'admin'))
            <div class="col-md-3"><span class="text-muted small d-block">Admin Name</span>{{ $value(data_get($payload, 'admin.name', $emailLog->trigger_user_name)) }}</div><div class="col-md-3"><span class="text-muted small d-block">Admin Email</span>{{ $value(data_get($payload, 'admin.email', $emailLog->trigger_user_email)) }}</div><div class="col-md-3"><span class="text-muted small d-block">Admin Role</span>{{ $value(data_get($payload, 'admin.role', $emailLog->trigger_user_role)) }}</div><div class="col-md-3"><span class="text-muted small d-block">Admin ID</span>{{ $value(data_get($payload, 'admin.id', $emailLog->source_id)) }}</div>
            <div class="col-md-3"><span class="text-muted small d-block">Session ID</span>{{ $value(data_get($payload, 'admin.session_id')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Login Time</span>{{ $value(data_get($payload, 'admin.login_time')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Last Activity</span>{{ $value(data_get($payload, 'admin.last_activity')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Admin IP Address</span>{{ $value(data_get($payload, 'admin.ip_address', data_get($payload, 'ip_address'))) }}</div>
        @else
            <div class="col-12 text-muted">System</div>
        @endif
    </div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Email Content</strong></div><div class="card-body">
        <div class="mb-2"><span class="text-muted small d-block">Subject</span>{{ $value($emailLog->subject) }}</div>
        @if (! empty($emailLog->body_html))<div class="border rounded mb-3" style="min-height: 360px; overflow:hidden;"><iframe title="Email HTML Preview" sandbox srcdoc="{{ $emailLog->body_html }}" style="width:100%; min-height:360px; border:0;"></iframe></div>@else<div class="text-muted mb-3">No rendered HTML preview available.</div>@endif
        <details class="mb-3"><summary>Raw HTML</summary><pre class="bg-light border rounded p-3 small mt-2" style="white-space: pre-wrap; max-height:360px; overflow:auto;">{{ $emailLog->body_html ?: '—' }}</pre></details>
        <details><summary>Plain Text Version</summary><pre class="bg-light border rounded p-3 small mt-2" style="white-space: pre-wrap;">{{ data_get($payload, 'plain_text', '—') }}</pre></details>
    </div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Template Information</strong></div><div class="card-body"><div class="row g-3"><div class="col-md-4"><span class="text-muted small d-block">Template Name</span>{{ $value(data_get($payload, 'template_name')) }}</div><div class="col-md-4"><span class="text-muted small d-block">Template Key</span>{{ $value($emailLog->template_key) }}</div><div class="col-md-4"><span class="text-muted small d-block">Template Version</span>{{ $value(data_get($payload, 'template_version')) }}</div><div class="col-12"><span class="text-muted small d-block">Variables Used</span><pre class="bg-light border rounded p-3 small mb-0">{{ $json(data_get($payload, 'variables', $payload)) }}</pre></div></div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Attachments</strong></div><div class="card-body"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>File Size</th><th>MIME Type</th><th>Download</th></tr></thead><tbody>@forelse ($attachments as $attachment)<tr><td>{{ $value(data_get($attachment, 'name')) }}</td><td>{{ $value(data_get($attachment, 'size')) }}</td><td>{{ $value(data_get($attachment, 'mime_type')) }}</td><td>@if (data_get($attachment, 'url'))<a href="{{ data_get($attachment, 'url') }}">Download</a>@else — @endif</td></tr>@empty<tr><td colspan="4" class="text-muted text-center">No attachments logged.</td></tr>@endforelse</tbody></table></div></div></div>

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Delivery Information</strong></div><div class="card-body"><div class="row g-3"><div class="col-md-3"><span class="text-muted small d-block">Mail Driver</span>{{ $value(data_get($payload, 'mail_driver', $emailLog->mail_provider)) }}</div><div class="col-md-3"><span class="text-muted small d-block">SMTP Host</span>{{ $value($emailLog->maskedPayloadValue(['smtp_host', 'mail.host'])) }}</div><div class="col-md-3"><span class="text-muted small d-block">Queue Name</span>{{ $value(data_get($payload, 'queue_name')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Queue Job ID</span>{{ $value(data_get($payload, 'queue_job_id')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Attempts</span>{{ $value(data_get($payload, 'attempts')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Processing Time</span>{{ $value(data_get($payload, 'processing_time')) }}</div><div class="col-md-6"><span class="text-muted small d-block">Provider Response</span>{{ $value(data_get($payload, 'provider_response')) }}</div></div></div></div>

    @if ($emailLog->status === 'failed')<div class="card shadow-sm border-danger mb-3"><div class="card-header text-danger"><strong>Failure Details</strong></div><div class="card-body"><div class="row g-3"><div class="col-12"><span class="text-muted small d-block">Error Message</span><pre class="bg-light border rounded p-3 small" style="white-space: pre-wrap;">{{ $emailLog->error_message ?: '—' }}</pre></div><div class="col-md-6"><span class="text-muted small d-block">Exception</span>{{ $value(data_get($payload, 'exception')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Retry Count</span>{{ $value(data_get($payload, 'retry_count')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Last Retry</span>{{ $value(data_get($payload, 'last_retry')) }}</div><div class="col-12"><details><summary>Stack Trace</summary><pre class="bg-light border rounded p-3 small mt-2" style="white-space: pre-wrap; max-height:360px; overflow:auto;">{{ data_get($payload, 'stack_trace', '—') }}</pre></details></div></div></div></div>@endif

    <div class="card shadow-sm mb-3"><div class="card-header"><strong>Audit Information</strong></div><div class="card-body"><div class="row g-3"><div class="col-md-3"><span class="text-muted small d-block">Created By</span>{{ $value(data_get($payload, 'created_by')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Updated By</span>{{ $value(data_get($payload, 'updated_by')) }}</div><div class="col-md-3"><span class="text-muted small d-block">Created At</span>{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div><div class="col-md-3"><span class="text-muted small d-block">Updated At</span>{{ optional($emailLog->updated_at)->format('Y-m-d H:i:s') ?? '—' }}</div></div></div></div>
@endsection
