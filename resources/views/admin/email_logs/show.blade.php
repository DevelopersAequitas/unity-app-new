@extends('admin.layouts.app')

@section('title', 'Email Log Detail')

@section('content')
    @php
        $statusBadgeClass = match (strtolower((string) $emailLog->status)) {
            'sent' => 'bg-success-subtle text-success border border-success-subtle',
            'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'queued', 'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
        $triggeredUser = $emailLog->user;
        $bodyText = $emailLog->body_text ?? null;
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Email Log #{{ $emailLog->id }}</h1>
            <div class="text-muted small">Complete delivery and content details for this email log.</div>
        </div>
        <a href="{{ route('admin.email-logs.index') }}" class="btn btn-sm btn-outline-secondary">Back to Email Logs</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Email Information</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><span class="text-muted small d-block">Log ID</span>{{ $emailLog->id }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Subject</span>{{ $emailLog->subject ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Template Key</span>{{ $emailLog->template_key ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Source Module</span>{{ $emailLog->source_module ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Status</span><span class="badge {{ $statusBadgeClass }}">{{ ucfirst((string) $emailLog->status) }}</span></div>
                <div class="col-md-3"><span class="text-muted small d-block">Mail Provider</span>{{ $emailLog->mail_provider ?: config('mail.default', '—') }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Queue ID</span>{{ $emailLog->queue_id ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Message ID</span>{{ $emailLog->message_id ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Sent At</span>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Created At</span>{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Recipient Information</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><span class="text-muted small d-block">Recipient Email</span>{{ $emailLog->to_email }}</div>
                <div class="col-md-6"><span class="text-muted small d-block">Recipient Name</span>{{ $emailLog->to_name ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Trigger Information</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><span class="text-muted small d-block">Triggered By</span>{{ $emailLog->triggered_by ?: ($emailLog->source_event ?: '—') }}</div>
                <div class="col-md-6"><span class="text-muted small d-block">Triggered User</span>{{ $triggeredUser?->display_name ?: $triggeredUser?->email ?: $emailLog->triggered_user_id ?: $emailLog->user_id ?: '—' }}</div>
            </div>
        </div>
    </div>

    @if (strtolower((string) $emailLog->status) === 'failed' || ! empty($emailLog->error_message))
        <div class="card shadow-sm border-danger mb-3">
            <div class="card-header text-danger"><strong>Failure Information</strong></div>
            <div class="card-body"><pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ $emailLog->error_message ?: 'No error message stored.' }}</pre></div>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Email Content</strong></div>
        <div class="card-body">
            @if (! empty($emailLog->body_html))
                <div class="border rounded mb-3" style="min-height: 420px; overflow: hidden;">
                    <iframe title="Email HTML Preview" sandbox srcdoc="{{ $emailLog->body_html }}" style="width: 100%; min-height: 420px; border: 0;"></iframe>
                </div>
            @elseif (! empty($bodyText))
                <pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ $bodyText }}</pre>
            @else
                <div class="text-muted">No email body stored for this log.</div>
            @endif
        </div>
    </div>

    @if ($emailLog->payload)
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Metadata / Payload</strong></div>
            <div class="card-body"><pre class="bg-light border rounded p-3 small mb-0" style="max-height: 360px; overflow:auto;">{{ json_encode($emailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
        </div>
    @endif
@endsection
