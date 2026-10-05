@extends('admin.layouts.app')

@section('title', 'Peers Store — Notification Logs')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notification Delivery Logs</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-bell-fill text-primary"></i> Store Notification & Delivery Logs
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

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.communication.logs') }}" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by Peer Name or Message Title..." value="{{ $search }}">
                </div>
                <div class="col-md-2">
                    <select name="channel" class="form-select">
                        <option value="">All Channels</option>
                        <option value="push" {{ $channelFilter === 'push' ? 'selected' : '' }}>Push Notification</option>
                        <option value="email" {{ $channelFilter === 'email' ? 'selected' : '' }}>Email</option>
                        <option value="sms" {{ $channelFilter === 'sms' ? 'selected' : '' }}>SMS</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="delivered" {{ $statusFilter === 'delivered' ? 'selected' : '' }}>Delivered</option>
                        <option value="sent" {{ $statusFilter === 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="failed" {{ $statusFilter === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.communication.logs') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Logs Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Timestamp</th>
                            <th style="width: 20%;">Recipient (Peer)</th>
                            <th style="width: 15%;">Channel</th>
                            <th style="width: 30%;">Title & Content</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4 text-end" style="width: 10%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ $log->created_at ? $log->created_at->format('d M Y') : '—' }}</div>
                                    <small class="text-muted">{{ $log->created_at ? $log->created_at->format('h:i A') : '' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $log->user?->name ?? 'Peer #' . $log->user_id }}</div>
                                    <small class="text-muted">{{ $log->user?->phone_number ?? $log->recipient ?? '—' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-chat-left-dots me-1"></i> {{ ucfirst($log->channel ?? 'Push') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $log->title }}</div>
                                    <div class="text-muted small" title="{{ $log->body ?? $log->message }}">{{ Str::limit($log->body ?? $log->message, 60) }}</div>
                                </td>
                                <td>
                                    @if($log->status === 'delivered' || $log->status === 'sent')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Delivered</span>
                                    @elseif($log->status === 'failed')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($log->status ?? 'Sent') }}</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <form method="POST" action="{{ route('admin.store.communication.logs.resend', $log->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Resend Notification">
                                            <i class="bi bi-arrow-repeat"></i> Resend
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-bell-slash fs-1 d-block mb-2 text-secondary"></i>
                                    No notification logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
