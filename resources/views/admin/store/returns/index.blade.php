@extends('admin.layouts.app')

@section('title', 'Peers Store — Return Requests Queue')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Return Requests</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-arrow-return-left text-primary"></i> Return & Replacement Requests
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.returns.refunds') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-cash-stack"></i> Processed Coin Refunds
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-3 gap-1">
        @foreach($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active bg-primary' : 'bg-light text-dark' }} py-1.5 px-3 rounded-pill fw-medium" href="{{ route('admin.store.returns.index', ['tab' => $key, 'search' => $search]) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.returns.index') }}" class="row g-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Return #, Order #, Customer Name..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.returns.index', ['tab' => $tab]) }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Returns Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Return ID & Date</th>
                            <th style="width: 20%;">Order & Customer</th>
                            <th style="width: 25%;">Reason / Notes</th>
                            <th style="width: 15%;">Refund Amount</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4 text-end" style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $ret)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('admin.store.returns.show', $ret->id) }}" class="fw-bold text-decoration-none text-primary fs-6">
                                        #RET-{{ $ret->id }}
                                    </a>
                                    <div class="text-muted small">{{ $ret->created_at ? $ret->created_at->format('d M Y') : '—' }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $ret->user->name ?? 'Peer #' . $ret->user_id }}</div>
                                    <small class="text-muted">Order: <code>#{{ $ret->order->order_number ?? $ret->order_id }}</code></small>
                                </td>
                                <td>
                                    <div class="text-dark small fw-semibold">{{ ucfirst(str_replace('_', ' ', $ret->reason)) }}</div>
                                    @if($ret->notes)
                                        <div class="text-muted small" title="{{ $ret->notes }}">{{ Str::limit($ret->notes, 40) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-primary">{{ number_format($ret->refund_coins ?? $ret->order->total_coins ?? 0) }}</span>
                                    <small class="text-muted">Coins</small>
                                </td>
                                <td>
                                    @if($ret->status === 'pending')
                                        <span class="badge bg-warning text-dark">Pending Review</span>
                                    @elseif($ret->status === 'approved')
                                        <span class="badge bg-info text-white">Approved</span>
                                    @elseif($ret->status === 'completed')
                                        <span class="badge bg-success text-white">Refunded</span>
                                    @elseif($ret->status === 'rejected')
                                        <span class="badge bg-danger text-white">Rejected</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($ret->status) }}</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('admin.store.returns.show', $ret->id) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> Inspect
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-arrow-return-left fs-1 d-block mb-2 text-secondary"></i>
                                    No return requests found in this queue.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($returns->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $returns->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
