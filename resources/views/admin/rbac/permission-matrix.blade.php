@extends('admin.layouts.app')
@section('title', 'Permission Matrix — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Role Permission Matrix</h4>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Role Selector --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row align-items-center g-3">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-0">Select Role:</label>
            </div>
            <div class="col-md-4">
                <select name="role_id" class="form-select" onchange="this.form.submit()">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $selectedRole?->id === $role->id ? 'selected' : '' }}>
                            {{ $role->name }} ({{ $role->key }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <span class="badge bg-primary">{{ $selectedRole?->name ?? 'None' }}</span>
            </div>
        </form>
    </div>
</div>

@if($selectedRole)
<form method="POST" action="{{ route('admin.rbac.permission-matrix.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bi bi-shield-check me-1"></i>
                Permissions for <strong>{{ $selectedRole->name }}</strong>
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="selectAll">
                    <i class="bi bi-check-all me-1"></i>Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="deselectAll">
                    <i class="bi bi-x-lg me-1"></i>Deselect All
                </button>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-save me-1"></i>Save Permissions
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width: 250px; position: sticky; left: 0; z-index: 2; background: #212529;">Page</th>
                            @foreach($permissions as $perm)
                                <th class="text-center" style="min-width: 80px;">
                                    <div class="d-flex flex-column align-items-center">
                                        <span>{{ $perm->name }}</span>
                                        <input type="checkbox" class="form-check-input mt-1 col-toggle"
                                               data-perm-id="{{ $perm->id }}"
                                               title="Toggle all {{ $perm->name }}">
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $module)
                            @if($module->pages->count() > 0)
                                <tr class="table-secondary">
                                    <td colspan="{{ $permissions->count() + 1 }}" class="fw-bold">
                                        <i class="{{ $module->icon }} me-1"></i>{{ $module->name }}
                                    </td>
                                </tr>
                                @foreach($module->pages as $page)
                                    <tr>
                                        <td style="position: sticky; left: 0; z-index: 1; background: #fff;" class="ps-4">
                                            {{ $page->name }}
                                            <small class="text-muted d-block">{{ $page->route_name }}</small>
                                        </td>
                                        @foreach($permissions as $perm)
                                            <td class="text-center">
                                                <input type="hidden" name="permissions[{{ $page->id }}][{{ $perm->id }}]" value="0">
                                                <input type="checkbox"
                                                       class="form-check-input perm-checkbox"
                                                       name="permissions[{{ $page->id }}][{{ $perm->id }}]"
                                                       value="1"
                                                       data-perm-id="{{ $perm->id }}"
                                                       {{ isset($currentPermissions[$page->id][$perm->id]) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Permissions
            </button>
        </div>
    </div>
</form>
@else
    <div class="alert alert-info">Select a role to manage its permissions.</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All
    document.getElementById('selectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
        document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = true);
    });

    // Deselect All
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = false);
    });

    // Column Toggle (toggle all checkboxes for a specific permission)
    document.querySelectorAll('.col-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const permId = this.dataset.permId;
            const checked = this.checked;
            document.querySelectorAll(`.perm-checkbox[data-perm-id="${permId}"]`).forEach(cb => {
                cb.checked = checked;
            });
        });
    });
});
</script>
@endpush
