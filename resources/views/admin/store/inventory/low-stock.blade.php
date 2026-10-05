@extends('admin.layouts.app')

@section('title', 'Peers Store — Low Stock Alerts')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.inventory.index') }}" class="text-decoration-none text-muted">Inventory</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Low Stock Alerts</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i> Low Stock & Depleted Inventory
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.inventory.export', ['threshold' => $threshold]) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Low Stock CSV
            </a>
            <a href="{{ route('admin.store.inventory.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> All Inventory
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

    {{-- Filter by Threshold --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.inventory.low-stock') }}" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold text-muted">Filter by Stock Threshold:</label>
                </div>
                <div class="col-auto">
                    <select name="threshold" class="form-select" onchange="this.form.submit()">
                        @foreach($thresholds as $val)
                            <option value="{{ $val }}" {{ (int)$threshold === (int)$val ? 'selected' : '' }}>
                                Stock &le; {{ $val }} units
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto text-muted small">
                    Showing SKUs with stock level at or below the selected cutoff.
                </div>
            </form>
        </div>
    </div>

    {{-- Low Stock SKU Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 30%;">Product</th>
                            <th style="width: 20%;">SKU / Variant</th>
                            <th style="width: 15%;">Remaining Stock</th>
                            <th style="width: 15%;">Configured Threshold</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4 text-end" style="width: 10%;">Restock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($variants as $variant)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; overflow: hidden; flex-shrink: 0;">
                                            @if($variant->product->primary_image_url)
                                                <img src="{{ $variant->product->primary_image_url }}" alt="{{ $variant->product->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <i class="bi bi-box-seam text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.store.catalog.products.edit', $variant->product->id) }}" class="fw-bold text-decoration-none text-dark">
                                                {{ $variant->product->name }}
                                            </a>
                                            <div class="text-muted small">{{ $variant->product->category->name ?? 'Uncategorized' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $variant->title ?: 'Default' }}</span>
                                    <div class="small text-muted">SKU: <code>{{ $variant->sku }}</code></div>
                                </td>
                                <td>
                                    <span class="badge {{ $variant->stock_quantity == 0 ? 'bg-danger' : 'bg-warning text-dark' }} fs-6">
                                        {{ $variant->stock_quantity }} units
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fw-medium">&le; {{ $variant->low_stock_threshold ?? 5 }} units</span>
                                </td>
                                <td>
                                    @if($variant->stock_quantity == 0)
                                        <span class="badge bg-danger"><i class="bi bi-slash-circle"></i> Out of Stock</span>
                                    @else
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Critical</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#restockModal{{ $variant->id }}">
                                        <i class="bi bi-plus-circle"></i> Restock
                                    </button>
                                </td>
                            </tr>

                            {{-- Restock Modal --}}
                            <div class="modal fade" id="restockModal{{ $variant->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.store.inventory.adjust') }}">
                                            @csrf
                                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                            <input type="hidden" name="adjustment_type" value="add">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-plus-circle text-primary"></i> Restock SKU: {{ $variant->sku }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted small mb-3">
                                                    Product: <strong>{{ $variant->product->name }}</strong> ({{ $variant->title ?: 'Default' }})<br>
                                                    Current Stock: <strong class="text-danger">{{ $variant->stock_quantity }}</strong> units
                                                </p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Units to Add <span class="text-danger">*</span></label>
                                                    <input type="number" name="quantity" class="form-control" min="1" required placeholder="e.g. 50">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Restock Reason <span class="text-danger">*</span></label>
                                                    <textarea name="reason" class="form-control" rows="2" placeholder="Purchase Order receipt, supplier batch refill..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Add to Inventory</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-check2-circle text-success fs-1 d-block mb-2"></i>
                                    Great! All items have healthy stock levels above {{ $threshold }} units.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($variants->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $variants->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
