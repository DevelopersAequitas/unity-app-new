@extends('admin.layouts.app')

@section('title', 'Peers Store — Sales & Redemption Analytics')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sales Reports</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow text-primary"></i> Store Sales & Coin Redemptions
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.reports.sales.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.reports.sales') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Apply Date Range</button>
                    <a href="{{ route('admin.store.reports.sales') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .sales-kpi-card {
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            transition: all 0.22s ease;
            text-decoration: none !important;
            color: inherit !important;
            display: block;
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        .sales-kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
        }
        .sales-kpi-card .card-accent-bar {
            height: 4px;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
    </style>

    {{-- Summary KPIs (Clickable) --}}
    <div class="row g-3 mb-4">
        <!-- Total Fulfilled Orders -->
        <div class="col-md-4">
            <a href="{{ route('admin.store.orders.index', ['status' => 'DELIVERED']) }}" class="sales-kpi-card p-4 shadow-sm" title="Click to view Fulfilled Orders">
                <div class="card-accent-bar bg-primary"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Total Fulfilled Orders</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 extra-small">Orders &rarr;</span>
                </div>
                <h2 class="fw-bold text-primary my-2">{{ number_format($totalOrders) }}</h2>
                <small class="text-muted d-block">Completed in selected period</small>
            </a>
        </div>

        <!-- Total Coin Volume Burned -->
        <div class="col-md-4">
            <a href="{{ route('admin.store.reports.coin-economy') }}" class="sales-kpi-card p-4 shadow-sm" title="Click to view Coin Flow Analytics">
                <div class="card-accent-bar bg-success"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Total Coin Volume Burned</span>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 extra-small">Economy &rarr;</span>
                </div>
                <h2 class="fw-bold text-success my-2">{{ number_format($totalCoinsVolume) }}</h2>
                <small class="text-muted d-block">Unity coins spent on merchandise</small>
            </a>
        </div>

        <!-- Average Order Value -->
        <div class="col-md-4">
            <a href="{{ route('admin.store.reports.products') }}" class="sales-kpi-card p-4 shadow-sm" title="Click to view Product Performance Report">
                <div class="card-accent-bar bg-info"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Average Order Value</span>
                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 extra-small">Products &rarr;</span>
                </div>
                <h2 class="fw-bold text-info my-2">{{ number_format($avgOrderCoins) }}</h2>
                <small class="text-muted d-block">Coins per order average</small>
            </a>
        </div>
    </div>

    {{-- Sales Daily Breakdown Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-calendar3 text-primary"></i> Daily Sales Volume Breakdown
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 30%;">Date</th>
                            <th style="width: 30%;">Orders Count</th>
                            <th class="pe-4 text-end" style="width: 40%;">Total Coins Burned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesByDay as $day)
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">{{ $day->order_date ?? $day->day ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark border px-2 py-1">{{ $day->orders_count ?? 0 }} orders</span></td>
                                <td class="pe-4 text-end fw-bold text-primary fs-6">{{ number_format($day->daily_coins ?? $day->total_coins ?? 0) }} Coins</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <i class="bi bi-graph-down fs-1 d-block mb-2 text-secondary"></i>
                                    No sales data recorded in this date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
