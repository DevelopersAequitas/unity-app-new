@extends('admin.layouts.app')

@section('title', 'Peers Store — Coin Economy Report')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wallet.economy') }}" class="text-decoration-none text-muted">Economy</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Analytics Report</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-pie-chart-fill text-primary"></i> Coin Economy Macro Analytics
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.reports.reconciliation') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-shield-check"></i> Ledger Reconciliation
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.reports.coin-economy') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.reports.coin-economy') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .economy-report-card {
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
        .economy-report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
        }
        .economy-report-card .card-accent-bar {
            height: 4px;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
    </style>

    {{-- Macro Economy Metrics (Clickable) --}}
    <div class="row g-3 mb-4">
        <!-- Total Circulation -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.index') }}" class="economy-report-card p-4 shadow-sm" title="Click to view Member Coin Wallets">
                <div class="card-accent-bar bg-primary"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Total Circulation</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 extra-small">Wallets &rarr;</span>
                </div>
                <h3 class="fw-bold text-primary my-2">{{ number_format($totalCirculation) }}</h3>
                <small class="text-muted d-block">Total active coins in all peer wallets</small>
            </a>
        </div>

        <!-- Earned Proportion -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.economy') }}" class="economy-report-card p-4 shadow-sm" title="Click to view Coin Economy Overview">
                <div class="card-accent-bar bg-success"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Earned Proportion</span>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 extra-small">Overview &rarr;</span>
                </div>
                <h3 class="fw-bold text-success my-2">{{ number_format($totalEarnedInCirculation) }}</h3>
                <small class="text-muted d-block">Earned via business & networking</small>
            </a>
        </div>

        <!-- Bonus Proportion -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.adjustments') }}" class="economy-report-card p-4 shadow-sm" title="Click to view Coin Approval Queue">
                <div class="card-accent-bar bg-info"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Bonus Proportion</span>
                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 extra-small">Queue &rarr;</span>
                </div>
                <h3 class="fw-bold text-info my-2">{{ number_format($totalBonusInCirculation) }}</h3>
                <small class="text-muted d-block">Granted promotional coins</small>
            </a>
        </div>

        <!-- Store Redemptions (Burn) -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.reports.sales') }}" class="economy-report-card p-4 shadow-sm" title="Click to view Sales & Redemptions Report">
                <div class="card-accent-bar bg-danger"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Store Redemptions</span>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5 extra-small">Sales &rarr;</span>
                </div>
                <h3 class="fw-bold text-danger my-2">{{ number_format($totalBurnedInStore) }}</h3>
                <small class="text-muted d-block">Burned through catalog orders</small>
            </a>
        </div>
    </div>

    {{-- Burn Daily Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-fire text-danger"></i> Daily Coin Redemption Volume
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 40%;">Date</th>
                            <th style="width: 30%;">Burn Transactions</th>
                            <th class="pe-4 text-end" style="width: 30%;">Coins Burned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($burnByDay as $day)
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">{{ $day->burn_date ?? $day->day ?? '—' }}</td>
                                <td><span class="badge bg-light text-dark border px-2 py-1">{{ $day->burn_count ?? 0 }} burns</span></td>
                                <td class="pe-4 text-end fw-bold text-danger fs-6">-{{ number_format(abs($day->daily_burned ?? $day->total_burned ?? 0)) }} Coins</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    No redemption burns recorded in this period.
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
