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
        <div class="card-header d-flex justify-content-between align-items-center py-3">
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
                            <th style="min-width: 250px; position: sticky; left: 0; z-index: 2; background: #212529;" title="Click any page cell or module header to toggle its row">
                                Page <span class="text-muted small fw-normal">(Click cell to toggle row)</span>
                            </th>
                            @foreach($permissions as $perm)
                                <th class="text-center col-header-cell"
                                    style="min-width: 85px; cursor: pointer;"
                                    data-perm-id="{{ $perm->id }}"
                                    title="Click column box to select / deselect all {{ $perm->name }}">
                                    <div class="d-flex flex-column align-items-center py-1">
                                        <span class="fw-bold">{{ $perm->name }}</span>
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
                                <tr class="table-secondary module-row" data-module-id="{{ $module->id }}">
                                    <td colspan="{{ $permissions->count() + 1 }}" class="fw-bold py-2 module-cell"
                                        style="cursor: pointer;"
                                        data-module-id="{{ $module->id }}"
                                        title="Click to toggle all permissions under {{ $module->name }}">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="{{ $module->icon }} me-1"></i>{{ $module->name }}
                                            </span>
                                            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;">Click to toggle module</span>
                                        </div>
                                    </td>
                                </tr>
                                @foreach($module->pages as $page)
                                    <tr class="page-row" data-page-id="{{ $page->id }}" data-module-id="{{ $module->id }}">
                                        <td style="position: sticky; left: 0; z-index: 1; background: #fff; cursor: pointer;"
                                            class="ps-3 page-cell"
                                            data-page-id="{{ $page->id }}"
                                            title="Click page cell to toggle all permissions for {{ $page->name }}">
                                            <span class="fw-semibold text-primary-hover">{{ $page->name }}</span>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">{{ $page->route_name }}</small>
                                        </td>
                                        @foreach($permissions as $perm)
                                            <td class="text-center align-middle">
                                                <input type="hidden" name="permissions[{{ $page->id }}][{{ $perm->id }}]" value="0">
                                                <input type="checkbox"
                                                       class="form-check-input perm-checkbox"
                                                       name="permissions[{{ $page->id }}][{{ $perm->id }}]"
                                                       value="1"
                                                       data-page-id="{{ $page->id }}"
                                                       data-module-id="{{ $module->id }}"
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
        <div class="card-footer text-end py-3">
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

@push('styles')
<style>
    .page-cell {
        transition: background-color 0.15s ease-in-out;
    }
    .page-cell:hover {
        background-color: #eef5ff !important;
    }
    .module-cell {
        transition: background-color 0.15s ease-in-out;
    }
    .module-cell:hover {
        background-color: #e2e6ea !important;
    }
    .col-header-cell {
        transition: background-color 0.15s ease-in-out;
        user-select: none;
    }
    .col-header-cell:hover {
        background-color: #343a40 !important;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Click column header cell box (VIEW, CREATE, EDIT, DELETE, etc.) -> toggle all checkboxes in that column
    document.querySelectorAll('.col-header-cell').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;

            const permId = this.dataset.permId;
            const colToggle = this.querySelector('.col-toggle');
            const colCbs = document.querySelectorAll(`.perm-checkbox[data-perm-id="${permId}"]`);
            if (colCbs.length === 0) return;

            const allChecked = Array.from(colCbs).every(cb => cb.checked);
            const newState = !allChecked;

            colCbs.forEach(cb => cb.checked = newState);
            if (colToggle) colToggle.checked = newState;
        });
    });

    // Column Toggle Checkbox direct change
    document.querySelectorAll('.col-toggle').forEach(toggle => {
        toggle.addEventListener('change', function(e) {
            const permId = this.dataset.permId;
            const checked = this.checked;
            document.querySelectorAll(`.perm-checkbox[data-perm-id="${permId}"]`).forEach(cb => {
                cb.checked = checked;
            });
        });
    });

    // Click page cell (the red box area) -> toggle all checkboxes for that row
    document.querySelectorAll('.page-cell').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;

            const pageId = this.dataset.pageId;
            const tr = this.closest('tr');
            if (!tr) return;

            const rowCbs = tr.querySelectorAll('.perm-checkbox');
            if (rowCbs.length === 0) return;

            const allChecked = Array.from(rowCbs).every(cb => cb.checked);
            rowCbs.forEach(cb => cb.checked = !allChecked);
        });
    });

    // Click module header cell -> toggle all checkboxes for all pages under that module
    document.querySelectorAll('.module-cell').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;

            const moduleId = this.dataset.moduleId;
            const moduleCbs = document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`);
            if (moduleCbs.length === 0) return;

            const allChecked = Array.from(moduleCbs).every(cb => cb.checked);
            moduleCbs.forEach(cb => cb.checked = !allChecked);
        });
    });

    // Select All Button
    document.getElementById('selectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
        document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = true);
    });

    // Deselect All Button
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.col-toggle').forEach(cb => cb.checked = false);
    });
});
</script>
@endpush
