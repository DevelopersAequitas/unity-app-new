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
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-dark fw-bold">Customer Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Contact Name</span>
                            <span class="text-dark fw-semibold">{{ $ticket->contact_name }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Email Address</span>
                            <a href="mailto:{{ $ticket->email }}" class="text-decoration-none fw-semibold">{{ $ticket->email }}</a>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Associated App Account</span>
                            @if($ticket->user)
                                <span class="text-success"><i class="bi bi-person-check-fill me-1"></i>Linked Account (ID: {{ $ticket->user->id }})</span>
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
                            <label for="admin_note" class="form-label text-muted small fw-semibold">Admin Notes</label>
                            <textarea name="admin_note" id="admin_note" rows="5" class="form-control" placeholder="Add details or response notes here...">{{ $ticket->admin_note }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
