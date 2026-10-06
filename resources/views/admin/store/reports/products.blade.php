@extends('admin.layouts.app')

@section('title', 'Peers Store — Product Performance Report')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Product Performance</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-line-fill text-primary"></i> Product Sales & Velocity Performance
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.catalog.products') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-box-seam"></i> Products Catalog
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.reports.products') }}" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search by Product Name..." value="{{ $search }}">
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-select">
                        <option value="sales_desc" {{ $sort === 'sales_desc' ? 'selected' : '' }}>Highest Sales Volume</option>
                        <option value="sales_asc" {{ $sort === 'sales_asc' ? 'selected' : '' }}>Lowest Sales Volume</option>
                        <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>Highest Coin Price</option>
                        <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Lowest Coin Price</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Sort & Filter</button>
                    <a href="{{ route('admin.store.reports.products') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Products Performance Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 35%;">Product</th>
                            <th style="width: 15%;">Category</th>
                            <th style="width: 15%;">Coin Price</th>
                            <th style="width: 15%;">Units Sold</th>
                            <th class="pe-4 text-end" style="width: 20%;">Total Coins Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; overflow: hidden; flex-shrink: 0;">
                                            @if($product->primary_image_url)
                                                <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                <i class="bi bi-box-seam text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.store.catalog.products.edit', $product->id) }}" class="fw-bold text-decoration-none text-dark">
                                                {{ $product->name }}
                                            </a>
                                            <div class="text-muted small">ID: #{{ $product->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $product->category->name ?? 'Uncategorized' }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">{{ number_format($product->coin_price) }} Coins</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary rounded-pill fs-6">{{ number_format($product->total_units_sold ?? $product->order_items_count ?? 0) }} units</span>
                                </td>
                                <td class="pe-4 text-end">
                                    <span class="fw-bold fs-6 text-primary">{{ number_format(($product->total_units_sold ?? 0) * $product->coin_price) }} Coins</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary"></i>
                                    No products found matching this filter.
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
