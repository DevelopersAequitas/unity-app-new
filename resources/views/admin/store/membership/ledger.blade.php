@extends('admin.layouts.app')

@section('title', 'Peers Store — Active Members Roster')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.membership.index') }}" class="text-decoration-none text-muted">Memberships</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Roster</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-people-fill text-primary"></i> Active VIP Members Roster
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.membership.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Membership Tiers
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.membership.ledger') }}" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Peer Name or Phone..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.membership.ledger') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Members Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 30%;">Peer / Member</th>
                            <th style="width: 20%;">Membership Tier</th>
                            <th style="width: 15%;">Store Discount</th>
                            <th style="width: 20%;">Validity Period</th>
                            <th class="pe-4 text-end" style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($memberships as $mem)
                            @php
                                $u = $mem->user;
                                $peerName = $u ? ($u->display_name ?: ($u->name ?: trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')))) : null;
                                if (!$peerName) {
                                    $peerName = 'Peer #' . substr($mem->user_id, 0, 8);
                                }
                                $peerPhone = $u ? ($u->phone ?: ($u->phone_number ?: ($u->mobile ?: ($u->email ?: '—')))) : '—';
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $peerName }}</div>
                                    <small class="text-muted">{{ $peerPhone }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle fw-bold fs-6">
                                        {{ $mem->tier->name ?? $mem->tier_name ?? 'VIP Member' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">{{ $mem->tier->discount_percentage ?? 10 }}% OFF</span>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-muted">From:</span> {{ $mem->starts_at ? $mem->starts_at->format('d M Y') : '—' }}<br>
                                        <span class="text-muted">To:</span> {{ $mem->expires_at ? $mem->expires_at->format('d M Y') : 'Lifetime' }}
                                    </div>
                                </td>
                                <td class="pe-4 text-end">
                                    @if(!$mem->expires_at || $mem->expires_at->isFuture())
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Expired</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                    No VIP member records found matching your filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($memberships->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $memberships->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
