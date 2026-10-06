@extends('admin.layouts.app')

@section('title', 'Peers Store — Serviceable Pincodes')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Serviceable Pincodes</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-geo-alt-fill text-primary"></i> Serviceable Pincodes & Delivery Coverage
            </h1>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addPincodeModal">
                <i class="bi bi-plus-lg"></i> Add Pincode
            </button>
            <button class="btn btn-outline-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importPincodesModal">
                <i class="bi bi-file-earmark-arrow-up"></i> Bulk Import CSV
            </button>
            <a href="{{ route('admin.store.serviceability.pincodes.export') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
            </a>
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

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.serviceability.pincodes') }}" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by Pincode, City, State or Hub..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="is_active" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="1" {{ $activeFilter === '1' ? 'selected' : '' }}>Active (Serviceable)</option>
                        <option value="0" {{ $activeFilter === '0' ? 'selected' : '' }}>Inactive (Non-serviceable)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Search</button>
                    <a href="{{ route('admin.store.serviceability.pincodes') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Pincodes Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Pincode</th>
                            <th style="width: 25%;">City / District</th>
                            <th style="width: 20%;">State</th>
                            <th style="width: 15%;">Delivery Days (TAT)</th>
                            <th style="width: 10%;">Status</th>
                            <th class="pe-4 text-end" style="width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pincodes as $pin)
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border fs-6 fw-bold px-2 py-1">
                                        {{ $pin->pincode }}
                                    </span>
                                </td>
                                <td><span class="fw-semibold text-dark">{{ $pin->city ?: '—' }}</span></td>
                                <td>{{ $pin->state ?: '—' }}</td>
                                <td>
                                    <span class="text-muted"><i class="bi bi-truck me-1"></i> {{ $pin->estimated_delivery_days ?? 3 }} day(s)</span>
                                </td>
                                <td>
                                    @if($pin->is_active)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                    @endif
                                </td>
                                <td class="pe-4 text-end">
                                    <form method="POST" action="{{ route('admin.store.serviceability.pincodes.status', $pin->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="is_active" value="{{ $pin->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm {{ $pin->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" title="Toggle Active Status">
                                            <i class="bi {{ $pin->is_active ? 'bi-x-circle' : 'bi-check-circle' }}"></i> {{ $pin->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-geo-alt fs-1 d-block mb-2 text-secondary"></i>
                                    No serviceable pincodes registered matching this search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($pincodes->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $pincodes->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Add Pincode Modal --}}
<div class="modal fade" id="addPincodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.serviceability.pincodes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-geo-alt-fill text-primary"></i> Add Serviceable Pincode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pincode (6 digits) <span class="text-danger">*</span></label>
                        <input type="text" name="pincode" class="form-control" maxlength="6" pattern="\d{6}" required placeholder="e.g. 380015">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">City / District</label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Ahmedabad">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">State</label>
                            <input type="text" name="state" class="form-control" placeholder="e.g. Gujarat">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estimated Delivery Days (TAT)</label>
                        <input type="number" name="estimated_delivery_days" class="form-control" min="1" max="30" value="3">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="pincodeActiveCheck" checked>
                        <label class="form-check-label fw-semibold" for="pincodeActiveCheck">Immediately Active for Deliveries</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Save Pincode</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Import CSV Modal --}}
<div class="modal fade" id="importPincodesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.serviceability.pincodes.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up text-primary"></i> Bulk Import Pincodes CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <strong>CSV Format Requirements:</strong>
                        <ul class="mb-0 ps-3">
                            <li>Header row required: <code>pincode,city,state,estimated_delivery_days,is_active</code></li>
                            <li>Pincode must be 6 digits.</li>
                            <li>Existing pincodes will be updated in-place.</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload & Process</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
