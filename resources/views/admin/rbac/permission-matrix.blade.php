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

{{-- Role Selector & Matrix Search Header --}}
<div class="card mb-4 border-0 shadow-sm rounded-3 bg-white">
    <div class="card-body p-3">
        <form method="GET" class="row align-items-center g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-muted mb-1">SELECT ROLE TO EDIT PERMISSIONS</label>
                <select name="role_id" class="form-select rounded-3 shadow-xs" onchange="this.form.submit()">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $selectedRole?->id === $role->id ? 'selected' : '' }}>
                            {{ $role->name }} ({{ $role->key }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small text-muted mb-1">FILTER PAGES OR ROUTES LIVE</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="matrixSearch" class="form-control border-start-0" placeholder="Type page name or route slug..." onkeyup="filterMatrixPages()">
                </div>
            </div>
            <div class="col-md-3 text-end d-flex align-items-center justify-content-end gap-2">
                <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill" id="totalActivePagesBadge">
                    <i class="bi bi-shield-check me-1"></i> Active Role: {{ $selectedRole?->name ?? 'None' }}
                </span>
            </div>
        </form>
    </div>
</div>

@if($selectedRole)
<div id="ajaxToastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

<form id="permissionForm" method="POST" action="{{ route('admin.rbac.permission-matrix.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 border-bottom gap-2">
            <h6 class="mb-0 text-dark fw-bold">
                <i class="bi bi-person-badge me-2 text-primary"></i>
                Page Permissions Matrix for <span class="text-primary">{{ $selectedRole->name }}</span>
            </h6>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllSections">
                    <i class="bi bi-arrows-expand me-1"></i>Expand All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllSections">
                    <i class="bi bi-arrows-collapse me-1"></i>Collapse All
                </button>
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
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;" id="matrixTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width: 320px;" class="ps-3 py-3">Module & Page Section</th>
                            <th style="width: 200px;" class="text-center py-3">Access Granted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $module)
                            @if($module->pages->count() > 0)
                                <tr class="table-secondary module-row module-header-toggle" data-module-id="{{ $module->id }}" style="cursor: pointer;" title="Click to expand / collapse module section">
                                    <td colspan="2" class="fw-bold py-2 px-3 module-cell" style="background-color: #f1f5f9;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-chevron-right module-toggle-icon text-muted me-1 fs-6" data-module-id="{{ $module->id }}"></i>
                                                <span class="fs-6 text-dark fw-bold">
                                                    <i class="{{ $module->icon ?: 'bi-grid-fill' }} text-primary me-2"></i>{{ $module->name }}
                                                </span>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill module-counter" data-module-id="{{ $module->id }}">
                                                    0 / {{ $module->pages->count() }} Pages
                                                </span>
                                            </div>
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
                                    <tr class="page-row" data-module-id="{{ $module->id }}" style="display: none;">
                                        <td class="ps-5 py-2">
                                            <div class="fw-semibold text-dark page-name">{{ $page->name }}</div>
                                            <small class="text-muted font-monospace page-route" style="font-size: 0.75rem;">{{ $page->route_name }}</small>
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
    </div>
</form>
@else
    <div class="alert alert-info rounded-3">Select a role to manage its permissions.</div>
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
    .module-header-toggle:hover {
        background-color: #e2e8f0 !important;
    }
</style>
@endpush

@push('scripts')
<script>
function toggleModuleSection(moduleId, forceState = null) {
    const icon = document.querySelector(`.module-toggle-icon[data-module-id="${moduleId}"]`);
    const rows = document.querySelectorAll(`.page-row[data-module-id="${moduleId}"]`);
    
    let isCurrentlyHidden = false;
    if (rows.length > 0) {
        isCurrentlyHidden = (rows[0].style.display === 'none');
    }
    
    const shouldHide = (forceState !== null) ? !forceState : !isCurrentlyHidden;
    
    rows.forEach(r => {
        r.style.display = shouldHide ? 'none' : '';
    });
    
    if (icon) {
        icon.className = shouldHide 
            ? 'bi bi-chevron-right module-toggle-icon text-muted me-1 fs-6' 
            : 'bi bi-chevron-down module-toggle-icon text-muted me-1 fs-6';
    }
}

function updateModuleCounters() {
    document.querySelectorAll('.module-counter').forEach(counter => {
        const moduleId = counter.dataset.moduleId;
        const total = document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`).length;
        const checked = document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]:checked`).length;
        counter.textContent = `${checked} / ${total} Pages Granted`;
        if (checked === total && total > 0) {
            counter.className = 'badge bg-success rounded-pill module-counter';
        } else if (checked > 0) {
            counter.className = 'badge bg-primary rounded-pill module-counter';
        } else {
            counter.className = 'badge bg-secondary-subtle text-secondary rounded-pill module-counter';
        }
    });
}

function filterMatrixPages() {
    const q = (document.getElementById('matrixSearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.module-row').forEach(modRow => {
        const moduleId = modRow.dataset.moduleId;
        const pageRows = document.querySelectorAll(`.page-row[data-module-id="${moduleId}"]`);
        let hasMatchingPage = false;

        pageRows.forEach(row => {
            const name = row.querySelector('.page-name')?.textContent.toLowerCase() || '';
            const route = row.querySelector('.page-route')?.textContent.toLowerCase() || '';
            if (!q || name.includes(q) || route.includes(q)) {
                row.style.display = '';
                if (q) hasMatchingPage = true;
            } else {
                row.style.display = 'none';
            }
        });

        if (q && hasMatchingPage) {
            toggleModuleSection(moduleId, true);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateModuleCounters();

    // Attach checkbox listener for live counter updates
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.addEventListener('change', updateModuleCounters);
    });

    // Module Header Click to Collapse/Expand
    document.querySelectorAll('.module-header-toggle').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.closest('button') || e.target.closest('.form-check') || e.target.closest('input')) {
                return;
            }
            const moduleId = this.dataset.moduleId;
            toggleModuleSection(moduleId);
        });
    });

    // Expand All / Collapse All Buttons
    document.getElementById('expandAllSections')?.addEventListener('click', function() {
        document.querySelectorAll('.module-row').forEach(row => {
            toggleModuleSection(row.dataset.moduleId, true);
        });
    });

    document.getElementById('collapseAllSections')?.addEventListener('click', function() {
        document.querySelectorAll('.module-row').forEach(row => {
            toggleModuleSection(row.dataset.moduleId, false);
        });
    });

    // Select Module Button
    document.querySelectorAll('.btn-select-module').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const moduleId = this.dataset.moduleId;
            document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`).forEach(cb => cb.checked = true);
            updateModuleCounters();
        });
    });

    // Deselect Module Button
    document.querySelectorAll('.btn-deselect-module').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const moduleId = this.dataset.moduleId;
            document.querySelectorAll(`.perm-checkbox[data-module-id="${moduleId}"]`).forEach(cb => cb.checked = false);
            updateModuleCounters();
        });
    });

    // Select All Pages Button
    document.getElementById('selectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
        updateModuleCounters();
    });

    // Deselect All Pages Button
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
        updateModuleCounters();
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
                    updateModuleCounters();
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
