@extends('admin.layouts.app')

@section('title', 'Member 30-Day WhatsApp Journey - ' . ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))

@section('content')
<div class="container-fluid px-0">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.journey.index') }}">30-Day Journey</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->display_name ?: 'Member Details' }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="bi bi-person-badge text-primary me-2"></i>{{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}
            </h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.whatsapp.journey.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Journey List
            </a>
            <form action="{{ route('admin.whatsapp.journey.initialize', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Re-initialize 30-day journey schedule for this member? Existing entries will be preserved.');">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>Re-initialize Journey
                </button>
            </form>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Flash Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Member Overview & Smart Skip Readiness Card -->
    <div class="row g-3 mb-4">
        <!-- Member Profile Info -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-gray-800">
                        <i class="bi bi-info-circle me-2 text-primary"></i>Member Information
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            @if(!empty($user->profile_photo_url))
                                <img src="{{ $user->profile_photo_url }}" class="rounded-circle border" width="56" height="56" alt="Avatar" style="object-fit: cover;">
                            @else
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 56px; height: 56px; font-size: 1.25rem;">
                                    {{ strtoupper(substr($user->first_name ?: $user->name ?: 'P', 0, 1)) }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-dark">{{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}</div>
                            <div class="text-muted small">
                                First Name resolved: <span class="badge bg-light text-dark border font-monospace">{{ trim($user->first_name ?: explode(' ', $user->display_name ?? 'Peer')[0]) }}</span>
                            </div>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted ps-0" style="width: 140px;">WhatsApp Phone:</th>
                                <td>
                                    @if(!empty($user->phone))
                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                                            <i class="bi bi-whatsapp me-1"></i>{{ $user->phone }}
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Missing Phone</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Email:</th>
                                <td class="text-dark">{{ $user->email }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Designation / Role:</th>
                                <td class="text-dark">{{ $user->designation ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Company:</th>
                                <td class="text-dark">{{ $user->company_name ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Registered On:</th>
                                <td class="text-dark">{{ $user->created_at ? $user->created_at->format('d M Y, h:i A') : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Smart Skipping Conditions (Days 1, 2, 3) -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-gray-800">
                        <i class="bi bi-shield-check me-2 text-success"></i>Smart Conditional Skipping Status (Days 1–3)
                    </h6>
                    <span class="badge bg-light text-muted border">Automatic Profile Awareness</span>
                </div>
                <div class="card-body pt-0">
                    <p class="small text-muted mb-3">
                        To respect new members' time, our intelligent onboarding pipeline automatically checks profile completion before dispatching reminders. If already completed, the day is skipped without sending unnecessary alerts.
                    </p>

                    <div class="row g-2">
                        <!-- Day 1 Profile Bio Check -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 {{ ($profileReadiness['has_bio'] && $profileReadiness['has_designation']) ? 'bg-success-subtle border-success-subtle' : 'bg-light' }}">
                                <div class="fw-bold small mb-1">
                                    Day 1: Profile Completeness
                                </div>
                                <div class="small mb-2">
                                    @if($profileReadiness['has_bio'] && $profileReadiness['has_designation'])
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                                        <div class="text-success small mt-1">Bio & designation provided. Day 1 will auto-skip!</div>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Incomplete</span>
                                        <div class="text-muted small mt-1">Bio or headline missing. Day 1 will send.</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Day 2 Business Info Check -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 {{ ($profileReadiness['has_company'] && $profileReadiness['has_business_info']) ? 'bg-success-subtle border-success-subtle' : 'bg-light' }}">
                                <div class="fw-bold small mb-1">
                                    Day 2: Business Info
                                </div>
                                <div class="small mb-2">
                                    @if($profileReadiness['has_company'] && $profileReadiness['has_business_info'])
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                                        <div class="text-success small mt-1">Company & business details present. Day 2 will auto-skip!</div>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Incomplete</span>
                                        <div class="text-muted small mt-1">Company details missing. Day 2 will send.</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Day 3 Profile Photograph Check -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 {{ $profileReadiness['has_photo'] ? 'bg-success-subtle border-success-subtle' : 'bg-light' }}">
                                <div class="fw-bold small mb-1">
                                    Day 3: Profile Photo
                                </div>
                                <div class="small mb-2">
                                    @if($profileReadiness['has_photo'])
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complete</span>
                                        <div class="text-success small mt-1">Photo uploaded. Day 3 will auto-skip!</div>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Incomplete</span>
                                        <div class="text-muted small mt-1">Photo missing. Day 3 will send.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 p-2 bg-light rounded text-muted small d-flex align-items-center">
                        <i class="bi bi-lightbulb text-warning me-2 fs-5"></i>
                        <span>Days 4 through 30 (The Mission, Discover Peers, One-To-One, etc.) proceed progressively one day at a time regardless of profile completion.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 30-Day Journey Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-gray-800">
                <i class="bi bi-calendar3 me-2 text-primary"></i>30-Day Progressive Delivery Schedule
            </h6>
            <span class="badge bg-light text-muted border">Language: en_IN &bull; Meta Approved Templates</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 80px;">Day</th>
                        <th style="min-width: 220px;">Template & Topic</th>
                        <th>CTA Button</th>
                        <th style="min-width: 170px;">Scheduled For</th>
                        <th>Status</th>
                        <th style="min-width: 160px;">Delivery Info</th>
                        <th class="text-end pe-3" style="min-width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @for($day = 1; $day <= 30; $day++)
                        @php
                            $send = $sendsByDay->get($day);
                            $meta = $templateMeta[$day] ?? ['name' => "day_{$day}_template", 'title' => "Day {$day}", 'cta' => 'Enter Unity', 'language' => 'en_IN'];
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-dark rounded-pill px-2 py-1">Day {{ $day }}</span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $meta['title'] }}</div>
                                <div class="font-monospace small text-muted">
                                    {{ $meta['name'] }}
                                    <span class="badge bg-light text-secondary border">en_IN</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>{{ $meta['cta'] }}
                                </span>
                            </td>
                            <td>
                                @if($send && $send->scheduled_at)
                                    <span class="small {{ $send->scheduled_at->isPast() ? 'text-muted' : 'text-primary fw-semibold' }}">
                                        {{ $send->scheduled_at->format('d M Y, h:i A') }}
                                    </span>
                                @else
                                    <span class="text-muted small">Not Scheduled</span>
                                @endif
                            </td>
                            <td>
                                @if(!$send)
                                    <span class="badge bg-secondary">Uninitialized</span>
                                @elseif($send->status === 'sent')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check2-all me-1"></i>Sent
                                    </span>
                                @elseif($send->status === 'skipped')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" title="{{ $send->skip_reason }}">
                                        <i class="bi bi-skip-forward me-1"></i>Skipped
                                    </span>
                                @elseif($send->status === 'failed')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="{{ $send->error_message }}">
                                        <i class="bi bi-x-circle me-1"></i>Failed
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                        <i class="bi bi-clock me-1"></i>Scheduled
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($send && $send->status === 'sent')
                                    <div class="small text-success">{{ $send->sent_at ? $send->sent_at->format('d M, h:i A') : 'Sent' }}</div>
                                    @if($send->provider_message_id)
                                        <div class="font-monospace text-muted small" style="font-size: 0.72rem;" title="{{ $send->provider_message_id }}">
                                            {{ Str::limit($send->provider_message_id, 16) }}
                                        </div>
                                    @endif
                                @elseif($send && $send->status === 'skipped')
                                    <div class="small text-muted" style="max-width: 200px;">
                                        {{ Str::limit($send->skip_reason ?? 'Smart profile skip', 45) }}
                                    </div>
                                @elseif($send && $send->status === 'failed')
                                    <div class="small text-danger" style="max-width: 200px;">
                                        {{ Str::limit($send->error_message ?? 'Failed', 45) }}
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                @if($send)
                                    <!-- Send / Test Trigger Button -->
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sendModal{{ $send->id }}">
                                        <i class="bi bi-send me-1"></i>{{ $send->status === 'sent' ? 'Resend' : 'Send Now' }}
                                    </button>

                                    <!-- View Details Button -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal{{ $send->id }}" title="View Payload & Response JSON">
                                        <i class="bi bi-code-slash"></i>
                                    </button>

                                    <!-- Send Modal -->
                                    <div class="modal fade text-start" id="sendModal{{ $send->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.whatsapp.journey.send_day', $send->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-whatsapp text-success me-2"></i>Send Day {{ $day }} — {{ $meta['title'] }}
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="small text-muted mb-3">
                                                            Template: <code>{{ $meta['name'] }}</code> (en_IN)<br>
                                                            Parameter <code>@{{1}}</code>: <strong>{{ trim($user->first_name ?: explode(' ', $user->display_name ?? 'Peer')[0]) }}</strong>
                                                        </p>

                                                        <div class="mb-3">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <label class="form-label small fw-bold mb-0">Recipient Mobile Number</label>
                                                                @if(!empty($userPhone))
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                                        <i class="bi bi-person-check-fill me-1"></i>Member's Registered Phone
                                                                    </span>
                                                                @else
                                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                                        <i class="bi bi-exclamation-circle me-1"></i>No phone in DB — enter below
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <div class="input-group">
                                                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                                                <input type="text" name="override_phone" class="form-control font-monospace" value="{{ $userPhone }}" placeholder="e.g. +919876543210" required>
                                                            </div>
                                                            <div class="form-text small">
                                                                @if(!empty($userPhone))
                                                                    Pre-filled with member's registered number. You can change this to a test number (e.g. your own phone) to verify formatting first.
                                                                @else
                                                                    Member does not have a phone number on their profile. Enter their WhatsApp number with country code (e.g. <code>+919876543210</code>) and it will be saved to their profile.
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="checkbox" name="force" value="1" id="forceSend{{ $send->id }}" {{ $day > 3 ? 'checked' : '' }}>
                                                            <label class="form-check-label small" for="forceSend{{ $send->id }}">
                                                                Force send even if smart profile skip conditions are met
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="bi bi-send-fill me-1"></i>Dispatch WhatsApp Message
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detail Modal -->
                                    <div class="modal fade text-start" id="detailModal{{ $send->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-code-slash text-primary me-2"></i>Day {{ $day }} Payload & Provider Response
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <h6 class="fw-bold small text-uppercase text-muted">Sent Payload JSON</h6>
                                                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($send->payload_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'No payload logged yet.' }}</code></pre>

                                                    <h6 class="fw-bold small text-uppercase text-muted mt-3">Meta API Response JSON</h6>
                                                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 250px; overflow-y: auto;"><code>{{ json_encode($send->provider_response_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'No provider response logged yet.' }}</code></pre>

                                                    @if($send->error_message)
                                                        <div class="alert alert-danger mt-3 mb-0 small">
                                                            <strong>Error:</strong> {{ $send->error_message }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
