@extends('admin.layouts.app')

@section('title', 'Email Log #' . $emailLog->id)

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    .email-log-page {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
    }
    .font-mono-code {
        font-family: 'JetBrains Mono', monospace;
    }
    .modern-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.25rem;
        overflow: hidden;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .modern-card:hover {
        box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.06), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        border-color: #cbd5e1;
    }
    .modern-card-header {
        padding: 0.875rem 1.25rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modern-card-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .modern-card-body {
        padding: 1.25rem;
    }
    .info-field-group {
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        padding: 0.75rem 0.875rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .info-field-label {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 0.25rem;
    }
    .info-field-value {
        font-size: 0.84375rem;
        font-weight: 600;
        color: #0f172a;
        word-break: break-word;
        line-height: 1.4;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .status-pill-sent {
        background-color: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .status-pill-failed {
        background-color: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }
    .status-pill-queued, .status-pill-pending {
        background-color: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
    }
    .status-pill-default {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }
    .pulse-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .pulse-dot.sent { background-color: #10b981; }
    .pulse-dot.failed { background-color: #f43f5e; }
    .pulse-dot.queued, .pulse-dot.pending { background-color: #f59e0b; }
    .copy-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 0 0.25rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: color 0.15s;
    }
    .copy-btn:hover {
        color: #2563eb;
    }
    .preview-browser-box {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
    }
    .preview-browser-header {
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.5rem 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .browser-circle {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
    }
    .code-box {
        background: #0f172a;
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 8px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8125rem;
        line-height: 1.6;
    }
</style>
@endpush

@section('content')
    @php
        $statusKey = strtolower((string) $emailLog->status);
        $statusPillClass = match ($statusKey) {
            'sent' => 'status-pill-sent',
            'failed' => 'status-pill-failed',
            'queued', 'pending' => 'status-pill-queued',
            default => 'status-pill-default',
        };
        $triggeredUser = $emailLog->user;
        $bodyText = $emailLog->body_text ?? null;
        $htmlContent = !empty($bodyHtml) ? $bodyHtml : (!empty($emailLog->body_html) ? $emailLog->body_html : null);
    @endphp

    <div class="email-log-page container-fluid px-0 py-2">
        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h4 mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Email Log #{{ $emailLog->id }}</span>
                </h1>
                <div class="text-muted small">Complete delivery and content details for this email log.</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="{{ route('admin.email-logs.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 d-inline-flex align-items-center gap-2 fw-semibold shadow-none">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- 1. Email Information Card -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h6 class="modern-card-title">
                    <i class="bi bi-envelope text-primary"></i> Email Information
                </h6>
                <span class="status-pill {{ $statusPillClass }}">
                    <span class="pulse-dot {{ $statusKey }}"></span>
                    {{ ucfirst((string) $emailLog->status) }}
                </span>
            </div>
            <div class="modern-card-body">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Log ID</span>
                            <div class="info-field-value font-mono-code small text-truncate" title="{{ $emailLog->id }}">
                                {{ $emailLog->id }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Subject</span>
                            <div class="info-field-value text-dark" title="{{ $emailLog->subject }}">
                                {{ $emailLog->subject ?: '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Template Key</span>
                            <div class="info-field-value font-mono-code text-primary small">
                                {{ $emailLog->template_key ?: '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Source Module</span>
                            <div class="info-field-value text-dark">
                                @if($emailLog->source_module)
                                    <span class="badge bg-light text-dark border px-2 py-1">{{ $emailLog->source_module }}</span>
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Status</span>
                            <div class="info-field-value">
                                <span class="status-pill {{ $statusPillClass }} py-0.5 px-2">
                                    {{ ucfirst((string) $emailLog->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Mail Provider</span>
                            <div class="info-field-value text-dark">
                                {{ $emailLog->mail_provider ?: config('mail.default', '—') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Queue ID</span>
                            <div class="info-field-value font-mono-code text-muted">
                                {{ $emailLog->queue_id ?: '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Message ID</span>
                            <div class="info-field-value font-mono-code text-muted text-truncate" title="{{ $emailLog->message_id }}">
                                {{ $emailLog->message_id ?: '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Sent At</span>
                            <div class="info-field-value text-dark">
                                {{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Created At</span>
                            <div class="info-field-value text-dark">
                                {{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Recipient Information Card -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h6 class="modern-card-title">
                    <i class="bi bi-person-check text-primary"></i> Recipient Information
                </h6>
            </div>
            <div class="modern-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Recipient Email</span>
                            <div class="info-field-value">
                                <a href="mailto:{{ $emailLog->to_email }}" class="text-primary text-decoration-none fw-semibold">
                                    {{ $emailLog->to_email }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Recipient Name</span>
                            <div class="info-field-value text-dark fw-bold">
                                {{ $emailLog->to_name ?: '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Trigger Information Card -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h6 class="modern-card-title">
                    <i class="bi bi-lightning-charge text-primary"></i> Trigger Information
                </h6>
            </div>
            <div class="modern-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Triggered By</span>
                            <div class="info-field-value text-dark">
                                {{ $emailLog->triggered_by ?: ($emailLog->source_event ?: '—') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <span class="info-field-label">Triggered User</span>
                            <div class="info-field-value text-dark">
                                {{ $triggeredUser?->display_name ?: $triggeredUser?->email ?: $emailLog->triggered_user_id ?: $emailLog->user_id ?: '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Failure Information (if failed or error) -->
        @if (strtolower((string) $emailLog->status) === 'failed' || ! empty($emailLog->error_message))
            <div class="modern-card border-danger">
                <div class="modern-card-header bg-danger-subtle text-danger">
                    <h6 class="modern-card-title text-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> Failure Information
                    </h6>
                </div>
                <div class="modern-card-body">
                    <pre class="bg-light border border-danger-subtle rounded-3 p-3 font-mono-code text-danger small mb-0" style="white-space: pre-wrap;">{{ $emailLog->error_message ?: 'No error message stored.' }}</pre>
                </div>
            </div>
        @endif

        <!-- 5. Email Content Card -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h6 class="modern-card-title">
                    <i class="bi bi-file-earmark-richtext text-primary"></i> Email Content
                </h6>
                @if (! empty($htmlContent))
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-secondary fw-semibold d-inline-flex align-items-center gap-1 shadow-none" style="font-size: 0.75rem;" onclick="openPopoutEmailPreview()">
                            <i class="bi bi-box-arrow-up-right text-primary"></i> Popout Preview
                        </button>
                    </div>
                @endif
            </div>
            <div class="modern-card-body p-0">
                @if (! empty($htmlContent))
                    <div class="p-3 p-md-4" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05); overflow: hidden;">
                            <iframe id="emailContentIframe" title="Email HTML Preview" sandbox="allow-same-origin" srcdoc="{{ $htmlContent }}" style="width: 100%; min-height: 520px; border: 0; display: block; background: #ffffff;" onload="adjustIframeHeight(this)"></iframe>
                        </div>
                    </div>
                @elseif (! empty($bodyText))
                    <div class="p-3">
                        <pre class="bg-light border rounded-3 p-3.5 font-mono-code text-dark small mb-0" style="white-space: pre-wrap; font-size: 0.85rem; line-height: 1.6;">{{ $bodyText }}</pre>
                    </div>
                @else
                    <div class="text-center py-4 text-muted small">
                        No email body stored for this log.
                    </div>
                @endif
            </div>
        </div>

        <!-- 6. Metadata / Payload Card (if payload exists) -->
        @if ($emailLog->payload)
            <div class="modern-card">
                <div class="modern-card-header">
                    <h6 class="modern-card-title">
                        <i class="bi bi-braces text-primary"></i> Metadata / Payload
                    </h6>
                </div>
                <div class="modern-card-body p-0">
                    <pre class="code-box mb-0 rounded-0" style="max-height: 380px; overflow: auto; border-radius: 0 0 12px 12px;">{{ json_encode($emailLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>

    <template id="hiddenEmailHtmlTemplate">{!! $htmlContent !!}</template>

    <script>
        function adjustIframeHeight(iframe) {
            try {
                if (iframe && iframe.contentWindow && iframe.contentWindow.document && iframe.contentWindow.document.body) {
                    const doc = iframe.contentWindow.document;
                    const scrollHeight = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight, doc.documentElement.offsetHeight, doc.body.offsetHeight);
                    if (scrollHeight > 100) {
                        iframe.style.height = (scrollHeight + 20) + 'px';
                    }
                }
            } catch(e) {}
        }

        function openPopoutEmailPreview() {
            const tpl = document.getElementById('hiddenEmailHtmlTemplate');
            if (!tpl) return;
            const popout = window.open('', '_blank');
            if (popout) {
                popout.document.write(tpl.innerHTML);
                popout.document.close();
            }
        }
    </script>
@endsection


