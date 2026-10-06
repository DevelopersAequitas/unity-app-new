@extends('admin.layouts.app')

@section('title', 'Peers Store — Inventory Movement Audit Logs')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.inventory.index') }}" class="text-decoration-none text-muted">Inventory</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Movement History</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i> Inventory Movement Audit Trail
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.inventory.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.inventory.movements') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Search Keyword</label>
                    <input type="text" name="search" class="form-control" placeholder="SKU or product name..." value="{{ $search }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted">Movement Type</label>
                    <select name="reference_type" class="form-select">
                        <option value="">All Movement Types</option>
                        @foreach($referenceTypes as $type)
                            <option value="{{ $type }}" {{ $refType === $type ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.inventory.movements') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Movements Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Timestamp</th>
                            <th style="width: 25%;">Product / SKU</th>
                            <th style="width: 15%;">Movement Type</th>
                            <th style="width: 12%;">Qty Change</th>
                            <th style="width: 13%;">Balance After</th>
                            <th class="pe-4" style="width: 20%;">Reason / Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ $mov->created_at ? $mov->created_at->format('d M Y') : '—' }}</div>
                                    <small class="text-muted">{{ $mov->created_at ? $mov->created_at->format('h:i A') : '' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $mov->variant->product->name ?? 'Unknown Product' }}</div>
                                    <small class="text-muted">SKU: <code>{{ $mov->variant->sku ?? '—' }}</code></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst(str_replace('_', ' ', $mov->reference_type ?? 'Adjustment')) }}
                                    </span>
                                </td>
                                <td>
                                    @if($mov->quantity_change > 0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-6">
                                            +{{ $mov->quantity_change }}
                                        </span>
                                    @elseif($mov->quantity_change < 0)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold fs-6">
                                            {{ $mov->quantity_change }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary fw-bold fs-6">0</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $mov->stock_after ?? '—' }} units</span>
                                </td>
                                <td class="pe-4">
                                    <div class="text-dark small">{{ $mov->reason ?: 'No notes provided' }}</div>
                                    @if($mov->reference_id)
                                        <small class="text-muted">Ref ID: #{{ $mov->reference_id }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-clock-history fs-1 d-block mb-2 text-secondary"></i>
                                    No inventory movements found for this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($movements->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
