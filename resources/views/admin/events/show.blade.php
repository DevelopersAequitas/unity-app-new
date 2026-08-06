@extends('admin.layouts.app')

@section('title', $event->title)

@section('content')
<div class="container-fluid py-3">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-3" role="alert" style="border-radius: 8px;">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-3" role="alert" style="border-radius: 8px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0 fw-bold">{{ $event->title }}</h1>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.events.edit', $event->id) }}" class="btn btn-primary">Edit</a>
        </div>
    </div>
    <div class="card mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><strong>Type:</strong> {{ $event->event_type }}</div>
            <div class="col-md-3"><strong>Circle:</strong> {{ $event->circle?->name ?? '-' }}</div>
            <div class="col-md-3"><strong>Mode:</strong> {{ $event->mode }}</div>
            <div class="col-md-3"><strong>Recurrence:</strong> {{ $event->recurrence_type ?? 'none' }}</div>
            <div class="col-md-6"><strong>Location:</strong> @if(!empty($event->metadata['google_maps_url']))<a href="{{ $event->metadata['google_maps_url'] }}" target="_blank" rel="noopener">{{ $event->location_text ?? 'Open map' }}</a>@else{{ $event->location_text ?? '-' }}@endif</div>
            <div class="col-md-6"><strong>Online:</strong> {{ $event->online_meeting_url ?? '-' }}</div>
            <div class="col-12"><strong>Description:</strong> {{ $event->description ?? '-' }}</div>
        </div>
    </div></div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Occurrences</span><a class="btn btn-sm btn-success" href="{{ route('admin.events.attendance', $event->id) }}">Attendance</a></div>
        <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Date</th><th>Start</th><th>End</th><th>Registered</th><th>Checked-in</th><th>Public Visitor Form Link</th><th>Status</th></tr></thead><tbody>
        @forelse($event->occurrences as $occurrence)
            @php
                $visitorFormUrl = url('/events/'.$event->id.'/occurrences/'.$occurrence->id.'/visitor-register');
            @endphp
            <tr>
                <td>{{ optional($occurrence->occurrence_date)->format('d M Y') }}</td>
                <td>{{ optional($occurrence->start_at)->format('d M Y h:i A') }}</td>
                <td>{{ optional($occurrence->end_at)->format('d M Y h:i A') }}</td>
                <td>
                    <a href="{{ route('admin.events.total-registered', ['event_id' => $event->id, 'occurrence_id' => $occurrence->id]) }}" class="font-bold text-indigo-600 hover:text-indigo-800 no-underline">
                        {{ $occurrence->registered_count ?? 0 }}
                    </a>
                </td>
                <td>{{ $occurrence->checked_in_count ?? 0 }}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 320px;">
                            <input type="text" class="form-control" value="{{ $visitorFormUrl }}" readonly id="formUrl_{{ $occurrence->id }}">
                            <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText('{{ $visitorFormUrl }}'); alert('Visitor Registration Form Link copied to clipboard!');">Copy</button>
                            <a href="{{ $visitorFormUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">Open</a>
                        </div>
                        <button type="button" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addDirectVisitorModal_{{ $occurrence->id }}">
                            <i class="bi bi-person-plus-fill"></i> Add Visitor
                        </button>
                    </div>

                    <!-- Direct Add Visitor Modal -->
                    <div class="modal fade" id="addDirectVisitorModal_{{ $occurrence->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content shadow text-start" style="border-radius: 12px; overflow: hidden;">
                                <form method="POST" action="{{ route('admin.events.occurrences.add-visitor', [$event->id, $occurrence->id]) }}">
                                    @csrf
                                    <div class="modal-header bg-dark text-white py-3">
                                        <h5 class="modal-title fs-6 mb-0">Register Visitor Directly</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 bg-light">
                                        <div class="mb-3 text-muted small fw-semibold">
                                            This will instantly generate a free Event Pass QR Code and register them in our database as a peer.
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted mb-1">Visitor First Name *</label>
                                                <input type="text" name="visitor_first_name" class="form-control form-control-sm" required placeholder="First Name">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted mb-1">Visitor Last Name *</label>
                                                <input type="text" name="visitor_last_name" class="form-control form-control-sm" required placeholder="Last Name">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted mb-1">Visitor Email *</label>
                                                <input type="email" name="visitor_email" class="form-control form-control-sm" required placeholder="Visitor Email Address">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted mb-1">Visitor Phone *</label>
                                                <input type="text" name="visitor_phone" class="form-control form-control-sm" required placeholder="Visitor Mobile Number">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted mb-1">Company</label>
                                                <input type="text" name="visitor_company" class="form-control form-control-sm" placeholder="Company Name">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted mb-1">City</label>
                                                <input type="text" name="visitor_city" class="form-control form-control-sm" placeholder="City">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted mb-1">Designation</label>
                                                <input type="text" name="visitor_designation" class="form-control form-control-sm" placeholder="Designation">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted mb-1">Business Brief</label>
                                                <textarea name="visitor_business_brief" class="form-control form-control-sm" rows="2" placeholder="Brief details about visitor business..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 bg-light pt-0">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary">Add Visitor & Send Mail</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </td>
                <td>{{ $occurrence->status }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No occurrences generated.</td></tr>
        @endforelse
        </tbody></table></div>
    </div>

    <div class="card mt-3" id="registrations-section">
        <div class="card-header">Registrations</div>
        <div class="table-responsive"><table class="table table-striped align-middle mb-0"><thead><tr><th>Attendee</th><th>Date</th><th>Type</th><th>Gateway</th><th>Payment</th><th>Payment ref</th><th>Amount</th><th>Invoice</th><th class="text-center">QR</th><th>Check-in</th></tr></thead><tbody>
        @forelse($event->registrations as $registration)
            @php
                $attendeeName = $registration->user ? ($registration->user->display_name ?? trim(($registration->user->first_name ?? '').' '.($registration->user->last_name ?? ''))) : $registration->visitor_name;
                $attendeeEmail = $registration->user?->email ?? $registration->visitor_email;
                $hasQr = !empty($registration->qr_code_url) || !empty($registration->qr_code_path) || !empty($registration->qr_code_svg);
                $gateway = $registration->payment_gateway ?: null;
                $gatewayLabel = match ($gateway) {
                    'zoho_billing_payment_link' => 'Zoho Billing',
                    'razorpay' => 'Razorpay',
                    'none', 'not_required' => 'Not required',
                    default => $registration->payment_required ? ($gateway ?: 'Not required') : 'Not required',
                };
                $paymentStatus = strtolower((string) ($registration->payment_status ?? ''));
                $paymentLabel = in_array($paymentStatus, ['paid', 'success', 'completed'], true) ? 'Paid' : ($paymentStatus === 'failed' ? 'Failed' : ($paymentStatus === 'pending' ? 'Pending' : ($registration->payment_status ?? '—')));
                $paymentBadge = in_array($paymentStatus, ['paid', 'success', 'completed'], true) ? 'success' : ($paymentStatus === 'failed' ? 'danger' : 'warning');
                $qrAvailable = $hasQr && (! $registration->payment_required || in_array($paymentStatus, ['paid', 'success', 'completed'], true));
            @endphp
            <tr>
                <td>{{ $attendeeName ?: '—' }}<br><small class="text-muted">{{ $attendeeEmail ?: '—' }}</small></td>
                <td>{{ optional($registration->occurrence?->start_at)->format('d M Y') ?: '—' }}</td>
                <td>{{ $registration->registration_type ?: ($registration->user_id ? 'member' : 'visitor') }}</td>
                <td>{{ $gatewayLabel }}</td>
                <td><span class="badge bg-{{ $paymentBadge }}">{{ $paymentLabel }}</span></td>
                <td>{{ $registration->zoho_payment_id ?? $registration->razorpay_payment_id ?? $registration->razorpay_order_id ?? $registration->zoho_payment_link_id ?? '—' }}</td>
                <td>{{ $registration->amount !== null ? trim(($registration->currency ?? 'INR').' '.$registration->amount) : '—' }}</td>
                <td>
                    {{ $registration->zoho_invoice_number ?? $registration->zoho_invoice_id ?? '—' }}
                    @if($registration->zoho_invoice_pdf_url || $registration->zoho_invoice_url)
                        <br><a href="{{ $registration->zoho_invoice_pdf_url ?: $registration->zoho_invoice_url }}" target="_blank" rel="noopener">Download</a>
                    @endif
                    @if(!$registration->zoho_invoice_id && $registration->payment_status === 'paid')
                        <form method="POST" action="{{ route('admin.events.registrations.sync-zoho-invoice', $registration->id) }}" class="mt-1">@csrf<button class="btn btn-sm btn-outline-primary">Retry Zoho sync</button></form>
                    @endif
                    @if($registration->zoho_invoice_sync_error)<small class="text-danger d-block">Sync failed</small>@endif
                </td>
                @php
                    $inlineSvg = $qrAvailable ? ($registration->qr_code_svg ?: (function() use ($registration) {
                        try {
                            $token = $registration->qr_token ?: app(\App\Services\Events\EventQrService::class)->generateToken();
                            $payload = app(\App\Services\Events\EventQrService::class)->payload($token);
                            $reflection = new \ReflectionClass(app(\App\Services\Events\EventQrService::class));
                            $method = $reflection->getMethod('makeSvg');
                            $method->setAccessible(true);
                            return $method->invoke(app(\App\Services\Events\EventQrService::class), $payload);
                        } catch (\Throwable $e) {
                            return null;
                        }
                    })()) : null;
                    $qrUrl = $qrAvailable ? app(\App\Services\Events\EventRegistrationQrService::class)->qrCodeUrl($registration) : null;
                @endphp
                <td class="text-center">
                    @if($registration->qr_status === 'expired')
                        <span class="badge bg-danger mb-1 d-inline-block">Expired</span>
                    @else
                        <span class="badge bg-{{ $qrAvailable ? 'success' : 'secondary' }} mb-1 d-inline-block">{{ $qrAvailable ? 'Generated' : 'Pending' }}</span>
                    @endif
                    @if($inlineSvg)
                        <div class="d-block mt-1">
                            <button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#qrModal_{{ $registration->id }}" title="Click to view full size QR Code">
                                <div style="background: #ffffff; padding: 4px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer;">
                                    <div style="width: 54px; height: 54px; display: flex; align-items: center; justify-content: center;">
                                        {!! $inlineSvg !!}
                                    </div>
                                </div>
                            </button>

                            <!-- High-Res QR Code Modal -->
                            <div class="modal fade" id="qrModal_{{ $registration->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                    <div class="modal-content text-center" style="border-radius: 12px; overflow: hidden;">
                                        <div class="modal-header bg-dark text-white py-2">
                                            <h6 class="modal-title mb-0 fs-6">QR Code Pass</h6>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-light">
                                            <div style="background: #ffffff; padding: 16px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                                <div style="width: 220px; height: 220px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                                    {!! $inlineSvg !!}
                                                </div>
                                            </div>
                                            <div class="mt-3 text-muted small fw-semibold">
                                                {{ $attendeeName }}
                                            </div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                Registration ID: {{ $registration->id }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif($qrUrl)
                        <div class="mt-1">
                            <a href="{{ $qrUrl }}" target="_blank" rel="noopener" title="Click to open QR Code image">
                                <img src="{{ $qrUrl }}" alt="QR Code" style="width: 54px; height: 54px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 4px; padding: 2px; background: #fff;" />
                            </a>
                        </div>
                    @endif
                </td>
                <td>{{ $registration->checkin_status }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No registrations found.</td></tr>
        @endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
