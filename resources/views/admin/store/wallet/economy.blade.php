@extends('admin.layouts.app')

@section('title', 'Peers Store — Coin Economy Overview')

@section('content')
<style>
    .economy-kpi-card {
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
    .economy-kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08) !important;
        border-color: rgba(99, 102, 241, 0.3) !important;
    }
    .economy-kpi-card .card-accent-bar {
        height: 4px;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
    }
</style>

<div class="container-fluid px-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.wallet.index') }}" class="text-decoration-none text-muted">Wallets</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Economy Overview</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3">
                    <i class="bi bi-graph-up fs-4"></i>
                </div>
                <div>
                    <h1 class="h3 mb-0 fw-bold text-dark">Unity Coin Economy & Circulation</h1>
                    <p class="text-muted small mb-0">Live macroeconomic indicators, system circulation, mint/burn tracking, and top holders.</p>
                </div>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.store.reports.coin-economy') }}" class="btn btn-outline-primary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-file-earmark-text"></i> Economy Analytics Report
            </a>
        </div>
    </div>

    {{-- Economy KPI Cards (All Clickable) --}}
    <div class="row g-3 mb-4">
        <!-- Total Circulation -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.index') }}" class="economy-kpi-card p-4 shadow-sm" title="Click to view all Peer Wallets">
                <div class="card-accent-bar bg-primary"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Total Circulation</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5 extra-small">Wallets &rarr;</span>
                </div>
                <h3 class="fw-bold text-primary my-2">{{ number_format($totalCirculation) }}</h3>
                <small class="text-muted d-block">Total active coins in all peer wallets</small>
            </a>
        </div>

        <!-- Earned Coins -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.reports.coin-economy') }}" class="economy-kpi-card p-4 shadow-sm" title="Click to view Coin Flow Analytics">
                <div class="card-accent-bar bg-success"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Earned Coins</span>
                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5 extra-small">Flow &rarr;</span>
                </div>
                <h3 class="fw-bold text-success my-2">{{ number_format($totalEarnedInCirculation) }}</h3>
                <small class="text-muted d-block">Earned via business & networking</small>
            </a>
        </div>

        <!-- Bonus Coins -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.wallet.adjustments') }}" class="economy-kpi-card p-4 shadow-sm" title="Click to view Coin Approval Queue">
                <div class="card-accent-bar bg-info"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Bonus Coins</span>
                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5 extra-small">Queue &rarr;</span>
                </div>
                <h3 class="fw-bold text-info my-2">{{ number_format($totalBonusInCirculation) }}</h3>
                <small class="text-muted d-block">Granted through promotional campaigns</small>
            </a>
        </div>

        <!-- Coins Burned In Store -->
        <div class="col-md-3">
            <a href="{{ route('admin.store.reports.sales') }}" class="economy-kpi-card p-4 shadow-sm" title="Click to view Sales & Redemption Reports">
                <div class="card-accent-bar bg-danger"></div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold extra-small text-uppercase">Coins Burned in Store</span>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5 extra-small">Sales &rarr;</span>
                </div>
                <h3 class="fw-bold text-danger my-2">{{ number_format($totalBurnedInStore) }}</h3>
                <small class="text-muted d-block">Redeemed for catalog merchandise</small>
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Top Coin Holders --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-trophy text-warning"></i> Top Coin Holders
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 12%;">Rank</th>
                                    <th class="py-3" style="width: 58%;">Peer Member</th>
                                    <th class="pe-4 text-end py-3" style="width: 30%;">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topHolders as $index => $holder)
                                    @php
                                        $fullName = trim(($holder->first_name ?? '') . ' ' . ($holder->last_name ?? ''));
                                        $holderName = $fullName ?: ($holder->display_name ?: ($holder->name ?: 'Peer Member'));
                                        $cityName = $holder->cityRelation?->name ?? $holder->city ?? null;
                                    @endphp
                                    <tr>
                                        <td class="ps-4 py-3">
                                            @if($index === 0)
                                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold"><i class="bi bi-award-fill me-0.5"></i> #1</span>
                                            @elseif($index === 1)
                                                <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1 fw-bold"><i class="bi bi-award-fill me-0.5"></i> #2</span>
                                            @elseif($index === 2)
                                                <span class="badge text-white rounded-pill px-2.5 py-1 fw-bold" style="background-color: #cd7f32;"><i class="bi bi-award-fill me-0.5"></i> #3</span>
                                            @else
                                                <span class="text-muted fw-semibold ms-2">#{{ $index + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <div class="d-flex align-items-center gap-2.5">
                                                <div class="rounded-circle bg-light text-dark border d-flex align-items-center justify-content-center fw-bold small flex-shrink-0" style="width: 34px; height: 34px;">
                                                    {{ strtoupper(substr($holderName, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <a href="{{ route('admin.store.wallet.show', $holder->id) }}" class="fw-bold text-decoration-none text-dark d-block">
                                                        {{ $holderName }}
                                                    </a>
                                                    <div class="text-muted extra-small">
                                                        @if($holder->company_name)
                                                            <span><i class="bi bi-building me-1"></i>{{ $holder->company_name }}</span>
                                                        @elseif($cityName)
                                                            <span><i class="bi bi-geo-alt me-1"></i>{{ $cityName }}</span>
                                                        @else
                                                            <span>{{ $holder->email ?? 'Active Peer' }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="pe-4 text-end py-3">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <span class="fw-bold fs-6 text-primary">{{ number_format($holder->coins_balance) }}</span>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-1.5 py-0.5 rounded-pill extra-small">Coins</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">No data available</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Store Redemptions (Burns) --}}
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-fire text-danger"></i> Recent Store Redemptions
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 25%;">Timestamp</th>
                                    <th class="py-3" style="width: 45%;">Peer / Order</th>
                                    <th class="pe-4 text-end py-3" style="width: 30%;">Coins Burned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentBurnTransactions as $burn)
                                    @php
                                        $burnPeerName = trim(($burn->first_name ?? '') . ' ' . ($burn->last_name ?? '')) ?: ($burn->display_name ?? 'Peer #' . $burn->user_id);
                                    @endphp
                                    <tr>
                                        <td class="ps-4 text-muted small py-3">
                                            {{ $burn->created_at ? \Carbon\Carbon::parse($burn->created_at)->format('d M, h:i A') : '—' }}
                                        </td>
                                        <td class="py-3">
                                            <div class="fw-semibold text-dark">{{ $burnPeerName }}</div>
                                            <small class="text-muted">{{ Str::limit($burn->remark ?? ($burn->reference ?? 'Coin Redemption'), 35) }}</small>
                                        </td>
                                        <td class="pe-4 text-end py-3">
                                            <span class="fw-bold text-danger">-{{ number_format(abs($burn->coins ?? $burn->amount ?? 0)) }}</span>
                                            <small class="text-muted">Coins</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">No redemptions recorded yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
