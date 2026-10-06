@extends('admin.layouts.app')

@section('title', 'Product Catalog — Peers Store')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-boxes me-2 text-primary"></i>Merchandise Product Catalog
            </h1>
            <p class="text-muted small mb-0">Manage store merchandise items, SKU variants, stock limits, and coin prices.</p>
        </div>
        <a href="{{ route('admin.store.products.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Add New Product
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.store.products.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name, SKU, or keyword..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="PHYSICAL" {{ request('type') === 'PHYSICAL' ? 'selected' : '' }}>PHYSICAL</option>
                        <option value="DIGITAL" {{ request('type') === 'DIGITAL' ? 'selected' : '' }}>DIGITAL</option>
                        <option value="COURSE" {{ request('type') === 'COURSE' ? 'selected' : '' }}>COURSE</option>
                        <option value="MEMBERSHIP" {{ request('type') === 'MEMBERSHIP' ? 'selected' : '' }}>MEMBERSHIP</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                        <option value="OUT_OF_STOCK" {{ request('status') === 'OUT_OF_STOCK' ? 'selected' : '' }}>OUT_OF_STOCK</option>
                        <option value="ARCHIVED" {{ request('status') === 'ARCHIVED' ? 'selected' : '' }}>ARCHIVED</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Product Name / SKU</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Price in Coins</th>
                            <th>Stock Units</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        <tr>
                            <td>
                                @php
                                    $img = $product->primaryImage->image_url ?? ($product->images->first()->image_url ?? null);
                                @endphp
                                @if($img)
                                    <img src="{{ $img }}" alt="{{ $product->name }}" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;">
                                @else
                                    <div class="bg-light rounded border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="bi bi-image text-muted"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $product->name }}</div>
                                <div class="text-muted extra-small">SKU: <code>{{ $product->sku }}</code></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $product->category->name ?? 'Uncategorized' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $product->type === 'PHYSICAL' ? 'bg-primary' : 'bg-info text-dark' }}">
                                    {{ $product->type }}
                                </span>
                            </td>
                            <td class="fw-bold text-success">{{ number_format($product->price_coins) }} Coins</td>
                            <td>
                                @php
                                    $variantStockSum = $product->variants->sum('stock_quantity');
                                    $stockDisplay = $variantStockSum > 0 ? $variantStockSum : $product->stock_qty;
                                @endphp
                                @if($stockDisplay <= 0)
                                    <span class="badge bg-danger">0 Units (OOS)</span>
                                @elseif($stockDisplay <= 10)
                                    <span class="badge bg-warning text-dark">{{ $stockDisplay }} Units (Low)</span>
                                @else
                                    <span class="badge bg-success">{{ $stockDisplay }} Units</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badge = match($product->status) {
                                        'ACTIVE' => 'bg-success',
                                        'DRAFT' => 'bg-warning text-dark',
                                        'OUT_OF_STOCK' => 'bg-danger',
                                        'ARCHIVED' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                @endphp
                                <span class="badge {{ $badge }}">{{ $product->status }}</span>
                            </td>
                            <td class="small text-muted">{{ $product->created_at ? $product->created_at->format('d M Y') : 'N/A' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.store.products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                    Edit
                                </a>
                                @if($product->status !== 'ARCHIVED')
                                <form action="{{ route('admin.store.products.archive', $product->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Archive this product? It will be hidden from customer store.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                        Archive
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-muted opacity-50"></i>
                                No products found matching criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
            <div class="p-3 border-top">
                {{ $products->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
