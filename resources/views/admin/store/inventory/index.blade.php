@extends('admin.layouts.app')

@section('title', 'Peers Store — Inventory Management')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Inventory Management</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-boxes text-primary"></i> Inventory Stock Levels
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.inventory.low-stock') }}" class="btn btn-warning position-relative d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> Low Stock Alerts
                @if($lowStockCount > 0)
                    <span class="badge bg-danger rounded-pill">{{ $lowStockCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.store.inventory.movements') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-clock-history"></i> Movement Audit Logs
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

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.inventory.index') }}" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Product Name, SKU, or Category..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.inventory.index') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Products & SKU Inventory Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 35%;">Product & Category</th>
                            <th style="width: 25%;">Variants / SKUs</th>
                            <th style="width: 15%;">Stock Summary</th>
                            <th style="width: 15%;">Status</th>
                            <th class="pe-4 text-end" style="width: 10%;">Quick Adjust</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $totalStock = $product->variants->sum('stock_quantity');
                                $hasLowStock = $product->variants->contains(function($v) {
                                    return $v->stock_quantity <= ($v->low_stock_threshold ?? 5);
                                });
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; overflow: hidden; flex-shrink: 0;">
                                            @if($product->primary_image_url)
                                                <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <i class="bi bi-box-seam text-muted fs-4"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.store.catalog.products.edit', $product->id) }}" class="fw-bold text-decoration-none text-dark d-block">
                                                {{ $product->name }}
                                            </a>
                                            <span class="badge bg-light text-dark border">{{ $product->category->name ?? 'Uncategorized' }}</span>
                                            <small class="text-muted ms-1">Slug: {{ $product->slug }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($product->variants->isNotEmpty())
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($product->variants as $variant)
                                                <div class="d-flex align-items-center justify-content-between p-1.5 px-2 rounded bg-light border small">
                                                    <div>
                                                        <span class="fw-semibold">{{ $variant->title ?: 'Default' }}</span>
                                                        <code class="text-muted ms-1">({{ $variant->sku }})</code>
                                                    </div>
                                                    <span class="badge {{ $variant->stock_quantity <= ($variant->low_stock_threshold ?? 5) ? 'bg-danger' : 'bg-success' }}">
                                                        {{ $variant->stock_quantity }} in stock
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted small italic">No variants configured</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold fs-6 {{ $totalStock == 0 ? 'text-danger' : 'text-dark' }}">
                                        {{ $totalStock }} units total
                                    </div>
                                    <small class="text-muted">{{ $product->variants->count() }} active SKU(s)</small>
                                </td>
                                <td>
                                    @if($totalStock == 0)
                                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>
                                    @elseif($hasLowStock)
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Low Stock</span>
                                    @else
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Healthy</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustModal{{ $product->id }}" title="Stock Adjustment">
                                        <i class="bi bi-sliders"></i> Adjust
                                    </button>
                                </td>
                            </tr>

                            {{-- Adjust Modal for this product --}}
                            <div class="modal fade" id="adjustModal{{ $product->id }}" tabindex="-1" aria-labelledby="adjustModalLabel{{ $product->id }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.store.inventory.adjust') }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="adjustModalLabel{{ $product->id }}">
                                                    <i class="bi bi-sliders text-primary"></i> Adjust Stock — {{ $product->name }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Select SKU Variant <span class="text-danger">*</span></label>
                                                    <select name="variant_id" class="form-select" required>
                                                        @foreach($product->variants as $var)
                                                            <option value="{{ $var->id }}">
                                                                {{ $var->title ?: 'Default' }} (SKU: {{ $var->sku }}) — Current: {{ $var->stock_quantity }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold">Action Type <span class="text-danger">*</span></label>
                                                        <select name="adjustment_type" class="form-select" required>
                                                            <option value="add">Add (+)</option>
                                                            <option value="subtract">Subtract (-)</option>
                                                            <option value="set">Set Exact Count (=)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                                                        <input type="number" name="quantity" class="form-control" min="0" required placeholder="e.g. 25">
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Audit Reason / Note <span class="text-danger">*</span></label>
                                                    <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Warehouse batch intake, damaged stock write-off..." required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Commit Adjustment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-boxes fs-1 d-block mb-2 text-secondary"></i>
                                    No products matching your search filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($products->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
