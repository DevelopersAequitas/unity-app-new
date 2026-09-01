@extends('admin.layouts.app')
@section('title', 'Data Scope Rules — Dynamic RBAC')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-funnel-fill text-primary me-2"></i>Data Scope Assignments & Boundaries</h4>
        <p class="text-muted small mb-0">Define geographic and organizational data access limits per role to restrict record visibility.</p>
    </div>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Scope Explainer Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                <i class="bi bi-globe fs-4"></i> Global Scope
            </div>
            <p class="text-muted small mb-0">Full access to all records across all districts, circles, and industries without restriction.</p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-info fw-bold">
                <i class="bi bi-geo-alt-fill fs-4"></i> District Scope
            </div>
            <p class="text-muted small mb-0">Limits record visibility strictly to the admin's assigned District (e.g. DED roles).</p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-warning fw-bold">
                <i class="bi bi-building fs-4"></i> Industry Scope
            </div>
            <p class="text-muted small mb-0">Restricts member data and leads strictly to the admin's assigned Industry Sector.</p>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold">
                <i class="bi bi-people-fill fs-4"></i> Circle Scope
            </div>
            <p class="text-muted small mb-0">Limits operational visibility strictly to members inside the admin's assigned Circle.</p>
        </div>
    </div>
</div>

{{-- New Scope Form --}}
<div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Add Data Scope Constraint</h6>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.rbac.data-scope.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small text-muted">TARGET ROLE</label>
                    <select name="role_id" class="form-select rounded-3" id="roleSelect" onchange="autoSelectScopeType()" required>
                        <option value="">— Select Role —</option>
                        @foreach($roles as $role)
                            @php
                                $key = strtolower($role->key ?? '');
                                $name = strtolower($role->name ?? '');
                                $defaultScope = 'circle';
                                if (str_contains($key, 'global') || str_contains($name, 'global')) {
                                    $defaultScope = 'global';
                                } elseif ($key === 'ded' || str_contains($key, 'district') || str_contains($name, 'district') || $name === 'ded') {
                                    $defaultScope = 'district';
                                } elseif ($key === 'industry_director' || $key === 'id' || $name === 'id' || str_contains($key, 'industry') || str_contains($name, 'industry')) {
                                    $defaultScope = 'industry';
                                }
                            @endphp
                            <option value="{{ $role->id }}" data-scope-type="{{ $defaultScope }}">{{ $role->name }} ({{ $role->key }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small text-muted">SCOPE TYPE</label>
                    <select name="scope_type" class="form-select rounded-3" id="scopeType" onchange="toggleScopeEntity()">
                        <option value="global">Global (Unrestricted)</option>
                        <option value="circle">Circle Boundary</option>
                        <option value="district">District Boundary</option>
                        <option value="industry">Industry Sector</option>
                    </select>
                </div>
                <div class="col-md-4" id="scopeEntityCol">
                    <label class="form-label fw-semibold small text-muted">SPECIFIC TARGET ENTITY</label>
                    <select name="scope_id" class="form-select rounded-3" id="scopeEntity">
                        <option value="">— Select Entity —</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-3" title="Add Scope Rule">
                        <i class="bi bi-plus-lg"></i> Add
                    </button>
                </div>
            </div>
            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger mt-3 mb-0 rounded-3">@foreach($errors->all() as $e)<div><i class="bi bi-exclamation-triangle me-1"></i>{{ $e }}</div>@endforeach</div>
            @endif
        </form>
    </div>
</div>

{{-- Existing Scopes --}}
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-task me-2 text-primary"></i>Active Data Scope Constraints</h6>
        <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ count($scopes) }} Scope Rules Active</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Role Name</th>
                        <th>Scope Type</th>
                        <th>Target Entity ID / Name</th>
                        <th>Assigned Date</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scopes as $scope)
                    <tr>
                        <td class="ps-4 fw-bold text-dark">
                            <i class="bi bi-shield-lock text-primary me-2"></i>{{ $scope->role?->name ?? '—' }}
                        </td>
                        <td>
                            @php
                                $badgeClass = match($scope->scope_type) {
                                    'global' => 'bg-primary',
                                    'district' => 'bg-info text-dark',
                                    'industry' => 'bg-warning text-dark',
                                    'circle' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill fs-7">{{ ucfirst($scope->scope_type) }}</span>
                        </td>
                        <td>
                            <code>{{ $scope->scope_id ?? 'ALL_RECORDS' }}</code>
                        </td>
                        <td class="text-muted small">{{ $scope->created_at->format('M d, Y') }}</td>
                        <td class="text-end pe-4">
                            <form method="POST" action="{{ route('admin.rbac.data-scope.destroy', $scope->id) }}" class="d-inline" onsubmit="return confirm('Remove this data scope rule?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm rounded-2"><i class="bi bi-trash me-1"></i>Remove</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-funnel fs-1 d-block mb-2 text-secondary"></i>
                            No data scope constraints assigned. Roles operate with default scoping.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($scopes->hasPages())
    <div class="card-footer bg-white py-3">{{ $scopes->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const circles = @json($circles);
const industries = @json($industries);
const districts = @json($districts);

function autoSelectScopeType() {
    const roleSelect = document.getElementById('roleSelect');
    if (!roleSelect || roleSelect.selectedIndex < 0) return;

    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const scopeType = selectedOption.getAttribute('data-scope-type');
    if (scopeType) {
        const scopeTypeSelect = document.getElementById('scopeType');
        if (scopeTypeSelect) {
            scopeTypeSelect.value = scopeType;
            if (window.jQuery && jQuery(scopeTypeSelect).data('select2')) {
                jQuery(scopeTypeSelect).trigger('change.select2');
            }
            toggleScopeEntity();
        }
    }
}

function toggleScopeEntity() {
    const type = document.getElementById('scopeType').value;
    const select = document.getElementById('scopeEntity');
    const col = document.getElementById('scopeEntityCol');

    select.innerHTML = '<option value="">— Select Target Entity —</option>';

    if (type === 'global') {
        col.style.display = 'none';
        return;
    }

    col.style.display = '';
    const items = type === 'circle' ? circles : (type === 'industry' ? industries : districts);
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name;
        select.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    toggleScopeEntity();
    if (window.jQuery) {
        jQuery('#roleSelect').on('change', autoSelectScopeType);
    }
});
</script>
@endpush
