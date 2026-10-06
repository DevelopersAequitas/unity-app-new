@extends('admin.layouts.app')

@section('title', 'Peers Store — Ledger Reconciliation')

@section('content')
<style>
    .recon-kpi-card {
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    .badge-soft-success {
        background-color: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .badge-soft-danger {
        background-color: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .badge-soft-warning {
        background-color: #fffbeb;
        color: #92400e;
        border: 1px solid #fde68a;
    }
</style>

<div class="container-fluid px-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wallet.economy') }}" class="text-decoration-none text-muted">Economy</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Reconciliation</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3">
                    <i class="bi bi-shield-check fs-4"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold text-dark">Wallet vs Ledger Reconciliation</h1>
                    <p class="text-muted small mb-0">Cross-checks cached user balances against immutable append-only records in <code>coins_ledger</code>.</p>
                </div>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.store.wallet.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-wallet2"></i> Peer Wallets
            </a>
        </div>
    </div>

    <style>
        .recon-kpi-card {
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            transition: all 0.22s ease;
            text-decoration: none !important;
            color: inherit !important;
            display: block;
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        .recon-kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
        }
    </style>

    {{-- KPI Cards (Clickable) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.index') }}" class="recon-kpi-card p-3 shadow-sm" title="Click to view Member Coin Wallets">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Wallets Audited</span>
                    <span class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary fs-5"><i class="bi bi-people"></i></span>
                </div>
                <div class="h3 mb-0 fw-bold text-dark">{{ number_format($totalAudited ?? count($usersLedgerReconciliation)) }}</div>
                <div class="text-muted extra-small mt-1">Sample cohort &rarr; View all</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="#reconTable" class="recon-kpi-card p-3 shadow-sm" title="Click to view Wallets with Discrepancy">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Discrepancies</span>
                    <span class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger fs-5"><i class="bi bi-exclamation-triangle"></i></span>
                </div>
                <div class="h3 mb-0 fw-bold text-danger">{{ number_format($totalDiscrepancyCount) }}</div>
                <div class="text-muted extra-small mt-1">Wallets requiring review &darr;</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.index') }}" class="recon-kpi-card p-3 shadow-sm" title="Click to view Verified Member Wallets">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Matched Wallets</span>
                    <span class="p-2 rounded-3 bg-success bg-opacity-10 text-success fs-5"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="h3 mb-0 fw-bold text-success">{{ number_format($matchedCount ?? 0) }}</div>
                <div class="text-muted extra-small mt-1">100% verified ledger balance</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.adjustments') }}" class="recon-kpi-card p-3 shadow-sm" title="Click to view Coin Approval Queue">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Total Coin Variance</span>
                    <span class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning fs-5"><i class="bi bi-coin"></i></span>
                </div>
                <div class="h3 mb-0 fw-bold text-dark">{{ number_format($totalVarianceCoins ?? 0) }}</div>
                <div class="text-muted extra-small mt-1">Resolve in approval queue &rarr;</div>
            </a>
        </div>
    </div>

    {{-- Status Alert Notice --}}
    @if($totalDiscrepancyCount > 0)
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #fffaf0; border: 1px solid #feebc8 !important;">
        <div class="card-body p-3.5 d-flex align-items-center gap-3">
            <div class="p-2 rounded-3 bg-warning bg-opacity-20 text-warning-emphasis fs-4">
                <i class="bi bi-info-circle-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0.5 text-dark">
                    {{ $totalDiscrepancyCount }} Ledger Discrepancies Detected
                </h6>
                <p class="mb-0 text-muted small">
                    Cached balance in <code>users.coins_balance</code> differs from append-only sum in <code>coins_ledger</code>. You can inspect individual peer ledgers or submit adjustments to synchronize balances.
                </p>
            </div>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background-color: #f0fdf4; border: 1px solid #bbf7d0 !important;">
        <div class="card-body p-3.5 d-flex align-items-center gap-3">
            <div class="p-2 rounded-3 bg-success bg-opacity-20 text-success fs-4">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0.5 text-success">
                    100% Ledger Balance Integrity Verified
                </h6>
                <p class="mb-0 text-muted small">
                    All audited peer wallets match their append-only ledger transaction history perfectly.
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Reconciliation Table --}}
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i> Audited Peer Wallets
            </h6>
            <span class="badge bg-light text-muted border px-2.5 py-1">Top 100 Accounts</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 30%;">Peer / Member</th>
                            <th class="py-3" style="width: 20%;">Cached User Balance</th>
                            <th class="py-3" style="width: 20%;">Calculated Ledger Sum</th>
                            <th class="py-3" style="width: 15%;">Discrepancy (Diff)</th>
                            <th class="pe-4 text-end py-3" style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usersLedgerReconciliation as $row)
                            @php
                                $diff = $row['diff'] ?? (($row['cached_balance'] ?? 0) - ($row['ledger_sum'] ?? 0));
                                $userName = $row['user_name'] ?? 'Peer Member';
                            @endphp
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="rounded-circle bg-light text-dark border d-flex align-items-center justify-content-center fw-bold small flex-shrink-0" style="width: 38px; height: 38px;">
                                            {{ strtoupper(substr($userName, 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.store.wallet.show', $row['user_id']) }}" class="fw-bold text-dark text-decoration-none d-block">
                                                {{ $userName }}
                                            </a>
                                            @if(!empty($row['company_name']))
                                                <div class="text-muted extra-small"><i class="bi bi-building me-1"></i>{{ $row['company_name'] }}</div>
                                            @elseif(!empty($row['email']))
                                                <div class="text-muted extra-small"><i class="bi bi-envelope me-1"></i>{{ $row['email'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-dark fs-6">{{ number_format($row['cached_balance'] ?? 0) }}</span>
                                    <span class="text-muted extra-small">coins</span>
                                </td>
                                <td class="py-3">
                                    <span class="fw-semibold text-secondary fs-6">{{ number_format($row['ledger_sum'] ?? 0) }}</span>
                                    <span class="text-muted extra-small">coins</span>
                                </td>
                                <td class="py-3">
                                    @if($diff == 0)
                                        <span class="badge badge-soft-success px-2.5 py-1 rounded-pill small fw-semibold">
                                            <i class="bi bi-check2 me-1"></i> Matched
                                        </span>
                                    @else
                                        <span class="badge badge-soft-danger px-2.5 py-1 rounded-pill small fw-bold">
                                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }} Coins
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end py-3">
                                    <a href="{{ route('admin.store.wallet.show', $row['user_id']) }}" class="btn btn-sm btn-outline-primary rounded-3 px-3">
                                        <i class="bi bi-eye me-1"></i> View Ledger
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-check2-circle text-success fs-1 d-block mb-2"></i>
                                    All peer wallets perfectly match their append-only ledger transaction history.
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
