@extends('admin.layouts.app')

@section('title', 'Message Delivery Details - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-envelope-paper text-primary me-2"></i>WhatsApp Message Log Details
            </h1>
            <p class="text-muted small mb-0">Record ID: <span class="font-monospace text-dark">{{ $message->id }}</span></p>
        </div>
        <div class="d-flex gap-2">
            @if(in_array(strtolower((string)$message->status), ['sent', 'delivered'], true))
                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-broadcast me-1"></i>Simulate Webhook
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        @if(strtolower((string)$message->status) === 'sent')
                            <li>
                                <form action="{{ route('admin.whatsapp.messages.simulate_status', $message->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-check2-all text-success me-2"></i>Trigger "DELIVERED" Status
                                    </button>
                                </form>
                            </li>
                        @endif
                        <li>
                            <form action="{{ route('admin.whatsapp.messages.simulate_status', $message->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="read">
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-check2-all text-primary me-2"></i>Trigger "READ" Status (Blue Ticks)
                                </button>
                            </form>
                        </li>
                        <li>
                            <form action="{{ route('admin.whatsapp.messages.simulate_status', $message->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="failed">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-x-circle text-danger me-2"></i>Trigger "FAILED" Status
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endif
            <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Logs
            </a>
        </div>
    </div>

    <!-- Lifecycle Stepper -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="row text-center g-3">
                <div class="col-3">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 42px; height: 42px;">
                        <i class="bi bi-send-check fs-5"></i>
                    </div>
                    <div class="fw-bold small text-dark">1. Accepted by Meta</div>
                    <div class="text-muted" style="font-size: 11px;">{{ $message->sent_at ? $message->sent_at->format('d M H:i:s') : 'Pending' }}</div>
                </div>
                <div class="col-3">
                    <div class="rounded-circle {{ in_array(strtolower((string)$message->status), ['delivered', 'read'], true) ? 'bg-success text-white' : (strtolower((string)$message->status) === 'failed' ? 'bg-danger text-white' : 'bg-light text-muted border') }} d-inline-flex align-items-center justify-content-center mb-2" style="width: 42px; height: 42px;">
                        @if(in_array(strtolower((string)$message->status), ['delivered', 'read'], true))
                            <i class="bi bi-check2-all fs-5"></i>
                        @elseif(strtolower((string)$message->status) === 'failed')
                            <i class="bi bi-x fs-5"></i>
                        @else
                            <i class="bi bi-hourglass-split fs-5"></i>
                        @endif
                    </div>
                    <div class="fw-bold small {{ in_array(strtolower((string)$message->status), ['delivered', 'read'], true) ? 'text-success' : (strtolower((string)$message->status) === 'failed' ? 'text-danger' : 'text-muted') }}">2. Delivered to Device</div>
                    <div class="text-muted" style="font-size: 11px;">
                        {{ in_array(strtolower((string)$message->status), ['delivered', 'read'], true) ? 'Confirmed' : (strtolower((string)$message->status) === 'failed' ? 'Failed' : 'Pending webhook') }}
                    </div>
                </div>
                <div class="col-3">
                    <div class="rounded-circle {{ strtolower((string)$message->status) === 'read' ? 'bg-primary text-white' : 'bg-light text-muted border' }} d-inline-flex align-items-center justify-content-center mb-2" style="width: 42px; height: 42px;">
                        <i class="bi bi-eye fs-5"></i>
                    </div>
                    <div class="fw-bold small {{ strtolower((string)$message->status) === 'read' ? 'text-primary' : 'text-muted' }}">3. Read by Recipient</div>
                    <div class="text-muted" style="font-size: 11px;">{{ strtolower((string)$message->status) === 'read' ? 'Confirmed' : 'Pending read' }}</div>
                </div>
                <div class="col-3">
                    <div class="rounded-circle {{ strtolower((string)$message->status) === 'failed' ? 'bg-danger text-white' : (strtolower((string)$message->status) === 'read' ? 'bg-primary text-white' : (in_array(strtolower((string)$message->status), ['delivered', 'sent'], true) ? 'bg-success text-white' : 'bg-light text-muted border')) }} d-inline-flex align-items-center justify-content-center mb-2" style="width: 42px; height: 42px;">
                        <i class="bi bi-flag fs-5"></i>
                    </div>
                    <div class="fw-bold small {{ strtolower((string)$message->status) === 'failed' ? 'text-danger' : (strtolower((string)$message->status) === 'read' ? 'text-primary' : 'text-success') }}">4. Final State</div>
                    <div class="text-muted" style="font-size: 11px;">{{ strtoupper((string)$message->status) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Banners -->
    @if(strtolower((string)$message->status) === 'read')
        <div class="alert alert-primary border-primary mb-4 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="bi bi-check2-all fs-2 me-3 text-primary"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Message Delivered and Read by User (Blue Ticks)</h5>
                    <div class="small">Read status confirmed by WhatsApp network callback.</div>
                </div>
            </div>
        </div>
    @elseif(strtolower((string)$message->status) === 'delivered')
        <div class="alert alert-success border-success mb-4 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="bi bi-check2-all fs-2 me-3 text-success"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Successfully Delivered to Recipient Phone</h5>
                    <div class="small">Device received the message via WhatsApp network.</div>
                </div>
            </div>
        </div>
    @elseif(strtolower((string)$message->status) === 'failed')
        <div class="alert alert-danger border-danger mb-4 shadow-sm">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-danger"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Delivery Failure Reported by Provider</h5>
                    <div class="small">Meta or the network rejected or dropped this message.</div>
                </div>
            </div>
            <div class="mt-3 p-3 bg-white rounded border border-danger-subtle">
                <div class="row g-2">
                    <div class="col-sm-3 text-muted fw-semibold">Error Code:</div>
                    <div class="col-sm-9"><span class="badge bg-danger fs-6">{{ $message->error_code ?? 'N/A' }}</span></div>
                    <div class="col-sm-3 text-muted fw-semibold">Error Message:</div>
                    <div class="col-sm-9 text-dark fw-bold">{{ $message->error_message ?? 'Unknown Meta error.' }}</div>
                    @if($message->failed_at)
                        <div class="col-sm-3 text-muted fw-semibold">Failed At:</div>
                        <div class="col-sm-9 text-danger">{{ $message->failed_at->format('d M Y, H:i:s') }}</div>
                    @endif
                </div>
            </div>
        </div>
    @elseif(strtolower((string)$message->status) === 'sent')
        <div class="alert alert-info border-info mb-4 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="bi bi-clock-history fs-2 me-3 text-info"></i>
                <div class="flex-grow-1">
                    <h5 class="alert-heading fw-bold mb-0">Dispatched &amp; Accepted by Meta Cloud API</h5>
                    <div class="small">The message was successfully enqueued and accepted by Meta (Message ID: <code>{{ $message->provider_message_id }}</code>). Delivery state (<code>DELIVERED</code>, <code>READ</code>, or <code>FAILED</code>) updates automatically when Meta delivers the message to the device and sends its Webhook notification.</div>
                </div>
            </div>
        </div>
    @elseif(strtolower((string)$message->status) === 'dry_run')
        <div class="alert alert-secondary border mb-4 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="bi bi-eye-slash-fill fs-3 me-3 text-secondary"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-0">Dry-Run Simulation Record</h5>
                    <div class="small"><code>WHATSAPP_DRY_RUN=true</code> was active. The exact outbound payload was generated and validated, but no actual HTTP call was made to Meta.</div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Message Details Card -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">Message Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width: 180px;">Status:</td>
                            <td>
                                @php $st = strtolower((string)$message->status); @endphp
                                @if($st === 'read')
                                    <span class="badge bg-primary fs-6"><i class="bi bi-check2-all me-1"></i>READ</span>
                                @elseif($st === 'delivered')
                                    <span class="badge bg-success fs-6"><i class="bi bi-check2-all me-1"></i>DELIVERED</span>
                                @elseif($st === 'sent')
                                    <span class="badge bg-info text-dark fs-6"><i class="bi bi-check2 me-1"></i>SENT</span>
                                @elseif($st === 'failed')
                                    <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i>FAILED</span>
                                @elseif($st === 'dry_run')
                                    <span class="badge bg-secondary fs-6"><i class="bi bi-eye-slash me-1"></i>DRY RUN</span>
                                @else
                                    <span class="badge bg-warning text-dark fs-6">{{ strtoupper($st) }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Target Phone:</td>
                            <td>
                                @php
                                    $isRedirected = ! empty($message->actual_recipient_phone)
                                        && ! empty($message->requested_recipient_phone)
                                        && ($message->actual_recipient_phone !== $message->requested_recipient_phone);
                                @endphp
                                @if($isRedirected)
                                    <div>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-2 font-monospace" style="font-size: 11px;">
                                            <i class="bi bi-shield-exclamation me-1"></i>TEST ROUTE APPLIED
                                        </span>
                                        <div class="font-monospace fw-bold text-dark mt-1">
                                            Actual Sent: {{ $message->actual_recipient_phone }}
                                        </div>
                                        <div class="text-muted small">
                                            Requested Recipient: <span class="font-monospace">{{ $message->requested_recipient_phone }}</span>
                                        </div>
                                    </div>
                                @elseif($message->is_test)
                                    <div>
                                        <span class="badge bg-secondary-subtle text-secondary border py-0.5 px-2 font-monospace" style="font-size: 11px;">
                                            <i class="bi bi-vial me-1"></i>TEST MODE
                                        </span>
                                        <span class="font-monospace fw-bold ms-1">{{ $message->actual_recipient_phone ?: $message->recipient_phone }}</span>
                                    </div>
                                @else
                                    <span class="font-monospace fw-bold">{{ $message->actual_recipient_phone ?: $message->recipient_phone }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">User:</td>
                            <td>
                                @if($message->user)
                                    <span class="fw-semibold">{{ $message->user->first_name ?? $message->user->name ?? 'User' }}</span>
                                    <span class="text-muted small">({{ $message->user->email ?? $message->user->mobile }})</span>
                                @elseif($message->user_id)
                                    <span class="font-monospace text-muted">{{ $message->user_id }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Template Key:</td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $message->template_key }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Meta Template Name:</td>
                            <td><span class="fw-bold">{{ $message->template_name }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Event Key:</td>
                            <td>
                                @if($message->event_key)
                                    <span class="badge bg-secondary-subtle text-secondary border font-monospace">{{ $message->event_key }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Provider Message ID:</td>
                            <td class="font-monospace small text-primary">{{ $message->provider_message_id ?: 'None' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Environment:</td>
                            <td>
                                <span class="badge {{ $message->is_test ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle' }}">
                                    {{ strtoupper((string)($message->environment ?? 'PRODUCTION')) }} {{ $message->is_test ? '(TEST MODE)' : '' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created / Queued At:</td>
                            <td>{{ $message->created_at ? $message->created_at->format('d M Y, H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dispatched (SENT):</td>
                            <td>{{ $message->sent_at ? $message->sent_at->format('d M Y, H:i:s') : '-' }}</td>
                        </tr>
                        @if($message->failed_at)
                            <tr>
                                <td class="text-muted">Failed At:</td>
                                <td class="text-danger fw-semibold">{{ $message->failed_at->format('d M Y, H:i:s') }}</td>
                            </tr>
                        @endif
                        @if($message->idempotency_key)
                            <tr>
                                <td class="text-muted">Idempotency Key:</td>
                                <td class="font-monospace small text-muted">{{ Str::limit($message->idempotency_key, 32) }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- Dynamic Variables Card -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Dynamic Template Variables</h5>
                    <span class="badge bg-light text-dark border">Sanitized & Masked</span>
                </div>
                <div class="card-body">
                    @php
                        $vars = (array) ($message->template_variables ?? []);
                    @endphp
                    @if(!empty($vars))
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Variable</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vars as $k => $v)
                                    <tr>
                                        <td class="font-monospace text-muted small fw-semibold">{{ $k }}</td>
                                        <td>
                                            @if(strtolower((string)$k) === 'code' || strtolower((string)$k) === 'otp')
                                                <span class="badge bg-dark font-monospace">******</span>
                                                <span class="text-muted small ms-1">(Masked for security)</span>
                                            @elseif(is_string($v) && (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')))
                                                <a href="{{ $v }}" target="_blank" class="text-break font-monospace small">
                                                    {{ Str::limit($v, 50) }} <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            @elseif(is_array($v) || is_object($v))
                                                <pre class="mb-0 small font-monospace">{{ json_encode($v, JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                <span class="fw-semibold text-dark">{{ (string)$v }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-muted small p-3 text-center">
                            No discrete template variables recorded for this dispatch.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Request & Provider Response Payloads -->
    <div class="row g-4 mb-4">
        <!-- Request Payload -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Meta Request Payload</h5>
                    <span class="badge bg-light text-muted border font-monospace">JSON</span>
                </div>
                <div class="card-body p-0">
                    <pre class="bg-dark text-light p-3 mb-0 rounded-bottom small font-monospace" style="max-height: 380px; overflow-y: auto;">{{ json_encode($message->request_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>

        <!-- Provider Response -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Provider Response / Webhook Audit</h5>
                    <span class="badge bg-light text-muted border font-monospace">JSON</span>
                </div>
                <div class="card-body p-0">
                    <pre class="bg-dark text-light p-3 mb-0 rounded-bottom small font-monospace" style="max-height: 380px; overflow-y: auto;">{{ json_encode($message->provider_response ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
