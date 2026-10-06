@extends('admin.layouts.app')

@section('title', 'Peers Store — Orders Management')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Orders Management</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-cart-check-fill text-primary"></i> Store Orders & Fulfilment
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.reports.sales') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-bar-graph"></i> Sales Report
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
                <a class="nav-link {{ $tab === $key ? 'active bg-primary' : 'bg-light text-dark' }} py-1.5 px-3 rounded-pill fw-medium" href="{{ route('admin.store.orders.index', array_merge(request()->query(), ['tab' => $key])) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Filters Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.orders.index') }}" class="row g-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Order #, Customer Name, Phone, or AWB..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" placeholder="From Date" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" placeholder="To Date" value="{{ $dateTo }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.orders.index', ['tab' => $tab]) }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Order ID & Date</th>
                            <th style="width: 20%;">Customer / Peer</th>
                            <th style="width: 15%;">Fulfilment Type</th>
                            <th style="width: 15%;">Coins Paid</th>
                            <th style="width: 15%;">Status</th>
                            <th class="pe-4 text-end" style="width: 20%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php
                                $statusColors = [
                                    'pending' => 'bg-warning text-dark',
                                    'processing' => 'bg-info text-white',
                                    'shipped' => 'bg-primary text-white',
                                    'ready_for_pickup' => 'bg-info text-white',
                                    'delivered' => 'bg-success text-white',
                                    'cancelled' => 'bg-danger text-white',
                                    'refunded' => 'bg-secondary text-white',
                                ];
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('admin.store.orders.show', $order->id) }}" class="fw-bold text-decoration-none text-primary fs-6">
                                        #{{ $order->order_number ?: $order->id }}
                                    </a>
                                    <div class="text-muted small">{{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—' }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $order->user->name ?? 'Guest/Peer #' . $order->user_id }}</div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $order->shipping_phone ?: ($order->user->phone_number ?? '—') }}</small>
                                </td>
                                <td>
                                    @if($order->delivery_method === 'pickup')
                                        <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-building me-1"></i> Central Hub Pickup</span>
                                    @else
                                        <span class="badge bg-light text-dark border"><i class="bi bi-truck me-1"></i> Doorstep Courier</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-primary">{{ number_format($order->total_coins ?? 0) }}</span>
                                    <small class="text-muted">Coins</small>
                                </td>
                                <td>
                                    <span class="badge {{ $statusColors[$order->status] ?? 'bg-light text-dark' }} px-2 py-1">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.store.orders.show', $order->id) }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View Order
                                        </a>
                                        <a href="{{ route('admin.store.orders.packing-slip', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Packing Slip">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                                    No orders found for the selected tab and filter criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($orders->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
