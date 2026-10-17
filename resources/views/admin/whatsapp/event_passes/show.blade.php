@extends('admin.layouts.app')

@section('title', 'Event Pass Details - Meta WhatsApp')

@push('styles')
<style>
.qr-show-container svg {
    width: 100% !important;
    height: 100% !important;
    max-width: 200px !important;
    max-height: 200px !important;
    display: block !important;
    margin: 0 auto !important;
}
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $isMember = ! empty($registration->user_id);
        $name = $isMember
            ? ($registration->user?->display_name ?: trim(($registration->user?->first_name ?? '').' '.($registration->user?->last_name ?? '')))
            : ($registration->visitor_name ?: 'Visitor');
        $phone = $isMember ? ($registration->user?->phone ?: '-') : ($registration->visitor_phone ?: '-');
        $email = $isMember ? ($registration->user?->email ?: '-') : ($registration->visitor_email ?: '-');
        $wStatus = strtolower((string) ($registration->whatsapp_status ?? ''));

        $rawSvg = (string) ($inlineSvg ?? $registration->qr_code_svg ?? '');
        $cleanedSvg = preg_replace('/<\?xml[^>]*\?>/i', '', $rawSvg);
        $cleanedSvg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $cleanedSvg);
        $cleanedSvg = trim($cleanedSvg);
    @endphp

    {{-- Top Action Row --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.whatsapp.event_passes.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Event Passes
            </a>
            <div>
                <h1 class="h4 fw-bold text-dark mb-0">Pass Audit #{{ substr($registration->id, 0, 8) }}</h1>
                <div class="text-muted small">Registration Date: {{ $registration->created_at?->format('d M Y, h:i A') }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.whatsapp.event_passes.resend', $registration->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-1.5 shadow-sm" onclick="return confirm('Resend WhatsApp QR pass to {{ $phone }}?');">
                    <i class="bi bi-whatsapp"></i>
                    <span>Resend WhatsApp Pass</span>
                </button>
            </form>
            @if($qrUrl)
                <a href="{{ $qrUrl }}" download="Pass_{{ $registration->id }}.png" target="_blank" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5">
                    <i class="bi bi-download"></i> Download PNG
                </a>
            @endif
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Attendee & QR Pass Card --}}
        <div class="col-12 col-lg-5">
            {{-- QR Pass Box --}}
            <div class="card shadow-sm border-0 mb-4 text-center">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-qr-code me-1 text-success"></i>Official Entry QR Pass
                    </h5>
                </div>
                <div class="card-body p-4 bg-light">
                    <div class="bg-white p-3 rounded-4 shadow-sm d-inline-block border">
                        <div class="qr-show-container" style="width: 200px; height: 200px; margin: 0 auto; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            @if(!empty($cleanedSvg))
                                {!! $cleanedSvg !!}
                            @elseif(!empty($qrUrl))
                                <img src="{{ $qrUrl }}" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">
                            @else
                                <span class="text-muted small">No QR Available</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3">
                        <h5 class="fw-bold text-dark mb-1">{{ $name }}</h5>
                        <span class="badge {{ $isMember ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-purple-subtle text-purple border' }} px-2 py-1">
                            {{ $isMember ? 'Peers Member' : 'Visitor Attendee' }}
                        </span>
                    </div>

                    <div class="mt-3 p-2 bg-white rounded-3 border text-start">
                        <div class="text-muted small" style="font-size: 11px;">PASS TOKEN:</div>
                        <code class="text-dark font-monospace text-break" style="font-size: 12px;">{{ $registration->qr_token ?: $registration->id }}</code>
                    </div>
                </div>
            </div>

            {{-- Attendee Profile Card --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fs-6 fw-bold">Attendee Profile</h5>
                </div>
                <div class="card-body p-3">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Full Name</span>
                            <span class="fw-bold text-dark">{{ $name }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Phone Number</span>
                            <span class="fw-bold text-dark">
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $phone) }}" target="_blank" class="text-success text-decoration-none">
                                    <i class="bi bi-whatsapp me-1"></i>{{ $phone }}
                                </a>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Email</span>
                            <span class="fw-bold text-dark">{{ $email }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Check-in Status</span>
                            @if(strtolower((string) $registration->checkin_status) === 'checked_in')
                                <span class="badge bg-success text-white">Checked In</span>
                            @else
                                <span class="badge bg-secondary text-white">Not Checked In</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Payment Status</span>
                            <span class="badge bg-light text-dark border text-uppercase">{{ $registration->payment_status ?: 'Free / Included' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Right Column: Event Info & WhatsApp Delivery Logs --}}
        <div class="col-12 col-lg-7">
            {{-- Event Details Card --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-calendar-event me-1 text-primary"></i>Event Information
                    </h5>
                </div>
                <div class="card-body p-3">
                    <h5 class="fw-bold text-dark mb-1">{{ $registration->event?->title ?? 'N/A' }}</h5>
                    <div class="text-muted small mb-3">
                        <i class="bi bi-geo-alt me-1"></i>{{ $registration->event?->venue ?: 'Location details in app' }}
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <div class="text-muted small" style="font-size: 11px;">EVENT DATE & TIME</div>
                                <div class="fw-bold text-dark mt-1">
                                    {{ optional($registration->occurrence?->start_at ?: $registration->event?->start_at)->format('d M Y, h:i A') ?: 'Upcoming' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <div class="text-muted small" style="font-size: 11px;">CIRCLE / CHAPTER</div>
                                <div class="fw-bold text-dark mt-1">
                                    {{ $registration->event?->circle?->name ?: 'All Circles' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WhatsApp Status & Audit Trail --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp Delivery Audit Trail
                    </h5>
                    <div>
                        @if($wStatus === 'read')
                            <span class="badge bg-success text-white px-2.5 py-1">
                                <i class="bi bi-check2-all me-1"></i>Read (Blue Ticks)
                            </span>
                        @elseif($wStatus === 'delivered')
                            <span class="badge bg-info-subtle text-info border border-info px-2.5 py-1">
                                <i class="bi bi-check2-all me-1"></i>Delivered
                            </span>
                        @elseif($wStatus === 'sent')
                            <span class="badge bg-primary-subtle text-primary border border-primary px-2.5 py-1">
                                <i class="bi bi-check2 me-1"></i>Sent (In-Transit)
                            </span>
                        @elseif($wStatus === 'failed')
                            <span class="badge bg-danger-subtle text-danger border border-danger px-2.5 py-1">
                                <i class="bi bi-exclamation-triangle me-1"></i>Failed
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1">
                                <i class="bi bi-clock me-1"></i>Pending
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-md-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <div class="text-muted small" style="font-size: 11px;">META MESSAGE ID (wamid)</div>
                                <div class="font-monospace text-primary small mt-1 text-break">
                                    {{ $registration->whatsapp_provider_id ?: 'None recorded' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="p-2.5 rounded-3 bg-light border">
                                <div class="text-muted small" style="font-size: 11px;">DISPATCH TIMESTAMP</div>
                                <div class="fw-semibold text-dark small mt-1">
                                    {{ $registration->whatsapp_sent_at?->format('d M Y, h:i:s A') ?: 'Not yet dispatched' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Timeline Logs --}}
                    <h6 class="fw-bold text-dark small text-uppercase mb-2" style="letter-spacing: 0.5px;">Delivery History Logs</h6>
                    @if($deliveryLogs->count() > 0)
                        <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                            @foreach($deliveryLogs as $log)
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            @if($log->status === 'sent')
                                                <span class="badge bg-primary">Sent</span>
                                            @elseif($log->status === 'delivered')
                                                <span class="badge bg-info">Delivered</span>
                                            @elseif($log->status === 'read')
                                                <span class="badge bg-success">Read</span>
                                            @elseif($log->status === 'failed')
                                                <span class="badge bg-danger">Failed</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $log->status }}</span>
                                            @endif
                                            <span class="text-muted small">{{ $log->created_at?->format('d M Y, h:i:s A') }}</span>
                                        </div>
                                        @if($log->provider_message_id)
                                            <code class="text-muted small">{{ substr($log->provider_message_id, 0, 20) }}...</code>
                                        @endif
                                    </div>
                                    @if($log->error_message)
                                        <div class="alert alert-danger py-1 px-2 mb-0 mt-2 small">
                                            <i class="bi bi-exclamation-circle me-1"></i>{{ $log->error_message }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small py-3 text-center bg-light rounded-3">
                            No separate delivery logs recorded yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
