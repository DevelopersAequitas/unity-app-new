@extends('admin.layouts.app')

@section('title', 'Peers Store — Return Request #RET-' . $return->id)

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.returns.index') }}" class="text-decoration-none text-muted">Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#RET-{{ $return->id }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-arrow-return-left text-primary"></i> Return Request #RET-{{ $return->id }}
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.returns.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Queue
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

    <div class="row g-4">
        {{-- Left: Return Details & Order Items --}}
        <div class="col-lg-8">
            {{-- Reason & Inspection Info --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0">Return Request Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <span class="text-muted small">Return Reason:</span>
                            <div class="fw-bold text-dark fs-6">{{ ucfirst(str_replace('_', ' ', $return->reason)) }}</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small">Requested Date:</span>
                            <div class="fw-semibold text-dark">{{ $return->created_at->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small">Customer Explanation / Notes:</span>
                        <div class="p-3 bg-light rounded border text-dark mt-1">
                            {{ $return->notes ?: 'No additional notes provided by customer.' }}
                        </div>
                    </div>

                    {{-- Attached Defect / Return Photos --}}
                    @if(!empty($return->images) && is_array($return->images))
                        <div class="border-top pt-3 mt-3">
                            <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-camera text-primary me-1"></i> Defect / Inspection Images</h6>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($return->images as $imgUrl)
                                    <a href="{{ $imgUrl }}" target="_blank" class="rounded border d-inline-block overflow-hidden" style="width: 100px; height: 100px;">
                                        <img src="{{ $imgUrl }}" alt="Return proof" style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Original Order Items --}}
            @if($return->order)
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title fw-bold text-dark mb-0">Original Order #{{ $return->order->order_number ?: $return->order_id }}</h5>
                        <a href="{{ route('admin.store.orders.show', $return->order->id) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i> View Order
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Product / SKU</th>
                                        <th>Unit Coins</th>
                                        <th>Qty</th>
                                        <th class="pe-4 text-end">Total Coins</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($return->order->items as $item)
                                        <tr>
                                            <td class="ps-4">
                                                <strong>{{ $item->product_name }}</strong>
                                                <div class="small text-muted">SKU: <code>{{ $item->sku }}</code></div>
                                            </td>
                                            <td>{{ number_format($item->unit_coins) }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td class="pe-4 text-end fw-bold text-primary">{{ number_format($item->total_coins) }} Coins</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right: Actions & Decision --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0">Return Status & Decision</h5>
                </div>
                <div class="card-body">
                    <div class="text-center p-3 bg-light rounded mb-3">
                        <span class="text-muted small d-block mb-1">Status</span>
                        @if($return->status === 'pending')
                            <span class="badge bg-warning text-dark fs-6 px-3 py-1.5">Pending Review</span>
                        @elseif($return->status === 'approved' || $return->status === 'completed')
                            <span class="badge bg-success text-white fs-6 px-3 py-1.5">Approved & Refunded</span>
                        @elseif($return->status === 'rejected')
                            <span class="badge bg-danger text-white fs-6 px-3 py-1.5">Rejected</span>
                        @else
                            <span class="badge bg-secondary fs-6 px-3 py-1.5">{{ ucfirst($return->status) }}</span>
                        @endif
                    </div>

                    @if($return->status === 'pending')
                        {{-- Approve & Refund Form --}}
                        <form method="POST" action="{{ route('admin.store.returns.approve', $return->id) }}" class="mb-3" onsubmit="return confirm('Approve this return and credit coins back to user wallet?')">
                            @csrf
                            <div class="p-3 border rounded bg-success bg-opacity-10 mb-3">
                                <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Approve & Refund</h6>
                                <p class="text-muted small mb-2">
                                    Total order coins: <strong>{{ number_format($return->order->total_coins ?? 0) }}</strong>.<br>
                                    Exact Earned/Bonus proportion will be restored.
                                </p>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Refund Amount (Coins)</label>
                                    <input type="number" name="refund_coins" class="form-control form-control-sm" value="{{ $return->order->total_coins ?? 0 }}" required min="1">
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="restock_inventory" value="1" id="restockCheck" checked>
                                    <label class="form-check-label small" for="restockCheck">Restock returned items into inventory</label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success w-100">
                                    <i class="bi bi-check2"></i> Approve & Issue Refund
                                </button>
                            </div>
                        </form>

                        {{-- Reject Form --}}
                        <form method="POST" action="{{ route('admin.store.returns.reject', $return->id) }}">
                            @csrf
                            <div class="p-3 border rounded bg-danger bg-opacity-10">
                                <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle-fill me-1"></i> Reject Return Request</h6>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" placeholder="Policy violation, missing items, damaged by user..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-x-lg"></i> Reject Request
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Customer Card --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-person text-primary me-1"></i> Peer Customer</h5>
                </div>
                <div class="card-body">
                    <div class="fw-bold text-dark">{{ $return->user->name ?? 'Peer #' . $return->user_id }}</div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-telephone me-1"></i> {{ $return->user->phone_number ?? $return->user->mobile ?: '—' }}<br>
                        <i class="bi bi-envelope me-1"></i> {{ $return->user->email ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
