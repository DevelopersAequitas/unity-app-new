@extends('admin.layouts.app')

@section('title', 'Peers Store — Product Entitlements')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.membership.index') }}" class="text-decoration-none text-muted">Memberships</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Product Entitlements</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-award-fill text-primary"></i> Manual Product Entitlements & Access Grants
            </h1>
        </div>
        <div>
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#grantEntitlementModal">
                <i class="bi bi-plus-lg"></i> Grant Entitlement
            </button>
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

    {{-- Entitlements Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 25%;">Peer / User</th>
                            <th style="width: 30%;">Product / Perk</th>
                            <th style="width: 15%;">Granted Date</th>
                            <th style="width: 15%;">Status</th>
                            <th class="pe-4 text-end" style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entitlements as $ent)
                            @php
                                $u = $ent->user;
                                $peerName = $u ? ($u->display_name ?: ($u->name ?: trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')))) : null;
                                if (!$peerName) {
                                    $peerName = 'User #' . substr($ent->user_id, 0, 8);
                                }
                                $peerPhone = $u ? ($u->phone ?: ($u->phone_number ?: ($u->mobile ?: ($u->email ?: '—')))) : '—';
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $peerName }}</div>
                                    <small class="text-muted">{{ $peerPhone }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $ent->product->name ?? 'Product #' . $ent->product_id }}</div>
                                    <small class="text-muted">Type: {{ ucfirst($ent->entitlement_type ?? 'Exclusive Access') }}</small>
                                </td>
                                <td>
                                    <div class="small text-muted">{{ $ent->created_at ? $ent->created_at->format('d M Y') : '—' }}</div>
                                </td>
                                <td>
                                    @if($ent->is_active ?? true)
                                        <span class="badge bg-success">Granted</span>
                                    @else
                                        <span class="badge bg-danger">Revoked</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    @if($ent->is_active ?? true)
                                        <form method="POST" action="{{ route('admin.store.membership.entitlements.revoke', $ent->id) }}" onsubmit="return confirm('Revoke this product entitlement?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle"></i> Revoke
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-award fs-1 d-block mb-2 text-secondary"></i>
                                    No custom product entitlements recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($entitlements->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $entitlements->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Grant Entitlement Modal --}}
<div class="modal fade" id="grantEntitlementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.membership.entitlements.grant') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-award text-primary"></i> Grant Product Entitlement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Peer <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select Peer...</option>
                            @foreach($users as $u)
                                @php
                                    $pName = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                                    if (!$pName) {
                                        $pName = $u->display_name ?: ($u->name ?: ($u->email ?: 'User #' . substr($u->id, 0, 8)));
                                    }
                                    $pPhone = $u->phone ?: ($u->phone_number ?: ($u->mobile ?: ($u->email ?: '')));
                                @endphp
                                <option value="{{ $u->id }}">{{ $pName }}{{ $pPhone ? ' (' . $pPhone . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Exclusive Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} ({{ number_format($p->coin_price) }} Coins)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason / Grant Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. VIP speaker privilege, contest winner..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-award"></i> Grant Access</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
