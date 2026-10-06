@extends('admin.layouts.app')

@section('title', 'Peers Store — Processed Coin Refunds')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.returns.index') }}" class="text-decoration-none text-muted">Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Refunds Log</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-cash-stack text-primary"></i> Processed Coin Refunds Audit Log
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.returns.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Returns Queue
            </a>
        </div>
    </div>

    {{-- Total Refunded KPI Banner --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-info bg-opacity-10 border-info border-opacity-25">
                <div class="card-body">
                    <span class="text-muted fw-semibold small text-uppercase">Total Refunded Coins</span>
                    <h3 class="fw-bold text-info my-2">{{ number_format($totalRefundedCoins) }}</h3>
                    <small class="text-muted">Total store coins restored to peer wallets</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.returns.refunds') }}" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by Order # or Peer Name..." value="{{ $search }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.returns.refunds') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Refunds Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Refund Date</th>
                            <th style="width: 20%;">Order & Peer</th>
                            <th style="width: 20%;">Refunded Coins</th>
                            <th style="width: 25%;">Breakdown (Earned / Bonus)</th>
                            <th class="pe-4 text-end" style="width: 20%;">Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refunds as $ref)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ $ref->created_at ? $ref->created_at->format('d M Y') : '—' }}</div>
                                    <small class="text-muted">{{ $ref->created_at ? $ref->created_at->format('h:i A') : '' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $ref->user->name ?? 'Peer #' . $ref->user_id }}</div>
                                    <small class="text-muted">Order: <code>#{{ $ref->order->order_number ?? $ref->order_id }}</code></small>
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-success">+{{ number_format($ref->coins_refunded ?? $ref->amount ?? 0) }}</span>
                                    <small class="text-muted">Coins</small>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-semibold">{{ number_format($ref->earned_coins_refunded ?? 0) }} Earned</span> &bull;
                                        <span class="text-info fw-semibold">{{ number_format($ref->bonus_coins_refunded ?? 0) }} Bonus</span>
                                    </div>
                                </td>
                                <td class="pe-4 text-end">
                                    <span class="text-muted small">{{ $ref->processedBy->name ?? 'Admin #' . $ref->processed_by ?: 'System' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-cash-coin fs-1 d-block mb-2 text-secondary"></i>
                                    No refund records found matching this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($refunds->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $refunds->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
