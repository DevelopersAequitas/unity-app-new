@extends('admin.layouts.app')
@section('title', 'Permission Matrix — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-shield-check me-2"></i>Role Page Permissions</h4>
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
                <span class="badge bg-primary fs-6">{{ $selectedRole?->name ?? 'None' }}</span>
            </div>
        </form>
    </div>
</div>

@if($selectedRole)
<div id="ajaxToastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<form id="permissionForm" method="POST" action="{{ route('admin.rbac.permission-matrix.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
            <h6 class="mb-0 text-dark fw-bold">
                <i class="bi bi-person-badge me-2 text-primary"></i>
                Page Permissions for <span class="text-primary">{{ $selectedRole->name }}</span>
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="selectAll">
                    <i class="bi bi-check-all me-1"></i>Select All Pages
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="deselectAll">
                    <i class="bi bi-x-lg me-1"></i>Deselect All Pages
                </button>
                <button type="submit" class="btn btn-sm btn-primary px-3 btn-save-matrix">
                    <i class="bi bi-save me-1"></i>Save Permissions
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width: 300px;" class="ps-3 py-3">Module & Page Section</th>
                            <th style="width: 200px;" class="text-center py-3">Access Granted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $module)
                            @if($module->pages->count() > 0)
                                <tr class="table-secondary module-row" data-module-id="{{ $module->id }}">
                                    <td colspan="2" class="fw-bold py-2 px-3 module-cell" style="background-color: #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fs-6 text-dark fw-bold">
                                                <i class="{{ $module->icon }} text-primary me-2"></i>{{ $module->name }}
                                            </span>
                                            <div class="d-flex gap-2 align-items-center">
                                                <button type="button" class="btn btn-xs btn-outline-primary btn-select-module" data-module-id="{{ $module->id }}">
                                                    Select All in {{ $module->name }}
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-secondary btn-deselect-module" data-module-id="{{ $module->id }}">
                                                    Deselect All
                                                </button>
                                                <button type="submit" class="btn btn-xs btn-primary btn-save-matrix">
                                                    <i class="bi bi-save me-1"></i>Save Section
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @foreach($module->pages as $page)
                                    <tr class="page-row">
                                        <td class="ps-4 py-2">
                                            <div class="fw-semibold text-dark">{{ $page->name }}</div>
                                            <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ $page->route_name }}</small>
                                        </td>
                                        <td class="text-center align-middle py-2">
                                            <input type="hidden" name="pages[{{ $page->id }}]" value="0">
                                            <div class="form-check form-switch d-inline-block">
                                                <input type="checkbox"
                                                       class="form-check-input perm-checkbox"
                                                       name="pages[{{ $page->id }}]"
                                                       value="1"
                                                       data-module-id="{{ $module->id }}"
                                                       id="page_{{ $page->id }}"
                                                       style="cursor: pointer; width: 2.4em; height: 1.2em;"
                                                       {{ isset($currentPermissions[$page->id]) ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-end py-3 border-top">
            <button type="submit" class="btn btn-primary px-4 btn-save-matrix">
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
    .btn-xs {
        padding: 0.15rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 0.25rem;
    }
    .page-row:hover {
        background-color: #f8fafc !important;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Restore scroll position fallback if present
    const savedPos = sessionStorage.getItem('matrix_scroll_pos');
    if (savedPos !== null) {
        window.scrollTo(0, parseInt(savedPos, 10));
        sessionStorage.removeItem('matrix_scroll_pos');
    }

    // Select Module Button
    document.querySelectorAll('.btn-select-module').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const moduleId = this.dataset.moduleId;
            document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`).forEach(cb => cb.checked = true);
        });
    });

    // Deselect Module Button
    document.querySelectorAll('.btn-deselect-module').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const moduleId = this.dataset.moduleId;
            document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`).forEach(cb => cb.checked = false);
        });
    });

    // Select All Pages Button
    document.getElementById('selectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
    });

    // Deselect All Pages Button
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
    });

    // Toast helper
    function showToast(message, type = 'success') {
        const container = document.getElementById('ajaxToastContainer');
        if (!container) return;

        const alertEl = document.createElement('div');
        alertEl.className = `alert alert-${type} alert-dismissible fade show shadow`;
        alertEl.role = 'alert';
        alertEl.innerHTML = `
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        container.appendChild(alertEl);
        setTimeout(() => alertEl.remove(), 4000);
    }

    // Handle AJAX form submission without scrolling or reloading
    const form = document.getElementById('permissionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Store scroll position as fallback
            sessionStorage.setItem('matrix_scroll_pos', window.scrollY.toString());

            const submitBtns = form.querySelectorAll('.btn-save-matrix');
            submitBtns.forEach(btn => {
                btn.disabled = true;
                btn.dataset.origHtml = btn.innerHTML;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...`;
            });

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Permissions updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Error updating permissions.', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to save permissions. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    if (btn.dataset.origHtml) {
                        btn.innerHTML = btn.dataset.origHtml;
                    }
                });
            });
        });
    }
});
</script>
@endpush
