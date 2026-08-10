@extends('admin.layouts.app')
@section('title', 'Data Scope — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-funnel me-2"></i>Data Scope Assignments</h4>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- New Scope Form --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Add Data Scope</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.rbac.data-scope.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select" id="roleSelect" onchange="autoSelectScopeType()" required>
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
                            <option value="{{ $role->id }}" data-scope-type="{{ $defaultScope }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Scope Type</label>
                    <select name="scope_type" class="form-select" id="scopeType" onchange="toggleScopeEntity()">
                        <option value="global">Global</option>
                        <option value="circle">Circle</option>
                        <option value="district">District</option>
                        <option value="industry">Industry</option>
                    </select>
                </div>
                <div class="col-md-4" id="scopeEntityCol">
                    <label class="form-label">Scope Entity</label>
                    <select name="scope_id" class="form-select" id="scopeEntity">
                        <option value="">— Select —</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger mt-2">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
        </form>
    </div>
</div>

{{-- Existing Scopes --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Role</th>
                    <th>Scope Type</th>
                    <th>Scope ID</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scopes as $scope)
                <tr>
                    <td>{{ $scope->role?->name ?? '—' }}</td>
                    <td><span class="badge bg-info">{{ $scope->scope_type }}</span></td>
                    <td><code>{{ $scope->scope_id ?? 'ALL' }}</code></td>
                    <td>{{ $scope->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('admin.rbac.data-scope.destroy', $scope->id) }}" class="d-inline" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">No data scope assignments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($scopes->hasPages())
    <div class="card-footer">{{ $scopes->links() }}</div>
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

    select.innerHTML = '<option value="">— Select —</option>';

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
