@extends('admin.layouts.app')

@section('title', 'Peers Store — ' . ($user->display_name ?: $user->first_name) . "'s Wishlist")

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wishlists.index') }}" class="text-decoration-none text-muted">Member Wishlists</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->display_name ?: $user->first_name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-heart-fill text-danger"></i> {{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}'s Wishlist
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.wishlists.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back to Wishlists
            </a>
        </div>
    </div>

    {{-- User Info Header Card --}}
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white" style="border: 1px solid #e2e8f0 !important;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border: 3px solid #e0e7ff;">
                        @if($user->profile_photo_url)
                            <img src="{{ $user->profile_photo_url }}" alt="" class="w-100 h-100 object-fit-cover">
                        @else
                            <span class="fw-bold text-primary fs-4">{{ strtoupper(substr($user->display_name ?: $user->first_name ?: 'P', 0, 1)) }}</span>
                        @endif
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-1">{{ $user->display_name ?: trim($user->first_name.' '.$user->last_name) }}</h4>
                        <div class="text-muted small d-flex flex-wrap gap-3">
                            <span><i class="bi bi-envelope me-1"></i> {{ $user->email ?: 'N/A' }}</span>
                            <span><i class="bi bi-telephone me-1"></i> {{ $user->phone ?: 'N/A' }}</span>
                            @if($user->company_name)
                                <span><i class="bi bi-building me-1"></i> {{ $user->company_name }}</span>
                            @endif
                            @if($user->city)
                                <span><i class="bi bi-geo-alt me-1"></i> {{ $user->city }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-3 text-end">
                    <div class="px-3 py-2 bg-light rounded-3 border text-center">
                        <div class="text-muted extra-small fw-semibold text-uppercase">Coins Balance</div>
                        <div class="h5 mb-0 fw-bold text-primary"><i class="bi bi-coin text-warning"></i> {{ number_format($user->coins_balance ?? 0) }}</div>
                    </div>
                    <div class="px-3 py-2 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25 text-center">
                        <div class="text-danger extra-small fw-semibold text-uppercase">Wishlisted Items</div>
                        <div class="h5 mb-0 fw-bold text-danger">{{ $wishlist['total_items'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Wishlist Items Grid --}}
    <div class="row g-3">
        @forelse($wishlist['items'] as $item)
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 rounded-4 h-100 bg-white overflow-hidden" style="border: 1px solid #e2e8f0 !important;">
                    <div class="position-relative bg-light text-center p-3" style="min-height: 180px; display: flex; align-items: center; justify-content: center;">
                        @if($item['product']['image_url'])
                            <img src="{{ $item['product']['image_url'] }}" alt="{{ $item['product']['name'] }}" class="img-fluid rounded" style="max-height: 160px; object-fit: contain;">
                        @else
                            <i class="bi bi-box-seam display-4 text-muted"></i>
                        @endif
                        <span class="position-absolute top-0 start-0 m-3 badge bg-{{ $item['in_stock'] ? 'success' : 'danger' }} rounded-pill px-2.5 py-1">
                            {{ $item['in_stock'] ? 'In Stock ('.$item['stock_qty'].')' : 'Out of Stock' }}
                        </span>
                    </div>
                    <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                        <div>
                            @if($item['product']['category'])
                                <span class="badge bg-light text-muted border extra-small mb-2">{{ $item['product']['category']['name'] }}</span>
                            @endif
                            <h5 class="fw-bold text-dark mb-1">{{ $item['product']['name'] }}</h5>
                            <div class="text-muted extra-small mb-2">SKU: <code>{{ $item['product']['sku'] }}</code></div>

                            @if($item['variant'])
                                <div class="p-2 rounded bg-light border mb-2 extra-small">
                                    <strong>Variant:</strong> {{ $item['variant']['name'] }} (<code>{{ $item['variant']['sku'] }}</code>)
                                </div>
                            @endif
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <div class="text-muted extra-small">Coin Price</div>
                                <div class="h5 mb-0 fw-bold text-primary"><i class="bi bi-coin text-warning"></i> {{ number_format($item['coin_price']) }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted extra-small">Saved On</div>
                                <div class="small fw-semibold text-muted">{{ \Carbon\Carbon::parse($item['added_at'])->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top p-2.5 d-flex justify-content-between align-items-center">
                        <form method="POST" action="{{ route('admin.store.wishlists.destroy', $item['id']) }}" onsubmit="return confirm('Remove this item from peer wishlist?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2.5 rounded-pill">
                                <i class="bi bi-trash me-1"></i> Remove
                            </button>
                        </form>
                        <a href="{{ route('admin.store.catalog.products.edit', $item['product']['id']) }}" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill">
                            Product Details <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-heartbreak text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold text-dark">This peer's wishlist is empty</h5>
                    <p class="text-muted small">The peer has not added any store products to their wishlist yet.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
