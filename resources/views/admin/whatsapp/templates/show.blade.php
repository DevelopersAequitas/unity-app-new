@extends('admin.layouts.app')

@section('title', 'Template: ' . $template->name . ' - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-file-earmark-text text-primary me-2"></i>{{ $template->name }}
            </h1>
            <p class="text-muted small mb-0">Meta Template ID: <span class="font-monospace text-dark">{{ $template->meta_template_id ?: 'Not assigned' }}</span></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Templates
            </a>
            @if($template->isSendable())
                <a href="{{ route('admin.whatsapp.test_message', ['template' => $template->name, 'language' => $template->language]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-send me-1"></i>Send Test Message
                </a>
            @endif
        </div>
    </div>

    @if(strtoupper((string)$template->status) === 'REJECTED')
        @php
            $rejectedReason = $template->getRejectedReason();
            $rejectedDesc = $template->getRejectedReasonDescription();
        @endphp
        <div class="alert alert-danger border-danger mb-4 shadow-sm">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-x-circle-fill fs-2 me-3 text-danger"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Template Rejected by Meta Review</h5>
                    <div class="small">Meta evaluated this template submission and rejected approval for the reason below.</div>
                </div>
            </div>
            <div class="mt-3 p-3 bg-white rounded border border-danger-subtle">
                <div class="row g-2">
                    <div class="col-sm-3 text-muted fw-semibold">Meta Rejection Code:</div>
                    <div class="col-sm-9">
                        <span class="badge bg-danger fs-6">{{ $rejectedReason ?? 'INVALID_FORMAT' }}</span>
                    </div>
                    <div class="col-sm-3 text-muted fw-semibold">Diagnosis:</div>
                    <div class="col-sm-9 text-dark fw-bold">
                        {{ $rejectedDesc ?? 'Meta rejected this template due to invalid formatting or malformed variables.' }}
                    </div>
                </div>
            </div>
            <div class="mt-3 small">
                <div class="fw-bold text-dark mb-1"><i class="bi bi-lightbulb-fill text-warning me-1"></i>How to Fix:</div>
                @if(($rejectedReason ?? 'INVALID_FORMAT') === 'INVALID_FORMAT')
                    <p class="mb-2 text-muted"><strong>Double Curly Braces Required:</strong> WhatsApp templates require double curly braces like <code>@{{1}}</code>. If you wrote single braces like <code>{1}</code>, Meta rejects it immediately.</p>
                @elseif(($rejectedReason ?? '') === 'INCORRECT_CATEGORY')
                    <p class="mb-2 text-muted"><strong>Category Mismatch:</strong> Meta determined that the content is promotional/marketing, but was submitted under <strong>UTILITY</strong>. Try recreating it under category <strong>MARKETING</strong>.</p>
                @endif
                <div class="d-flex gap-2 mt-2">
                    <a href="{{ route('admin.whatsapp.templates.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create New Template with Fix
                    </a>
                    <form action="{{ route('admin.whatsapp.templates.delete', $template->id) }}" method="POST" onsubmit="return confirm('Delete rejected template from Meta and local system?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger bg-white">
                            <i class="bi bi-trash me-1"></i>Delete Rejected Template
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <!-- Overview & Components -->
        <div class="col-lg-7">
            <!-- Metadata Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">Template Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Status</span>
                            @php
                                $st = strtoupper((string)$template->status);
                            @endphp
                            @if(in_array($st, ['APPROVED', 'ACTIVE']))
                                <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>APPROVED</span>
                            @elseif($st === 'IN_REVIEW' || $st === 'PENDING')
                                <span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split me-1"></i>IN REVIEW</span>
                            @elseif($st === 'REJECTED')
                                <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i>REJECTED</span>
                            @else
                                <span class="badge bg-secondary fs-6">{{ $st }}</span>
                            @endif
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Quality Score</span>
                            <span class="fw-bold">{{ $template->quality ?: 'PENDING / NOT AVAILABLE' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Category</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $template->category }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Language</span>
                            <span class="font-monospace fw-bold">{{ $template->language }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Parameter Format</span>
                            <span class="font-monospace">{{ $template->parameter_format ?? 'positional' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Last Synced At</span>
                            <span>{{ $template->last_synced_at ? $template->last_synced_at->format('d M Y, h:i A') : 'Never' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Components Breakdown -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">Approved Components</h5>
                </div>
                <div class="card-body">
                    @php
                        $components = is_array($template->components) ? $template->components : [];
                    @endphp
                    @forelse($components as $c)
                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="fw-bold text-uppercase small text-primary mb-1">
                                Component: {{ $c['type'] ?? 'UNKNOWN' }}
                                @if(isset($c['format']))
                                    <span class="badge bg-secondary ms-1">{{ $c['format'] }}</span>
                                @endif
                            </div>
                            @if(isset($c['text']))
                                <div class="bg-white p-2 rounded border small font-monospace" style="white-space: pre-wrap;">{{ $c['text'] }}</div>
                            @endif
                            @if(isset($c['buttons']))
                                <div class="mt-2">
                                    <div class="small fw-semibold text-muted mb-1">Buttons:</div>
                                    @foreach($c['buttons'] as $b)
                                        <span class="badge bg-dark-subtle text-dark border me-1">
                                            [{{ $b['type'] ?? 'BUTTON' }}] {{ $b['text'] ?? '' }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            @if(isset($c['example']))
                                <div class="mt-2">
                                    <div class="small fw-semibold text-muted">Example Values:</div>
                                    <code class="small">{{ json_encode($c['example']) }}</code>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No component breakdown available.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- WhatsApp Authentic Preview & Raw Meta Response Viewer -->
        <div class="col-lg-5">
            @php
                $components = is_array($template->components) ? $template->components : [];
                $headerComp = collect($components)->firstWhere('type', 'HEADER');
                $bodyComp = collect($components)->firstWhere('type', 'BODY');
                $footerComp = collect($components)->firstWhere('type', 'FOOTER');
                $buttonsComp = collect($components)->firstWhere('type', 'BUTTONS');
                $buttons = $buttonsComp['buttons'] ?? [];
            @endphp

            <!-- Authentic WhatsApp Phone Preview Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-eye text-primary me-2"></i>Template Preview
                    </h6>
                    <span class="badge bg-success-subtle text-success">
                        <i class="bi bi-whatsapp me-1"></i>WhatsApp Preview
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="wa-preview-wrapper shadow-none border-0">
                        <!-- Top Bar -->
                        <div class="wa-chat-header">
                            <i class="bi bi-arrow-left fs-5"></i>
                            <div class="wa-chat-avatar">PG</div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-bold small text-truncate mb-0">Peers Global Unity</div>
                                <div class="text-white-50 small d-flex align-items-center" style="font-size: 10px;">
                                    <i class="bi bi-patch-check-fill text-info me-1"></i>Official Business Account
                                </div>
                            </div>
                            <div class="d-flex gap-3 text-white-50">
                                <i class="bi bi-camera-video-fill"></i>
                                <i class="bi bi-telephone-fill"></i>
                                <i class="bi bi-three-dots-vertical"></i>
                            </div>
                        </div>

                        <!-- Chat Area -->
                        <div class="wa-chat-body">
                            <div class="wa-date-pill">TODAY</div>
                            <div class="text-center mb-3">
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-1 px-2" style="font-size: 10px; font-weight: normal; border-radius: 6px;">
                                    <i class="bi bi-lock-fill me-1"></i>Messages and calls are end-to-end encrypted.
                                </span>
                            </div>

                            <!-- Chat Bubble with Notch Tail -->
                            <div class="wa-bubble-container">
                                <div class="wa-bubble-tail"></div>
                                <div class="wa-bubble">
                                    <div class="wa-msg-content">
                                        <!-- Header Component -->
                                        @if($headerComp)
                                            @if(($headerComp['format'] ?? '') === 'TEXT' && !empty($headerComp['text']))
                                                <div class="wa-header-text">{{ $headerComp['text'] }}</div>
                                            @elseif(!empty($headerComp['format']))
                                                <div class="wa-header-media">
                                                    @if($headerComp['format'] === 'VIDEO')
                                                        <i class="bi bi-play-btn-fill fs-4 d-block mb-1"></i>
                                                    @elseif($headerComp['format'] === 'DOCUMENT')
                                                        <i class="bi bi-file-earmark-pdf-fill fs-4 d-block mb-1"></i>
                                                    @else
                                                        <i class="bi bi-image fs-4 d-block mb-1"></i>
                                                    @endif
                                                    [{{ $headerComp['format'] }} Header]
                                                </div>
                                            @endif
                                        @endif

                                        <!-- Body Component -->
                                        <div class="wa-body-text">
                                            @if($bodyComp && !empty($bodyComp['text']))
                                                {!! preg_replace('/\{\{(\d+)\}\}/', '<span class="wa-body-var-badge">{{\'$0\'}}</span>', e($bodyComp['text'])) !!}
                                            @else
                                                <span class="text-muted font-italic">No body text defined.</span>
                                            @endif
                                        </div>

                                        <!-- Footer Component -->
                                        @if($footerComp && !empty($footerComp['text']))
                                            <div class="wa-footer-text">{{ $footerComp['text'] }}</div>
                                        @endif

                                        <!-- Meta Time & Ticks -->
                                        <div class="wa-meta-time">
                                            <span>12:35</span>
                                            <i class="bi bi-check2-all" style="color: #53bdeb; font-size: 13px;"></i>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    @if(!empty($buttons))
                                        <div class="wa-buttons-list">
                                            @foreach($buttons as $btn)
                                                <div class="wa-btn-item">
                                                    @if(($btn['type'] ?? '') === 'URL')
                                                        <i class="bi bi-box-arrow-up-right"></i>
                                                    @elseif(($btn['type'] ?? '') === 'PHONE_NUMBER')
                                                        <i class="bi bi-telephone-fill"></i>
                                                    @else
                                                        <i class="bi bi-reply-fill"></i>
                                                    @endif
                                                    <span>{{ $btn['text'] ?? $btn['type'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Footer Info Bar -->
                        <div class="bg-light text-center small text-muted py-2 border-top">
                            Category: <strong>{{ $template->category }}</strong> &bull; Language: <strong>{{ $template->language }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Raw Meta Response Viewer -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Raw Meta Response</h5>
                    <span class="badge bg-light text-muted border font-monospace">JSON</span>
                </div>
                <div class="card-body p-0">
                    <pre class="bg-dark text-light p-3 mb-0 rounded-bottom small font-monospace" style="max-height: 400px; overflow-y: auto;">{{ json_encode($template->meta_response ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Authentic WhatsApp Preview Styles */
.wa-preview-wrapper {
    max-width: 380px;
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #d1d7db;
    background: #ffffff;
}
.wa-chat-header {
    background: #008069;
    padding: 10px 14px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wa-chat-avatar {
    width: 36px;
    height: 36px;
    background: #ffffff;
    color: #008069;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}
.wa-chat-body {
    background-color: #efeae2;
    background-image: radial-gradient(#d1c7b7 12%, transparent 13%);
    background-size: 16px 16px;
    padding: 16px 12px 24px;
    min-height: 380px;
    display: flex;
    flex-direction: column;
}
.wa-date-pill {
    background: #ffffff;
    color: #54656f;
    font-size: 11px;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 6px;
    box-shadow: 0 1px 1px rgba(11,20,26,.12);
    align-self: center;
    margin-bottom: 10px;
    text-transform: uppercase;
}
.wa-bubble-container {
    position: relative;
    max-width: 90%;
    margin-left: 8px;
}
.wa-bubble {
    background: #ffffff;
    border-radius: 7.5px;
    box-shadow: 0 1px 0.5px rgba(11,20,26,.13);
    position: relative;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #111b21;
    overflow: hidden;
}
.wa-bubble-tail {
    position: absolute;
    top: 0;
    left: -8px;
    width: 0;
    height: 0;
    border-top: 0px solid transparent;
    border-bottom: 10px solid transparent;
    border-right: 8px solid #ffffff;
}
.wa-msg-content {
    padding: 8px 10px 4px 10px;
}
.wa-header-text {
    font-size: 14.5px;
    font-weight: 700;
    color: #111b21;
    margin-bottom: 6px;
    line-height: 1.35;
}
.wa-header-media {
    background: #e9edef;
    border-radius: 6px;
    padding: 22px 12px;
    text-align: center;
    color: #54656f;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 8px;
    border: 1px dashed #c4ccd0;
}
.wa-body-text {
    font-size: 14px;
    line-height: 1.42;
    color: #111b21;
    white-space: pre-wrap;
    word-break: break-word;
}
.wa-body-var-badge {
    background: #e7f8f5;
    color: #008069;
    padding: 0 4px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 13.5px;
    border: 1px solid #a3e6d8;
    display: inline-block;
}
.wa-footer-text {
    font-size: 11.5px;
    color: #667781;
    margin-top: 6px;
    margin-bottom: 2px;
    line-height: 1.3;
}
.wa-meta-time {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
    font-size: 11px;
    color: #667781;
    margin-top: 2px;
}
.wa-buttons-list {
    border-top: 1px solid #e9edef;
    margin-top: 6px;
}
.wa-btn-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 12px;
    color: #00a884;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    border-bottom: 1px solid #e9edef;
    background: #ffffff;
    transition: background 0.15s ease;
}
.wa-btn-item:last-child {
    border-bottom: none;
}
.wa-btn-item:hover {
    background: #f5f6f6;
    color: #008069;
}
</style>
@endsection
