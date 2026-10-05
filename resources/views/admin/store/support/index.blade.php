@extends('admin.layouts.app')

@section('title', 'Peers Store — Support Helpdesk')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Support Helpdesk</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-headset text-primary"></i> Support Helpdesk & Inquiries
            </h1>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-3 gap-1">
        @foreach($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active bg-primary' : 'bg-light text-dark' }} py-1.5 px-3 rounded-pill fw-medium" href="{{ route('admin.store.support.index', ['tab' => $key, 'search' => $search]) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.support.index') }}" class="row g-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Ticket #, Subject, Order #, Peer Name..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.support.index', ['tab' => $tab]) }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tickets Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Ticket ID</th>
                            <th style="width: 25%;">Subject</th>
                            <th style="width: 20%;">Peer / Customer</th>
                            <th style="width: 15%;">Order Reference</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4 text-end" style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('admin.store.support.show', $ticket->id) }}" class="fw-bold text-decoration-none text-primary">
                                        #TICK-{{ $ticket->id }}
                                    </a>
                                    <div class="text-muted small">{{ $ticket->created_at ? $ticket->created_at->format('d M Y') : '—' }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $ticket->subject }}</div>
                                    <small class="text-muted">{{ ucfirst($ticket->category ?? 'General Store') }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $ticket->user->name ?? 'Peer #' . $ticket->user_id }}</div>
                                    <small class="text-muted">{{ $ticket->user->phone_number ?? $ticket->user->mobile ?: '—' }}</small>
                                </td>
                                <td>
                                    @if($ticket->order_id)
                                        <a href="{{ route('admin.store.orders.show', $ticket->order_id) }}" target="_blank" class="badge bg-light text-primary border text-decoration-none">
                                            #{{ $ticket->order->order_number ?? $ticket->order_id }}
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ticket->status === 'open')
                                        <span class="badge bg-warning text-dark">Open</span>
                                    @elseif($ticket->status === 'in_progress')
                                        <span class="badge bg-info text-white">In Progress</span>
                                    @elseif($ticket->status === 'resolved')
                                        <span class="badge bg-success text-white">Resolved</span>
                                    @elseif($ticket->status === 'closed')
                                        <span class="badge bg-secondary text-white">Closed</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($ticket->status) }}</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('admin.store.support.show', $ticket->id) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-chat-dots"></i> Open Chat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    No support tickets in this view.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($tickets->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
