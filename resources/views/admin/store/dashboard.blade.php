@extends('admin.layouts.app')

@section('title', 'Peers Store — Operations Dashboard')

@section('content')
<style>
    /* Keyframe Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(18px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulseGlow {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4);
        }
        50% {
            transform: scale(1.02);
            box-shadow: 0 0 0 8px rgba(79, 70, 229, 0);
        }
    }

    @keyframes livePulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    @keyframes shimmerBar {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    /* Staggered Animation Classes */
    .anim-fade-1 { animation: fadeInUp 0.45s ease forwards; }
    .anim-fade-2 { animation: fadeInUp 0.55s ease forwards; }
    .anim-fade-3 { animation: fadeInUp 0.65s ease forwards; }
    .anim-fade-4 { animation: fadeInUp 0.75s ease forwards; }

    /* Store KPI Cards with Premium Glass & Gradients */
    .store-kpi-card {
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.85);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none !important;
        color: inherit !important;
        display: block;
        height: 100%;
        overflow: hidden;
        position: relative;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    }
    .store-kpi-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 36px -10px rgba(79, 70, 229, 0.14) !important;
        border-color: rgba(99, 102, 241, 0.4) !important;
    }
    .store-kpi-card .card-top-stripe {
        height: 4px;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
        background-size: 200% auto;
        animation: shimmerBar 3s linear infinite;
    }

    .kpi-icon-container {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.55rem;
        transition: transform 0.3s ease;
    }
    .store-kpi-card:hover .kpi-icon-container {
        transform: scale(1.1) rotate(4deg);
    }

    /* Color Gradient Utilities */
    .bg-gradient-blue { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #ffffff; }
    .bg-gradient-emerald { background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: #ffffff; }
    .bg-gradient-cyan { background: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%); color: #ffffff; }
    .bg-gradient-amber { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); color: #ffffff; }

    /* Quick Links */
    .store-hub-btn {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none !important;
        color: inherit !important;
        display: flex;
        align-items: center;
        gap: 14px;
        height: 100%;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }
    .store-hub-btn:hover {
        transform: translateY(-4px);
        background: #fafcff;
        border-color: #6366f1;
        box-shadow: 0 12px 24px -8px rgba(79, 70, 229, 0.12);
    }
    .store-hub-btn:hover .hub-icon-wrapper {
        transform: scale(1.08);
    }
    .hub-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        transition: transform 0.25s ease;
        flex-shrink: 0;
    }

    /* Pulse Dot Indicator */
    .pulse-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background-color: #10b981;
        display: inline-block;
        animation: livePulse 2s infinite;
    }

    /* Table & Container Cards */
    .dashboard-panel-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid rgba(0, 0, 0, 0.07);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }
    .table-hover-custom tbody tr {
        transition: background-color 0.18s ease;
    }
    .table-hover-custom tbody tr:hover {
        background-color: #f8faff !important;
    }
</style>

<div class="container-fluid px-3 py-3">
    <!-- Header Title Bar (Animated) -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 anim-fade-1">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <div class="p-2.5 bg-primary bg-opacity-10 text-primary rounded-4 shadow-sm">
                    <i class="bi bi-shop fs-3"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="h3 mb-0 text-dark fw-bold">Peers Store Operations Dashboard</h1>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 extra-small fw-semibold d-flex align-items-center gap-1.5">
                            <span class="pulse-dot"></span> Live Store
                        </span>
                    </div>
                    <p class="text-muted small mb-0 mt-0.5">Real-time catalog distribution, coin redemptions, fulfillment orders, and logistics.</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.store.orders.index') }}" class="btn btn-outline-secondary btn-sm px-3.5 py-2 rounded-3 fw-semibold shadow-sm">
                <i class="bi bi-box-seam me-1"></i> Orders &amp; Shipping
            </a>
            <a href="{{ route('admin.store.catalog.products.create') }}" class="btn btn-primary btn-sm px-3.5 py-2 rounded-3 shadow fw-semibold d-flex align-items-center gap-1.5">
                <i class="bi bi-plus-circle-fill"></i> Add Product
            </a>
        </div>
    </div>

    <!-- Actionable Alert Center (Animated) -->
    @if(count($alerts) > 0)
    <div class="row mb-4 anim-fade-2">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(135deg, #ffffff 0%, #f9faff 100%); border: 1px solid #e0e7ff !important;">
                <div class="card-body p-3.5 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger text-white px-2.5 py-1.5 rounded-pill fw-bold small shadow-sm">
                                <i class="bi bi-bell-fill me-1"></i> Action Required
                            </span>
                            <span class="fw-bold text-dark">Operational Alerts &amp; Queue</span>
                        </div>
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 extra-small fw-bold">
                            {{ count($alerts) }} alert{{ count($alerts) > 1 ? 's' : '' }} need attention
                        </span>
                    </div>
                    <div class="d-flex flex-column gap-2.5">
                        @foreach($alerts as $alert)
                        <div class="alert alert-{{ $alert['type'] }} mb-0 py-3 px-3.5 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border shadow-none" style="transition: transform 0.2s;">
                            <div class="d-flex align-items-center">
                                <i class="bi {{ $alert['icon'] }} me-3 fs-5"></i>
                                <span class="fw-semibold small text-dark">{{ $alert['message'] }}</span>
                            </div>
                            <a href="{{ $alert['link'] }}" class="btn btn-sm btn-{{ $alert['type'] }} py-1 px-3.5 rounded-pill fw-bold shadow-sm">
                                {{ $alert['link_text'] }} <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 4 High-Impact KPI Cards (Animated with Shimmer & 3D Lift) -->
    <div class="row g-3 mb-4 anim-fade-3">
        <!-- Card 1: Orders Today -->
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('admin.store.orders.index') }}" class="store-kpi-card p-4" title="Click to view all Orders & Shipping">
                <div class="card-top-stripe bg-gradient-blue"></div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-primary letter-spacing-1 mb-1">Orders Placed Today</div>
                        <div class="h2 mb-0 fw-bold text-dark">{{ number_format($ordersTodayCount) }}</div>
                    </div>
                    <div class="kpi-icon-container bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-cart-check-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                    <span class="text-muted small">
                        <strong class="text-primary">{{ number_format($ordersTodayCoins) }}</strong> Coins Spent
                    </span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 extra-small fw-semibold">Orders &rarr;</span>
                </div>
            </a>
        </div>

        <!-- Card 2: Coins Redeemed Today -->
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('admin.store.reports.coin-economy') }}" class="store-kpi-card p-4" title="Click to view Coin Economy Analytics">
                <div class="card-top-stripe bg-gradient-emerald"></div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-success letter-spacing-1 mb-1">Coins Redeemed (Today)</div>
                        <div class="h2 mb-0 fw-bold text-dark">{{ number_format($coinsRedeemedToday) }}</div>
                    </div>
                    <div class="kpi-icon-container bg-success bg-opacity-10 text-success">
                        <i class="bi bi-fire"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                    <span class="text-muted small">
                        Month: <strong class="text-success">{{ number_format($coinsRedeemedMonth) }}</strong>
                    </span>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 extra-small fw-semibold">Economy &rarr;</span>
                </div>
            </a>
        </div>

        <!-- Card 3: Circulating Coin Pool -->
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('admin.store.wallet.index') }}" class="store-kpi-card p-4" title="Click to view Member Coin Wallets">
                <div class="card-top-stripe bg-gradient-cyan"></div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-info letter-spacing-1 mb-1">Circulating Coin Pool</div>
                        <div class="h3 mb-0 fw-bold text-dark" style="font-size: 1.6rem;">{{ number_format($coinsInCirculation) }}</div>
                    </div>
                    <div class="kpi-icon-container bg-info bg-opacity-10 text-info">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                    <span class="text-muted small">Peer Holdings</span>
                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 extra-small fw-semibold">Wallets &rarr;</span>
                </div>
            </a>
        </div>

        <!-- Card 4: Active Catalog SKUs -->
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('admin.store.catalog.products') }}" class="store-kpi-card p-4" title="Click to view Product Catalog">
                <div class="card-top-stripe bg-gradient-amber"></div>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-xs fw-bold text-uppercase text-warning-emphasis letter-spacing-1 mb-1">Active Catalog SKUs</div>
                        <div class="h2 mb-0 fw-bold text-dark">{{ number_format($totalActiveProducts) }}</div>
                    </div>
                    <div class="kpi-icon-container bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                    <span class="small">
                        <span class="text-danger fw-bold">{{ $lowStockCount }} Low</span> &bull; <span class="text-muted">{{ $outOfStockCount }} OOS</span>
                    </span>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-0.5 extra-small fw-semibold">Catalog &rarr;</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Quick Hub Links (Animated - 5 in Single Line) -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4 anim-fade-4">
        <div class="col">
            <a href="{{ route('admin.store.catalog.products') }}" class="store-hub-btn">
                <div class="hub-icon-wrapper bg-gradient-blue shadow-sm">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate">Merchandise</div>
                    <div class="text-muted extra-small text-truncate">Manage SKUs &amp; pricing</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('admin.store.inventory.index') }}" class="store-hub-btn">
                <div class="hub-icon-wrapper bg-gradient-emerald shadow-sm">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate">Inventory &amp; Stock</div>
                    <div class="text-muted extra-small text-truncate">Audit stock movements</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('admin.store.wishlists.index') }}" class="store-hub-btn">
                <div class="hub-icon-wrapper shadow-sm" style="background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%); color: #ffffff;">
                    <i class="bi bi-heart-fill"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate">Member Wishlists</div>
                    <div class="text-muted extra-small text-truncate">Peer demand &amp; saves</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('admin.store.wallet.index') }}" class="store-hub-btn">
                <div class="hub-icon-wrapper bg-gradient-cyan shadow-sm">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate">Coin Wallets</div>
                    <div class="text-muted extra-small text-truncate">Balances &amp; ledger</div>
                </div>
            </a>
        </div>
        <div class="col">
            <a href="{{ route('admin.store.wallet.adjustments') }}" class="store-hub-btn">
                <div class="hub-icon-wrapper bg-gradient-amber shadow-sm">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold text-dark text-truncate">Coin Approvals</div>
                    <div class="text-muted extra-small text-truncate">{{ $pendingAdjustmentsCount }} Pending Approvals</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Recent Orders & Top Products (Animated) -->
    <div class="row g-4 anim-fade-4">
        {{-- Recent Orders Table --}}
        <div class="col-lg-8">
            <div class="dashboard-panel-card h-100">
                <div class="card-header bg-white py-3.5 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-receipt fs-5"></i>
                        </div>
                        <div>
                            <h6 class="m-0 fw-bold text-dark">Recent Fulfillment Orders</h6>
                            <small class="text-muted extra-small">Live stream of incoming member redemptions</small>
                        </div>
                    </div>
                    <a href="{{ route('admin.store.orders.index') }}" class="btn btn-sm btn-outline-primary rounded-3 px-3 fw-semibold">
                        View All Orders &rarr;
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover-custom align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Order #</th>
                                    <th>Peer Member</th>
                                    <th>Coins Burned</th>
                                    <th>Delivery</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                @php
                                    $peerName = trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: ($order->user->display_name ?: ($order->user->name ?? 'Peer Member'));
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('admin.store.orders.show', $order->id) }}" class="fw-bold text-primary text-decoration-none">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary border border-primary-subtle d-flex align-items-center justify-content-center fw-bold small" style="width: 32px; height: 32px;">
                                                {{ strtoupper(substr($peerName, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">{{ $peerName }}</div>
                                                <div class="text-muted extra-small">{{ $order->user->email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">{{ number_format($order->total_coins) }}</span>
                                        <span class="text-muted extra-small">coins</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $order->delivery_type === 'PICKUP' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-2 py-1 small">
                                            {{ $order->delivery_type }}
                                        </span>
                                    </td>
                                    <td>
                                        @php
                                            $badgeClass = match($order->status) {
                                                'PLACED', 'CONFIRMED' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                                'PACKING', 'PACKED' => 'bg-info-subtle text-info border border-info-subtle',
                                                'DISPATCHED', 'OUT_FOR_DELIVERY' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                'DELIVERED', 'PICKED_UP', 'COMPLETED' => 'bg-success-subtle text-success border border-success-subtle',
                                                'CANCELLED' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle'
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} rounded-pill px-2.5 py-1 small">{{ $order->status }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $order->created_at ? $order->created_at->format('d M, h:i A') : '' }}</td>
                                    <td class="pe-4 text-end">
                                        <a href="{{ route('admin.store.orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary py-1 px-2.5 rounded-3">
                                            Manage
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                        No recent orders found.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Redeemed Products --}}
        <div class="col-lg-4">
            <div class="dashboard-panel-card h-100">
                <div class="card-header bg-white py-3.5 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-trophy-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="m-0 fw-bold text-dark">Top Redeemed Products</h6>
                            <small class="text-muted extra-small">Most popular items by redemption</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($topProducts as $idx => $prod)
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4 border-bottom" style="transition: background-color 0.2s;">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge rounded-circle {{ $idx === 0 ? 'bg-warning text-dark' : ($idx === 1 ? 'bg-secondary text-white' : 'bg-light text-dark border') }} d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 30px; height: 30px;">
                                    {{ $idx + 1 }}
                                </span>
                                <div>
                                    <div class="fw-bold text-dark small">{{ $prod->name }}</div>
                                    <div class="text-muted extra-small">SKU: <code>{{ $prod->sku }}</code></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">{{ $prod->units_sold }} Sold</span>
                                <div class="text-muted extra-small mt-1 fw-bold">{{ number_format($prod->coins_redeemed) }} Coins</div>
                            </div>
                        </li>
                        @empty
                        <li class="list-group-item text-center py-5 text-muted small border-0">
                            <i class="bi bi-boxes fs-1 d-block mb-2 text-muted opacity-50"></i>
                            No product redemption data available yet.
                        </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
