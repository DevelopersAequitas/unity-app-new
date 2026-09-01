@extends('admin.layouts.app')

@section('title', 'Account Deletion Email Management')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Account Deletion Email Management</h4>
            <p class="text-muted small mb-0">Preview deletion module email templates and manually trigger/resend them to users.</p>
        </div>
        <a href="{{ route('admin.account-deletion.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Requests
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Selector Section -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body bg-light rounded">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <label for="request_selector" class="form-label font-weight-bold text-dark mb-1">Target Account Deletion Request</label>
                    <p class="text-muted small mb-2">Select a user's deletion request to preview the templates with their specific details or send emails directly to them.</p>
                    <select id="request_selector" class="form-select form-select-md">
                        <option value="">-- Use Dummy/Mock User Data --</option>
                        @foreach($requests as $req)
                            @if($req->user)
                                <option value="{{ $req->id }}">
                                    [{{ ucfirst($req->status) }}] {{ $req->user->display_name ?? ($req->user->first_name . ' ' . $req->user->last_name) }} ({{ $req->user->email }})
                                </option>
                            @else
                                <option value="{{ $req->id }}">
                                    [{{ ucfirst($req->status) }}] Deleted User (Request: {{ substr($req->id, 0, 8) }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Listing -->
    <div class="row">
        <!-- Template A -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="card-title mb-0 fs-6">
                        <i class="bi bi-envelope-open me-2 text-warning"></i>Template A: Request Submitted
                    </h5>
                    <span class="badge bg-secondary">System Mailable</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Automatically sent to the user when they submit an account deletion request. Notifies them that the request has been successfully queued and is under review by our compliance team.
                        </p>
                        <hr />
                        <div class="small text-dark mb-3">
                            <strong>Subject:</strong> Account Deletion Request Received
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="openPreview('requested')">
                            <i class="bi bi-eye me-1"></i> Preview Template
                        </button>
                        <button type="button" class="btn btn-primary btn-sm flex-fill" onclick="triggerSend('requested')">
                            <i class="bi bi-send me-1"></i> Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Template B -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-3">
                    <h5 class="card-title mb-0 fs-6">
                        <i class="bi bi-envelope-check me-2 text-success"></i>Template B: Account Deleted
                    </h5>
                    <span class="badge bg-secondary">System Mailable</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Sent to the user when an admin approves their request or completes their deletion. Notifies them that their account and associated data have been permanently removed from our systems.
                        </p>
                        <hr />
                        <div class="small text-dark mb-3">
                            <strong>Subject:</strong> Account Successfully Deleted
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-fill" onclick="openPreview('deleted')">
                            <i class="bi bi-eye me-1"></i> Preview Template
                        </button>
                        <button type="button" class="btn btn-primary btn-sm flex-fill" onclick="triggerSend('deleted')">
                            <i class="bi bi-send me-1"></i> Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Send Log -->
    <div class="card mt-2 shadow-sm border-0">
        <div class="card-header bg-light d-flex align-items-center justify-content-between py-3">
            <h6 class="mb-0 text-dark"><i class="bi bi-journal-text me-2"></i>Recent Manual Trigger Logs (Session-based)</h6>
            @if(session('manual_email_logs'))
                <button type="button" class="btn btn-link text-danger p-0 small text-decoration-none" onclick="document.getElementById('clearLogsForm').submit()">
                    Clear Logs
                </button>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Timestamp</th>
                            <th>Template</th>
                            <th>Recipient</th>
                            <th class="pe-4 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(session('manual_email_logs', []) as $log)
                            <tr>
                                <td class="ps-4">{{ $log['timestamp'] }}</td>
                                <td><strong>{{ $log['template'] }}</strong></td>
                                <td>{{ $log['recipient'] }}</td>
                                <td class="pe-4 text-end">
                                    @if($log['status'] === 'success')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Sent Successfully</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="{{ $log['status'] }}">Failed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    No manual emails have been triggered in the current session.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Email Preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Email Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background-color: #eaeaea; height: 500px;">
                <iframe id="previewIframe" src="" style="width: 100%; height: 100%; border: none; border-radius: 0 0 6px 6px;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Forms for actions -->
<form id="sendEmailForm" method="POST" action="" class="d-none">
    @csrf
    <input type="hidden" name="request_id" id="sendEmailFormRequestId" value="">
</form>

<form id="clearLogsForm" method="POST" action="" class="d-none">
    @csrf
    <!-- Simple endpoint to clear logs or we can let session clear automatically. We will handle clearing logs directly in web.php if needed -->
</form>

<script>
    function openPreview(template) {
        const requestId = document.getElementById('request_selector').value;
        const previewUrl = `{{ url('admin/account-deletion-requests/emails') }}/${template}/preview?request_id=${requestId}`;
        
        document.getElementById('previewIframe').src = previewUrl;
        
        const myModal = new bootstrap.Modal(document.getElementById('previewModal'));
        myModal.show();
    }

    function triggerSend(template) {
        const requestId = document.getElementById('request_selector').value;
        if (!requestId) {
            alert('Please select a valid Account Deletion Request from the dropdown to trigger this email.');
            return;
        }

        if (confirm(`Are you sure you want to manually send this email to the selected user?`)) {
            const form = document.getElementById('sendEmailForm');
            form.action = `{{ url('admin/account-deletion-requests/emails') }}/${template}/send`;
            document.getElementById('sendEmailFormRequestId').value = requestId;
            form.submit();
        }
    }
</script>
@endsection
