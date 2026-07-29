@extends('admin.layouts.app')

@section('title', 'Support Ticket #' . $ticket->ticket_number)

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Support Ticket #{{ $ticket->ticket_number }}</h4>
            <p class="text-muted small mb-0">View full ticket details and update status/notes.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
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

    <div class="row g-4">
        <!-- Main Content Column -->
        <div class="col-lg-8">
            <!-- Ticket Info Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-dark fw-bold">Ticket Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Subject</span>
                            <span class="fw-semibold text-dark fs-5">{{ $ticket->subject }}</span>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="text-muted small d-block">Submitted At</span>
                            <span class="text-dark">{{ $ticket->created_at->format('Y-m-d H:i:s') }} ({{ $ticket->created_at->diffForHumans() }})</span>
                        </div>
                    </div>

                    <hr class="text-muted opacity-25">

                    <div class="mb-4">
                        <span class="text-muted small d-block mb-2">Description</span>
                        <div class="bg-light p-3 rounded text-dark" style="white-space: pre-wrap; font-size: 15px; line-height: 1.6;">{{ $ticket->description }}</div>
                    </div>

                    @if($ticket->media_url)
                        <div class="mb-4">
                            <span class="text-muted small d-block mb-2">Attached Media</span>
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        @if(Str::startsWith($ticket->media_type, 'image'))
                                            <i class="bi bi-image text-primary fs-3 me-3"></i>
                                        @elseif(Str::startsWith($ticket->media_type, 'video'))
                                            <i class="bi bi-camera-video text-success fs-3 me-3"></i>
                                        @else
                                            <i class="bi bi-file-earmark-text text-secondary fs-3 me-3"></i>
                                        @endif
                                        <div>
                                            <span class="fw-semibold text-dark d-block">Attachment File</span>
                                            <small class="text-muted">Type: {{ $ticket->media_type }}</small>
                                        </div>
                                    </div>
                                    <a href="{{ $ticket->media_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open Attachment
                                    </a>
                                </div>
                                @if(Str::startsWith($ticket->media_type, 'image'))
                                    <div class="mt-3 text-center">
                                        <img src="{{ $ticket->media_url }}" alt="Attachment Preview" class="img-fluid rounded border shadow-sm" style="max-height: 350px;">
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact & User Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-dark fw-bold">Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Contact Name</span>
                            <span class="text-dark fw-semibold">
                                @if(!empty($ticket->user_id ?? $ticket->user?->id))
                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $ticket->user_id ?? $ticket->user?->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                        {{ $ticket->contact_name }}
                                    </a>
                                @else
                                    {{ $ticket->contact_name }}
                                @endif
                            </span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Email Address</span>
                            <a href="mailto:{{ $ticket->email }}" class="text-decoration-none fw-semibold">{{ $ticket->email }}</a>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Associated App Account</span>
                            @if($ticket->user)
                                <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $ticket->user->id }}', event);" class="text-success hover:underline no-underline fw-semibold">
                                    <i class="bi bi-person-check-fill me-1"></i>Linked Account (ID: {{ $ticket->user->id }})
                                </a>
                            @else
                                <span class="text-muted"><i class="bi bi-person-x-fill me-1"></i>No linked account (Guest Submission)</span>
                            @endif
                        </div>
                        @if($ticket->resolved_at)
                            <div class="col-md-6">
                                <span class="text-muted small d-block">Resolved At</span>
                                <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>{{ $ticket->resolved_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Panel Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-dark fw-bold">Manage Ticket</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.support-tickets.update', $ticket->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="status" class="form-label text-muted small fw-semibold">Ticket Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="priority" class="form-label text-muted small fw-semibold">Ticket Priority</label>
                            <select name="priority" id="priority" class="form-select">
                                <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="normal" {{ $ticket->priority === 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ $ticket->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="admin_note" class="form-label text-muted small fw-semibold">Admin Notes & Activity Log</label>
                            <textarea name="admin_note" id="admin_note" rows="8" class="form-control" placeholder="Add details or response notes here...">{{ $ticket->admin_note }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Direct Email Response Card (Full Width) -->
    <div class="row mt-4">
        <div class="col-12">
            <div id="emailResponseCardWrapper" class="email-card-fullscreen-wrapper">
                <div class="card shadow-sm border-primary border-opacity-25" id="emailResponseCard">
                    <div class="card-header bg-primary bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-primary fw-bold d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-paper-fill"></i>
                            <span>Send Email Response to Customer</span>
                        </h6>
                        <span class="badge bg-primary rounded-pill px-3 py-2">
                            <i class="bi bi-send me-1"></i>To: {{ $ticket->email }}
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.support-tickets.send-email', $ticket->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="email_subject" class="form-label text-dark small fw-semibold">Email Subject</label>
                                <input type="text" name="subject" id="email_subject" class="form-control @error('subject') is-invalid @enderror" 
                                       value="{{ old('subject', 'Re: [Ticket #' . $ticket->ticket_number . '] ' . $ticket->subject) }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="email_message" class="form-label text-dark small fw-semibold mb-0">Response Message / Answer</label>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-2 fs-7" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-magic me-1"></i>Quick Templates
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item small" href="javascript:void(0)" onclick="applyTemplate('resolved')">Resolution Confirmation</a></li>
                                            <li><a class="dropdown-item small" href="javascript:void(0)" onclick="applyTemplate('info_needed')">Request More Details</a></li>
                                            <li><a class="dropdown-item small" href="javascript:void(0)" onclick="applyTemplate('in_progress')">Issue Under Investigation</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <textarea name="message" id="email_message" rows="6" class="form-control @error('message') is-invalid @enderror" 
                                          placeholder="Type your response or solution to the user's issue here..." required>{{ old('message') }}</textarea>
                                @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Media Files & Attachments Section -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <label for="email_attachments" class="form-label text-dark small fw-semibold d-flex justify-content-between align-items-center mb-1">
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="bi bi-paperclip text-primary fs-6"></i>
                                        <span>Attach Media Files & Documents</span>
                                    </span>
                                    <small class="text-muted fw-normal">(Max 5 files, up to 20MB total: Images, Videos, PDFs, Documents, Archives)</small>
                                </label>
                                <input type="file" name="attachments[]" id="email_attachments" 
                                       class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror" 
                                       multiple 
                                       accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt" 
                                       onchange="previewSelectedFiles(this)">
                                @error('attachments')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('attachments.*')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div id="filePreviewContainer" class="mt-2 d-flex flex-wrap gap-2"></div>
                            </div>

                            <div class="row align-items-center g-3">
                                <div class="col-md-6">
                                    <label for="update_status" class="form-label text-muted small fw-semibold mb-1">Update Ticket Status on Send</label>
                                    <select name="status" id="update_status" class="form-select form-select-sm">
                                        <option value="">Keep Current Status ({{ ucfirst(str_replace('_', ' ', $ticket->status)) }})</option>
                                        <option value="in_progress">Set to In Progress</option>
                                        <option value="resolved" {{ $ticket->status !== 'resolved' ? 'selected' : '' }}>Set to Resolved</option>
                                        <option value="closed">Set to Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-md-end pt-md-3">
                                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2">
                                        <i class="bi bi-send-fill"></i> Send Email Response
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function applyTemplate(type) {
    const msgInput = document.getElementById('email_message');
    const statusSelect = document.getElementById('update_status');
    const contactName = @json($ticket->contact_name);
    const subject = @json($ticket->subject);

    if (type === 'resolved') {
        msgInput.value = `Hello ${contactName},\n\nWe have looked into your request regarding "${subject}" and resolved the issue.\n\nPlease verify on your side. If you continue to experience any issues or have additional questions, feel free to reply to this email.\n\nBest regards,\nPeers Global Support Team`;
        if (statusSelect) statusSelect.value = 'resolved';
    } else if (type === 'info_needed') {
        msgInput.value = `Hello ${contactName},\n\nThank you for reaching out to Peers Global Support. To help us investigate and solve your issue regarding "${subject}", could you please provide additional details or screenshots?\n\nWe look forward to hearing from you.\n\nBest regards,\nPeers Global Support Team`;
        if (statusSelect) statusSelect.value = 'in_progress';
    } else if (type === 'in_progress') {
        msgInput.value = `Hello ${contactName},\n\nWe are currently investigating your reported issue regarding "${subject}". Our technical team is working on resolving this as quickly as possible.\n\nWe will update you as soon as we have progress.\n\nBest regards,\nPeers Global Support Team`;
        if (statusSelect) statusSelect.value = 'in_progress';
    }
}

function previewSelectedFiles(input) {
    const container = document.getElementById('filePreviewContainer');
    container.innerHTML = '';

    if (!input.files || input.files.length === 0) {
        return;
    }

    Array.from(input.files).forEach((file, index) => {
        const fileBadge = document.createElement('div');
        fileBadge.className = 'badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm rounded';

        let iconClass = 'bi-file-earmark-text text-secondary';
        if (file.type.startsWith('image/')) {
            iconClass = 'bi-image text-primary';
        } else if (file.type.startsWith('video/')) {
            iconClass = 'bi-camera-video text-success';
        } else if (file.type.includes('pdf')) {
            iconClass = 'bi-file-earmark-pdf text-danger';
        } else if (file.type.includes('word') || file.name.endsWith('.doc') || file.name.endsWith('.docx')) {
            iconClass = 'bi-file-earmark-word text-info';
        } else if (file.type.includes('zip') || file.type.includes('rar')) {
            iconClass = 'bi-file-earmark-zip text-warning';
        }

        const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);

        let previewHtml = `<i class="bi ${iconClass} fs-5"></i>`;
        
        // If image file, create instant thumbnail preview
        if (file.type.startsWith('image/')) {
            const imgUrl = URL.createObjectURL(file);
            previewHtml = `<img src="${imgUrl}" alt="preview" class="rounded border" style="width: 32px; height: 32px; object-fit: cover;">`;
        }

        fileBadge.innerHTML = `
            ${previewHtml}
            <div class="text-start">
                <span class="fw-semibold d-block text-truncate" style="max-width: 180px;">${file.name}</span>
                <small class="text-muted" style="font-size: 11px;">${sizeInMb} MB</small>
            </div>
        `;

        container.appendChild(fileBadge);
    });
}
</script>
@endsection
