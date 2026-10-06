@extends('admin.layouts.app')

@section('title', 'Peers Store — VIP Membership Tiers')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Membership Tiers</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-stars text-warning"></i> VIP Membership & Discount Tiers
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.store.membership.ledger') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-people"></i> Active Members Roster
            </a>
            <a href="{{ route('admin.store.membership.entitlements') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-award"></i> Entitlements
            </a>
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addTierModal">
                <i class="bi bi-plus-lg"></i> Create VIP Tier
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

    {{-- Tiers Grid --}}
    <div class="row g-4">
        @forelse($tiers as $tier)
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100 {{ !$tier->is_active ? 'opacity-75' : '' }}">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-gem text-warning fs-5"></i>
                            <h5 class="fw-bold mb-0 text-dark">{{ $tier->name }}</h5>
                        </div>
                        <span class="badge {{ $tier->is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $tier->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="text-muted small">Tier Key:</span>
                            <code>{{ $tier->tier_code ?? $tier->slug }}</code>
                        </div>
                        <div class="p-3 bg-light rounded mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Store Discount:</span>
                                <span class="fw-bold text-success">{{ $tier->discount_percentage ?? 0 }}% OFF</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Annual Fee:</span>
                                <span class="fw-bold text-primary">{{ number_format($tier->annual_fee_coins ?? 0) }} Coins</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Active Members:</span>
                                <span class="badge bg-primary rounded-pill">{{ $membershipsCountByTier[$tier->id] ?? 0 }}</span>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">{{ $tier->description ?: 'Standard membership tier privileges.' }}</p>
                    </div>
                    <div class="card-footer bg-white border-top-0 pb-3 d-flex justify-content-between align-items-center">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTierModal{{ $tier->id }}">
                            <i class="bi bi-pencil"></i> Edit Tier
                        </button>
                    </div>
                </div>
            </div>

            {{-- Edit Tier Modal --}}
            <div class="modal fade" id="editTierModal{{ $tier->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.store.membership.tiers.update', $tier->id) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Edit Tier: {{ $tier->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tier Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ $tier->name }}" required>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Discount (%)</label>
                                        <input type="number" name="discount_percentage" class="form-control" value="{{ $tier->discount_percentage ?? 0 }}" min="0" max="100">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Annual Fee (Coins)</label>
                                        <input type="number" name="annual_fee_coins" class="form-control" value="{{ $tier->annual_fee_coins ?? 0 }}" min="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="2">{{ $tier->description }}</textarea>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editActive{{ $tier->id }}" {{ $tier->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="editActive{{ $tier->id }}">Active for Purchase/Assignment</label>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-stars fs-1 d-block mb-2 text-secondary"></i>
                No VIP membership tiers configured yet.
            </div>
        @endforelse
    </div>
</div>

{{-- Add Tier Modal --}}
<div class="modal fade" id="addTierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.membership.tiers.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg text-primary"></i> Create VIP Membership Tier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Platinum Executive" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Discount Percentage (%)</label>
                            <input type="number" name="discount_percentage" class="form-control" value="10" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Annual Fee (Coins)</label>
                            <input type="number" name="annual_fee_coins" class="form-control" value="0" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Describe benefits and privileges..."></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newTierActive" checked>
                        <label class="form-check-label fw-semibold" for="newTierActive">Immediately Active</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Tier</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
