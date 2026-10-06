@extends('admin.layouts.app')

@section('title', 'Peers Store — Store Configuration')

@section('content')
<style>
    .config-card {
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.2s ease;
        height: 100%;
    }
    .config-card:hover {
        border-color: rgba(99, 102, 241, 0.3);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.04);
    }
    .config-card .card-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .status-hero-card {
        border-radius: 16px;
        transition: all 0.25s ease;
    }
</style>

<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Store Configuration</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3">
                    <i class="bi bi-sliders fs-4"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold text-dark">Store Configuration &amp; Rules</h1>
                    <p class="text-muted small mb-0">Manage store operational rules, coin burn boundaries, delivery parameters, and contact info.</p>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.store.config.policies') }}" class="btn btn-outline-primary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-file-earmark-text"></i> Store Terms &amp; Policies
            </a>
            <a href="{{ route('admin.store.config.system-health') }}" class="btn btn-outline-info d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-heart-pulse"></i> System Health
            </a>
            <a href="{{ route('admin.store.config.audit-log') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-shield-check"></i> Admin Action Logs
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 mb-4" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Store Status Hero Card --}}
    <div class="card status-hero-card border-0 shadow-sm mb-4 {{ $maintenanceMode ? 'bg-danger bg-opacity-10 border border-danger border-opacity-25' : 'bg-white border' }}">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-4">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-4 {{ $maintenanceMode ? 'bg-danger text-white' : 'bg-success text-white' }} shadow-sm">
                    <i class="bi {{ $maintenanceMode ? 'bi-shield-slash-fill' : 'bi-shield-check' }} fs-2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="fw-bold mb-0 {{ $maintenanceMode ? 'text-danger' : 'text-dark' }}">
                            Store Operational Status
                        </h5>
                        <span class="badge {{ $maintenanceMode ? 'bg-danger' : 'bg-success' }} rounded-pill px-2.5 py-1">
                            {{ $maintenanceMode ? 'MAINTENANCE MODE' : 'STORE LIVE & ACTIVE' }}
                        </span>
                    </div>
                    <p class="mb-0 text-muted small" style="max-width: 650px;">
                        {{ $maintenanceMode 
                            ? 'The store is currently locked for members. Mobile app users will see a maintenance notice and will not be able to browse or redeem items.' 
                            : 'The store is live and open. Peers can explore catalog items, redeem Unity Coins, and place delivery/pickup orders.' }}
                    </p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.store.config.maintenance') }}">
                @csrf
                <input type="hidden" name="maintenance_mode" value="{{ $maintenanceMode ? 0 : 1 }}">
                <button type="submit" class="btn {{ $maintenanceMode ? 'btn-success' : 'btn-outline-danger' }} d-flex align-items-center gap-2 px-4 py-2.5 rounded-3 fw-bold shadow-sm" onclick="return confirm('Are you sure you want to change the Store status?')">
                    <i class="bi {{ $maintenanceMode ? 'bi-play-circle-fill' : 'bi-pause-circle' }} fs-5"></i>
                    {{ $maintenanceMode ? 'Turn OFF Maintenance (Open Store)' : 'Enable Maintenance (Lock Store)' }}
                </button>
            </form>
        </div>
    </div>

    {{-- General Configuration Form --}}
    <form method="POST" action="{{ route('admin.store.config.update') }}">
        @csrf
        <div class="row g-4 mb-4">
            {{-- Section 1: Coin & Wallet Economics --}}
            <div class="col-lg-6">
                <div class="config-card p-4 shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                        <div class="card-header-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Coin Redemption Parameters</h5>
                            <small class="text-muted">Rules governing coin spend priority and checkout limits</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Max Bonus Coins Allowed Per Order (%)</label>
                        <div class="input-group">
                            <input type="number" name="configs[max_bonus_coin_percentage]" class="form-control" value="{{ $configs['max_bonus_coin_percentage'] ?? 50 }}" min="0" max="100">
                            <span class="input-group-text bg-light text-muted fw-bold">%</span>
                        </div>
                        <small class="text-muted d-block mt-1">Maximum portion of order total payable with Bonus Coins (balance must come from Earned Coins).</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Minimum Coin Balance for Checkout</label>
                        <div class="input-group">
                            <input type="number" name="configs[min_checkout_coins]" class="form-control" value="{{ $configs['min_checkout_coins'] ?? 0 }}" min="0">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-coin"></i> Coins</span>
                        </div>
                        <small class="text-muted d-block mt-1">Minimum total coin balance required to initiate cart checkout.</small>
                    </div>
                </div>
            </div>

            {{-- Section 2: Delivery & Shipping --}}
            <div class="col-lg-6">
                <div class="config-card p-4 shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                        <div class="card-header-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Delivery &amp; Return Windows</h5>
                            <small class="text-muted">Courier fee policies and customer return timelines</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Flat Delivery Charge (In Coins)</label>
                        <div class="input-group">
                            <input type="number" name="configs[delivery_charge_coins]" class="form-control" value="{{ $configs['delivery_charge_coins'] ?? 0 }}" min="0">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-coin"></i> Coins</span>
                        </div>
                        <small class="text-muted d-block mt-1">Doorstep courier shipping fee in coins (Enter 0 for Free Delivery).</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Return Window (Days After Delivery)</label>
                        <div class="input-group">
                            <input type="number" name="configs[return_window_days]" class="form-control" value="{{ $configs['return_window_days'] ?? 7 }}" min="1" max="30">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar3"></i> Days</span>
                        </div>
                        <small class="text-muted d-block mt-1">Number of calendar days a member has to initiate a return request after delivery.</small>
                    </div>
                </div>
            </div>

            {{-- Section 3: Inventory & Order Safeguards --}}
            <div class="col-lg-6">
                <div class="config-card p-4 shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                        <div class="card-header-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Inventory &amp; Order Safeguards</h5>
                            <small class="text-muted">Stock threshold warnings and anti-hoarding caps</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Global Default Low-Stock Threshold</label>
                        <div class="input-group">
                            <input type="number" name="configs[default_low_stock_threshold]" class="form-control" value="{{ $configs['default_low_stock_threshold'] ?? 5 }}" min="1">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-exclamation-triangle"></i> Units</span>
                        </div>
                        <small class="text-muted d-block mt-1">Triggers low-stock warnings when item SKU level drops below this count.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Max Quantity Allowed Per Single Product Order</label>
                        <div class="input-group">
                            <input type="number" name="configs[max_units_per_order]" class="form-control" value="{{ $configs['max_units_per_order'] ?? 10 }}" min="1">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-cart"></i> Units</span>
                        </div>
                        <small class="text-muted d-block mt-1">Maximum quantity a member can order for any single SKU in a single transaction.</small>
                    </div>
                </div>
            </div>

            {{-- Section 4: Store Support & Helpdesk Contacts --}}
            <div class="col-lg-6">
                <div class="config-card p-4 shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-3 pb-2 border-bottom">
                        <div class="card-header-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-headset"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Helpdesk &amp; Support Contact</h5>
                            <small class="text-muted">Contact info displayed on member packing slips and receipts</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Support Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="configs[support_email]" class="form-control" value="{{ $configs['support_email'] ?? 'store-support@peersglobal.com' }}">
                        </div>
                        <small class="text-muted d-block mt-1">Official support email for store and coin queries.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Support Helpline Phone</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                            <input type="text" name="configs[support_phone]" class="form-control" value="{{ $configs['support_phone'] ?? '+91 9876543210' }}">
                        </div>
                        <small class="text-muted d-block mt-1">Helpline number displayed in the store helpdesk footer.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 bg-white p-3 d-flex flex-row justify-content-between align-items-center">
            <span class="text-muted small ps-2">
                <i class="bi bi-info-circle me-1"></i> Changes take effect immediately across web and mobile app endpoints.
            </span>
            <button type="submit" class="btn btn-primary px-4 py-2.5 rounded-3 fw-bold d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-check2-circle fs-5"></i> Save Configuration Changes
            </button>
        </div>
    </form>
</div>
@endsection
