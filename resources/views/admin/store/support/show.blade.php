@extends('admin.layouts.app')

@section('title', 'Peers Store — Ticket #TICK-' . $ticket->id)

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.support.index') }}" class="text-decoration-none text-muted">Support Helpdesk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#TICK-{{ $ticket->id }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-headset text-primary"></i> {{ $ticket->subject }}
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.support.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Helpdesk
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Chat Thread --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-chat-left-text text-primary"></i> Conversation Thread
                    </h5>
                    <span class="badge bg-light text-dark border">Category: {{ ucfirst($ticket->category ?? 'Store') }}</span>
                </div>
                <div class="card-body p-4" style="max-height: 500px; overflow-y: auto; background-color: #f8fafc;">
                    {{-- Initial Ticket Description --}}
                    <div class="d-flex mb-4">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                            {{ strtoupper(substr($ticket->user->name ?? 'P', 0, 1)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="bg-white p-3 rounded-3 shadow-sm border">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark">{{ $ticket->user->name ?? 'Peer' }} (Original Query)</span>
                                    <span class="text-muted small">{{ $ticket->created_at->format('d M Y, h:i A') }}</span>
                                </div>
                                <div class="text-dark">{{ $ticket->description ?: $ticket->subject }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Messages Thread --}}
                    @foreach($messages as $msg)
                        @php
                            $isAdmin = (strtolower($msg->sender_type ?? '') === 'admin');
                            $adminName = $msg->admin?->display_name ?: (trim(($msg->admin?->first_name ?? '') . ' ' . ($msg->admin?->last_name ?? '')) ?: ($msg->admin?->name ?? 'Store Support Admin'));
                            $peerName = $msg->user?->display_name ?: (trim(($msg->user?->first_name ?? '') . ' ' . ($msg->user?->last_name ?? '')) ?: ($ticket->user?->display_name ?: (trim(($ticket->user?->first_name ?? '') . ' ' . ($ticket->user?->last_name ?? '')) ?: ($ticket->user?->name ?? 'Peer'))));
                        @endphp
                        <div class="d-flex mb-4 {{ $isAdmin ? 'justify-content-end' : '' }}">
                            @if(!$isAdmin)
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center fw-bold me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                                    {{ strtoupper(substr($peerName, 0, 1)) }}
                                </div>
                            @endif
                            <div class="flex-grow-1" style="max-width: 80%;">
                                <div class="{{ $isAdmin ? 'bg-primary text-white' : 'bg-white text-dark' }} p-3 rounded-3 shadow-sm border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold {{ $isAdmin ? 'text-white' : 'text-dark' }}">
                                            {{ $isAdmin ? $adminName : $peerName }}
                                        </span>
                                        <span class="small {{ $isAdmin ? 'text-white-50' : 'text-muted' }}">{{ $msg->created_at ? $msg->created_at->format('d M, h:i A') : '' }}</span>
                                    </div>
                                    <div>{{ $msg->message ?: ($msg->body ?: '') }}</div>
                                </div>
                            </div>
                            @if($isAdmin)
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold ms-3 flex-shrink-0" style="width: 40px; height: 40px;">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Reply Composer --}}
                <div class="card-footer bg-white p-3 border-top">
                    <form method="POST" action="{{ route('admin.store.support.reply', $ticket->id) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted">Quick Canned Response Template:</label>
                            <select class="form-select form-select-sm" onchange="if(this.value) { document.getElementById('replyTextarea').value = this.value; }">
                                <option value="">Select a canned reply template...</option>
                                @foreach($cannedReplies as $title => $content)
                                    <option value="{{ $content }}">{{ $title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="message" id="replyTextarea" class="form-control" rows="3" placeholder="Type your response to the peer member..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mark_resolved" value="1" id="resolveCheck">
                                <label class="form-check-label small" for="resolveCheck">Mark ticket as resolved upon sending</label>
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                                <i class="bi bi-send"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right: Ticket Info & Status --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0">Ticket Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.store.support.status', $ticket->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Change Ticket Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                    </form>

                    <div class="border-top pt-3">
                        <span class="text-muted small">Peer Customer:</span>
                        <div class="fw-bold text-dark">{{ $ticket->user->name ?? 'Peer #' . $ticket->user_id }}</div>
                        <div class="small text-muted">
                            <i class="bi bi-telephone me-1"></i> {{ $ticket->user->phone_number ?? $ticket->user->mobile ?: '—' }}<br>
                            <i class="bi bi-envelope me-1"></i> {{ $ticket->user->email ?? '—' }}
                        </div>
                    </div>

                    @if($ticket->order_id)
                        <div class="border-top pt-3 mt-3">
                            <span class="text-muted small">Associated Order:</span>
                            <div class="fw-bold text-dark">#{{ $ticket->order->order_number ?? $ticket->order_id }}</div>
                            <a href="{{ route('admin.store.orders.show', $ticket->order_id) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-eye"></i> View Associated Order
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
