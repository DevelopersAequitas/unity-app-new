@extends('admin.layouts.app')

@section('title', 'Email Log Detail')

@section('content')
    @php
        $statusBadgeClass = match ((string) $emailLog->status) {
            'sent' => 'bg-success-subtle text-success border border-success-subtle',
            'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Email Log #{{ $emailLog->id }}</h1>
        <a href="{{ route('admin.email-logs.index', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Basic Info</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><span class="text-muted small d-block">Status</span><span class="badge {{ $statusBadgeClass }}">{{ ucfirst((string) $emailLog->status) }}</span></div>
                <div class="col-md-3"><span class="text-muted small d-block">Sent At</span>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Template Key</span>{{ $emailLog->template_key ?: '—' }}</div>
                <div class="col-md-3"><span class="text-muted small d-block">Source Module</span>{{ $emailLog->source_module ?: '—' }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Recipient</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><span class="text-muted small d-block">To Name</span>{{ $emailLog->to_name ?: '—' }}</div>
                <div class="col-md-6"><span class="text-muted small d-block">To Email</span>{{ $emailLog->to_email }}</div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Subject</strong></div>
        <div class="card-body">{{ $emailLog->subject ?: '—' }}</div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Email Body</strong></div>
        <div class="card-body">
            @if (! empty($emailLog->body_html))
                <div class="border rounded mb-3" style="min-height: 400px; overflow: hidden;">
                    <iframe
                        title="Email HTML Preview"
                        sandbox
                        srcdoc="{{ $emailLog->body_html }}"
                        style="width: 100%; min-height: 400px; border: 0;"
                    ></iframe>
                </div>
            @elseif ($emailLog->payload)
                <pre class="bg-light border rounded p-3 small mb-0" style="max-height: 360px; overflow:auto;">{{ json_encode($emailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <div class="text-muted">No body content available.</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>Payload</strong></div>
        <div class="card-body">
            @if ($emailLog->payload)
                <pre class="bg-light border rounded p-3 small mb-0" style="max-height: 360px; overflow:auto;">{{ json_encode($emailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <div class="text-muted">No payload available.</div>
            @endif
        </div>
    </div>

    @if ($emailLog->status === 'failed' && ! empty($emailLog->error_message))
        <div class="card shadow-sm border-danger mb-3">
            <div class="card-header text-danger"><strong>Error</strong></div>
            <div class="card-body">
                <pre class="bg-light border rounded p-3 small mb-0" style="white-space: pre-wrap;">{{ $emailLog->error_message }}</pre>
            </div>
        </div>
    @endif
@endsection
