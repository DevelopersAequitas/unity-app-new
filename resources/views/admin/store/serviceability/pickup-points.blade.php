@extends('admin.layouts.app')

@section('title', 'Peers Store — Central Pickup Points')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Central Pickup Points</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary"></i> Central Hubs & Pickup Points
            </h1>
        </div>
        <div>
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addPickupModal">
                <i class="bi bi-plus-lg"></i> Add Pickup Hub
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
            <div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Pickup Points Cards --}}
    <div class="row g-4">
        @forelse($pickupPoints as $point)
            @php
                $isActive = (bool) ($point->is_active ?? ($point->status === 'ACTIVE'));
                $displayAddress = $point->address ?: ($point->address_line1 . ($point->city ? ', ' . $point->city : ''));
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card shadow-sm border-0 rounded-4 h-100 {{ !$isActive ? 'opacity-75 bg-light' : '' }}">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-1">{{ $point->name }}</h5>
                                <span class="badge {{ $isActive ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-2.5 py-1 small">
                                    {{ $isActive ? 'Active for Pickup' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li>
                                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#editPickupModal{{ $point->id }}">
                                            <i class="bi bi-pencil me-2 text-primary"></i> Edit Hub
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.store.serviceability.pickup-points.status', $point->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item py-2 {{ $isActive ? 'text-danger' : 'text-success' }}">
                                                <i class="bi {{ $isActive ? 'bi-x-circle' : 'bi-check-circle' }} me-2"></i>
                                                {{ $isActive ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="text-muted small mb-3">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                            {{ $displayAddress ?: 'Address not specified' }}
                        </div>

                        <div class="border-top pt-3 small text-muted">
                            <div class="d-flex justify-content-between py-1">
                                <span><i class="bi bi-person me-1"></i> Contact Person:</span>
                                <span class="fw-semibold text-dark">{{ $point->contact_person ?: '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span><i class="bi bi-telephone me-1"></i> Phone:</span>
                                <span class="fw-semibold text-dark">{{ $point->contact_phone ?: ($point->phone ?: '—') }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span><i class="bi bi-clock me-1"></i> Operating Hours:</span>
                                <span class="fw-semibold text-dark">{{ $point->timings ?: ($point->operating_hours ?: '10:00 AM - 07:00 PM') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Edit Pickup Modal --}}
            <div class="modal fade" id="editPickupModal{{ $point->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content rounded-4 border-0 shadow">
                        <form method="POST" action="{{ route('admin.store.serviceability.pickup-points.update', $point->id) }}">
                            @csrf
                            <div class="modal-header border-bottom py-3">
                                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2"><i class="bi bi-pencil text-primary"></i> Edit Pickup Hub</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Hub Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control rounded-3" value="{{ $point->name }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Full Address / Location <span class="text-danger">*</span></label>
                                    <textarea name="address_line1" class="form-control rounded-3" rows="2" required>{{ $point->address ?: $point->address_line1 }}</textarea>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <label class="form-label fw-semibold small">City</label>
                                        <input type="text" name="city" class="form-control rounded-3" value="{{ $point->city }}">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label fw-semibold small">State</label>
                                        <input type="text" name="state" class="form-control rounded-3" value="{{ $point->state }}">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label fw-semibold small">Pincode</label>
                                        <input type="text" name="pincode" class="form-control rounded-3" value="{{ $point->pincode }}" maxlength="6">
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Contact Person</label>
                                        <input type="text" name="contact_person" class="form-control rounded-3" value="{{ $point->contact_person }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small">Phone</label>
                                        <input type="text" name="contact_phone" class="form-control rounded-3" value="{{ $point->contact_phone ?: $point->phone }}">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold small">Operating Hours</label>
                                    <input type="text" name="operating_hours" class="form-control rounded-3" value="{{ $point->timings ?: ($point->operating_hours ?: '10:00 AM - 07:00 PM') }}">
                                </div>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editActive{{ $point->id }}" {{ $isActive ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold small" for="editActive{{ $point->id }}">Active for Hub Pickup</label>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top py-3">
                                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-3 fw-semibold"><i class="bi bi-check2 me-1"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-building fs-1 d-block mb-2 text-secondary"></i>
                No central pickup hubs created yet. Click "Add Pickup Hub" to configure physical collection points.
            </div>
        @endforelse
    </div>
</div>

{{-- Add Pickup Modal --}}
<div class="modal fade" id="addPickupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.serviceability.pickup-points.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg text-primary"></i> Add Central Pickup Hub</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hub Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Peers HQ Central Vault" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address Line 1 <span class="text-danger">*</span></label>
                        <input type="text" name="address_line1" class="form-control" placeholder="Building, Street, Landmark" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address Line 2</label>
                        <input type="text" name="address_line2" class="form-control" placeholder="Suite, Floor, etc.">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" placeholder="Ahmedabad" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                            <input type="text" name="state" class="form-control" placeholder="Gujarat" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                            <input type="text" name="pincode" class="form-control" placeholder="380015" maxlength="6" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Store Manager Name">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="contact_phone" class="form-control" placeholder="+91 9876543210">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Operating Hours</label>
                        <input type="text" name="operating_hours" class="form-control" placeholder="Mon-Sat: 10:00 AM - 07:00 PM">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newActiveCheck" checked>
                        <label class="form-check-label fw-semibold" for="newActiveCheck">Immediately Active for Hub Pickup</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Hub</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
