@extends('admin.layouts.app')

@section('title', 'Peers Store — Member Wishlists')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Member Wishlists</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-heart-fill text-danger"></i> Peer Wishlists &amp; Product Demand
            </h1>
            <p class="text-muted small mb-0 mt-1">Track what items members have saved in their wishlists to gauge interest and plan inventory.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.wishlists.export') }}" class="btn btn-outline-success d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export CSV
            </a>
            <a href="{{ route('admin.store.catalog.products') }}" class="btn btn-outline-primary d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-box-seam"></i> View Catalog
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

    {{-- 4 KPI Metric Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-danger letter-spacing-1 mb-1">Total Wishlist Items</div>
                        <div class="h3 mb-0 fw-bold text-dark">{{ number_format($totalItems) }}</div>
                        <div class="text-muted extra-small mt-1">Saved across all peers</div>
                    </div>
                    <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger fs-4">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-primary letter-spacing-1 mb-1">Active Peers</div>
                        <div class="h3 mb-0 fw-bold text-dark">{{ number_format($uniqueUsers) }}</div>
                        <div class="text-muted extra-small mt-1">Peers with saved items</div>
                    </div>
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary fs-4">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-success letter-spacing-1 mb-1">Unique Products</div>
                        <div class="h3 mb-0 fw-bold text-dark">{{ number_format($uniqueProducts) }}</div>
                        <div class="text-muted extra-small mt-1">Distinct items wishlisted</div>
                    </div>
                    <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success fs-4">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-warning-emphasis letter-spacing-1 mb-1">Most Desired Item</div>
                        <div class="h5 mb-0 fw-bold text-dark text-truncate" style="max-width: 170px;" title="{{ $topProductItem?->product?->name ?? 'None' }}">
                            {{ $topProductItem?->product?->name ?? 'None yet' }}
                        </div>
                        <div class="text-muted extra-small mt-1">
                            @if($topProductItem)
                                <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">{{ $topProductItem->count }} saves</span>
                            @else
                                No wishlist activity
                            @endif
                        </div>
                    </div>
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning fs-4">
                        <i class="bi bi-fire"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-pills mb-3 gap-1">
        @foreach($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active bg-primary' : 'bg-light text-dark' }} py-1.5 px-3 rounded-pill fw-medium" href="{{ route('admin.store.wishlists.index', array_merge(request()->query(), ['tab' => $key])) }}">
                    @if($key === 'by_peer')
                        <i class="bi bi-person-hearts me-1"></i>
                    @elseif($key === 'top_products')
                        <i class="bi bi-graph-up-arrow me-1"></i>
                    @else
                        <i class="bi bi-list-ul me-1"></i>
                    @endif
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Filters Card --}}
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.wishlists.index') }}" class="row g-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Peer Name, Email, Phone, Company, or Product..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" placeholder="From Date" title="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" placeholder="To Date" title="To Date">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.wishlists.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Content Based on Tab --}}
    @if($tab === 'by_peer')
        {{-- Grouped by Peer View --}}
        @if($groupedUsers && $groupedUsers->count() > 0)
            <div class="row g-3">
                @foreach($groupedUsers as $peer)
                    <div class="col-lg-6 col-xl-4">
                        <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
                            <div class="card-header bg-white py-3 px-3.5 d-flex justify-content-between align-items-center border-bottom">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; border: 2px solid #e0e7ff;">
                                        @if($peer->profile_photo_url)
                                            <img src="{{ $peer->profile_photo_url }}" alt="" class="w-100 h-100 object-fit-cover">
                                        @else
                                            <span class="fw-bold text-primary">{{ strtoupper(substr($peer->display_name ?: $peer->first_name ?: 'P', 0, 1)) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 170px;">
                                            {{ $peer->display_name ?: trim($peer->first_name.' '.$peer->last_name) ?: 'Peer #'.$peer->id }}
                                        </div>
                                        <div class="text-muted extra-small text-truncate" style="max-width: 170px;">
                                            {{ $peer->company_name ?: ($peer->email ?: $peer->phone) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-bold">
                                        <i class="bi bi-heart-fill me-1"></i> {{ $peer->wishlists_count }} item{{ $peer->wishlists_count > 1 ? 's' : '' }}
                                    </span>
                                    <div class="text-muted extra-small mt-0.5">
                                        <i class="bi bi-coin text-warning"></i> {{ number_format($peer->coins_balance ?? 0) }} Coins
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div class="small fw-semibold text-muted mb-2 text-uppercase letter-spacing-1 extra-small">Saved Items:</div>
                                <div class="list-group list-group-flush gap-2">
                                    @foreach($peer->wishlists->take(4) as $wItem)
                                        @php
                                            $prod = $wItem->product;
                                            $var = $wItem->variant;
                                            $price = $var ? ($var->coin_price ?: $var->price_coins) : ($prod ? ($prod->coin_price ?: $prod->price_coins) : 0);
                                        @endphp
                                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light bg-opacity-50 border">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded overflow-hidden bg-white border d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; flex-shrink: 0;">
                                                    @if($prod?->primaryImage)
                                                        <img src="{{ $prod->primaryImage->url ?? $prod->primaryImage->image_url }}" alt="" class="w-100 h-100 object-fit-cover">
                                                    @else
                                                        <i class="bi bi-box-seam text-muted"></i>
                                                    @endif
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div class="fw-semibold small text-dark text-truncate" style="max-width: 160px;" title="{{ $prod?->name }}">
                                                        {{ $prod?->name ?? 'Unknown Product' }}
                                                    </div>
                                                    @if($var)
                                                        <span class="badge bg-secondary-subtle text-secondary extra-small">{{ $var->name }}</span>
                                                    @elseif($prod?->category)
                                                        <span class="text-muted extra-small">{{ $prod->category->name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-end ps-2 flex-shrink-0">
                                                <span class="fw-bold text-primary small"><i class="bi bi-coin text-warning"></i> {{ number_format($price) }}</span>
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($peer->wishlists->count() > 4)
                                        <div class="text-center py-1">
                                            <span class="text-muted extra-small">+ {{ $peer->wishlists->count() - 4 }} more item(s)</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top p-2.5 d-flex justify-content-between align-items-center">
                                <span class="text-muted extra-small">
                                    <i class="bi bi-telephone"></i> {{ $peer->phone ?: 'No phone' }}
                                </span>
                                <a href="{{ route('admin.store.wishlists.peer', $peer->id) }}" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-semibold">
                                    View Wishlist <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4">
                {{ $groupedUsers->withQueryString()->links() }}
            </div>
        @else
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
                <i class="bi bi-heart text-muted display-4 mb-3"></i>
                <h5 class="fw-bold text-dark">No Member Wishlists Found</h5>
                <p class="text-muted small">No peers match the search criteria or have wishlisted items yet.</p>
            </div>
        @endif

    @elseif($tab === 'top_products')
        {{-- Top Products View --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Product Details</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Coin Price</th>
                            <th>Wishlist Demand</th>
                            <th>Inventory Stock</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProductsList as $prod)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; flex-shrink: 0;">
                                            @if($prod->primaryImage)
                                                <img src="{{ $prod->primaryImage->url ?? $prod->primaryImage->image_url }}" alt="" class="w-100 h-100 object-fit-cover">
                                            @else
                                                <i class="bi bi-box-seam fs-4 text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $prod->name }}</div>
                                            <span class="badge bg-{{ $prod->status === 'ACTIVE' ? 'success' : 'secondary' }}-subtle text-{{ $prod->status === 'ACTIVE' ? 'success' : 'secondary' }} extra-small">
                                                {{ $prod->status }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td><code class="text-muted">{{ $prod->sku }}</code></td>
                                <td>{{ $prod->category?->name ?? '—' }}</td>
                                <td>
                                    <span class="fw-bold text-primary">
                                        <i class="bi bi-coin text-warning"></i> {{ number_format($prod->coin_price ?: $prod->price_coins ?: 0) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger px-2.5 py-1.5 rounded-pill fs-6 fw-bold shadow-sm">
                                        <i class="bi bi-heart-fill me-1"></i> {{ $prod->wishlists_count }} saves
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $stock = (int) ($prod->stock_qty ?? 0);
                                    @endphp
                                    @if($stock <= 0)
                                        <span class="badge bg-danger-subtle text-danger fw-semibold">Out of Stock</span>
                                    @elseif($stock < 10)
                                        <span class="badge bg-warning-subtle text-warning-emphasis fw-semibold">{{ $stock }} in stock (Low)</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fw-semibold">{{ $stock }} in stock</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('admin.store.wishlists.index', ['tab' => 'all', 'product_id' => $prod->id]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        View Peers
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    No products currently in any peer's wishlist.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($topProductsList && $topProductsList->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $topProductsList->withQueryString()->links() }}
                </div>
            @endif
        </div>

    @else
        {{-- All Wishlist Items Table --}}
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Peer Details</th>
                            <th>Wishlisted Product</th>
                            <th>Variant / Specs</th>
                            <th>Coin Price</th>
                            <th>Peer Coins</th>
                            <th>Date Added</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $wishlist)
                            @php
                                $user = $wishlist->user;
                                $product = $wishlist->product;
                                $variant = $wishlist->variant;
                                $price = $variant ? ($variant->coin_price ?: $variant->price_coins) : ($product ? ($product->coin_price ?: $product->price_coins) : 0);
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="avatar rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0; border: 1px solid #e0e7ff;">
                                            @if($user?->profile_photo_url)
                                                <img src="{{ $user->profile_photo_url }}" alt="" class="w-100 h-100 object-fit-cover">
                                            @else
                                                <span class="fw-bold text-primary small">{{ strtoupper(substr($user?->display_name ?: $user?->first_name ?: 'P', 0, 1)) }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <a href="{{ $user ? route('admin.store.wishlists.peer', $user->id) : '#' }}" class="text-decoration-none text-dark hover-primary">
                                                    {{ $user?->display_name ?: trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: 'Unknown Peer' }}
                                                </a>
                                            </div>
                                            <div class="text-muted extra-small">{{ $user?->phone ?: $user?->email ?: '—' }}</div>
                                            @if($user?->company_name)
                                                <div class="text-muted extra-small"><i class="bi bi-building"></i> {{ $user->company_name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                            @if($product?->primaryImage)
                                                <img src="{{ $product->primaryImage->url ?? $product->primaryImage->image_url }}" alt="" class="w-100 h-100 object-fit-cover">
                                            @else
                                                <i class="bi bi-box-seam text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="{{ $product?->name }}">
                                                {{ $product?->name ?? 'Product Not Found' }}
                                            </div>
                                            <div class="text-muted extra-small">SKU: <code>{{ $product?->sku ?? '—' }}</code></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($variant)
                                        <span class="badge bg-secondary-subtle text-secondary fw-semibold">{{ $variant->name }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold text-primary">
                                        <i class="bi bi-coin text-warning"></i> {{ number_format($price) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">
                                        <i class="bi bi-coin"></i> {{ number_format($user?->coins_balance ?? 0) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-muted">{{ $wishlist->created_at?->format('d M Y, h:i A') ?? '—' }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-1.5">
                                        @if($user)
                                            <a href="{{ route('admin.store.wishlists.peer', $user->id) }}" class="btn btn-sm btn-outline-primary" title="View all items for this user">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.store.wishlists.destroy', $wishlist->id) }}" onsubmit="return confirm('Are you sure you want to remove this item from the peer wishlist?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-heartbreak fs-1 d-block mb-2 text-secondary"></i>
                                    No wishlist records match the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($items && $items->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $items->withQueryString()->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
