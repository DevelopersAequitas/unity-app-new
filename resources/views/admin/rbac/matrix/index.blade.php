@extends('admin.layouts.app')
@section('title', 'Permission Matrix — RBAC')

@push('styles')
<style>
.matrix-container { max-height: calc(100vh - 200px); overflow: auto; position: relative; }
.matrix-table th, .matrix-table td { font-size: 0.8rem; padding: 7px 10px; white-space: nowrap; vertical-align: middle; }
.matrix-table thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa !important; box-shadow: 0 2px 5px rgba(0,0,0,0.08); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
.matrix-cb { width: 18px; height: 18px; cursor: pointer; }
.module-row td { background: #eef2f7; font-weight: 700; color: #334155; }
.select2-container { min-width: 240px !important; width: 240px !important; }
.select2-dropdown { min-width: 240px !important; width: auto !important; }
.select2-results__option { white-space: nowrap !important; }
.col-header-cell:hover { background-color: #e9ecef !important; }

/* Collapsible group rows */
.group-header-row td { cursor: pointer; user-select: none; transition: background 0.15s; }
.group-header-row td:hover { background: #dde6f1 !important; }
.group-chevron { display: inline-block; transition: transform 0.2s ease; margin-right: 6px; color: #64748b; }
.group-header-row.collapsed .group-chevron { transform: rotate(-90deg); }
.page-matrix-row { transition: opacity 0.15s ease; }
.page-matrix-row.row-hidden { display: none !important; }
</style>
@endpush

@section('content')
<div id="alertContainer" class="mb-3"></div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Permission Matrix</h1>
        <p class="text-muted small mb-0">Assign which actions each role can perform on each page.</p>
    </div>
    <button class="btn btn-success" id="saveMatrixBtn">
        <i class="bi bi-check-lg me-1"></i> Save Changes
    </button>
</div>

{{-- Role Selector & Quick Actions --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <form method="GET" action="{{ route('admin.rbac.matrix.index') }}" class="d-flex align-items-center gap-2 mb-0">
                <label class="fw-semibold mb-0">Role:</label>
                <select name="role_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $selectedRole?->id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                </select>
            </form>

            @if($selectedRole)
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Search input -->
                <div class="input-group input-group-sm" style="width: 220px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchMatrixInput" class="form-control border-start-0 ps-0" placeholder="Filter pages or routes...">
                </div>

                <!-- Select All Matrix / Deselect All -->
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllMatrixBtn"><i class="bi bi-check-all me-1"></i>Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllMatrixBtn"><i class="bi bi-x-circle me-1"></i>Deselect All</button>
            </div>
            @endif
        </div>
    </div>
</div>

@if($selectedRole)
<div class="card border-0 shadow-sm">
    <div class="card-body p-0 matrix-container">
        <table class="table table-bordered matrix-table mb-0" id="matrixTable">
            <thead>
                <tr>
                    <th style="min-width:240px">Page / Route</th>
                    @foreach($permissions as $perm)
                    <th class="text-center col-header-cell" data-perm="{{ $perm->id }}" style="cursor: pointer; user-select: none;" title="Click header to toggle all {{ $perm->name }} permissions">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <span class="fw-bold">{{ $perm->name }}</span>
                            <button type="button" class="btn btn-xs btn-light border py-0 px-2 toggle-col" data-perm="{{ $perm->id }}" style="font-size:0.68rem;">
                                All
                            </button>
                        </div>
                    </th>
                    @endforeach
                    <th class="text-center">Row Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pages as $moduleName => $modulePages)
                @php $groupKey = 'group_' . Str::slug($moduleName); @endphp
                <tr class="module-row group-header-row" data-group="{{ $groupKey }}">
                    <td colspan="{{ $permissions->count() + 2 }}">
                        <i class="bi bi-chevron-down group-chevron"></i>
                        <i class="bi bi-folder-fill me-1 text-primary"></i>{{ $moduleName }} ({{ count($modulePages) }} pages)
                    </td>
                </tr>
                @foreach($modulePages as $page)
                <tr class="page-matrix-row" data-group="{{ $groupKey }}" data-name="{{ strtolower($page->page_name . ' ' . $page->route_name) }}">
                    <td>
                        <span class="fw-semibold">{{ $page->page_name ?: 'Unnamed Page' }}</span>
                        <br>
                        <span class="text-muted" style="font-size:0.7rem">{{ $page->route_name }}</span>
                    </td>
                    @foreach($permissions as $perm)
                    <td class="text-center">
                        <input type="checkbox" class="matrix-cb"
                            data-page="{{ $page->id }}"
                            data-perm="{{ $perm->id }}"
                            data-perm-name="{{ strtolower($perm->name) }}"
                            {{ isset($grants[$page->id][$perm->id]) ? 'checked' : '' }}>
                    </td>
                    @endforeach
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-outline-secondary toggle-row" data-page="{{ $page->id }}" style="font-size:0.7rem;padding:2px 8px">All</button>
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
const roleId = "{{ $selectedRole?->id }}";
const saveUrl = "{{ route('admin.rbac.matrix.save') }}";
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    alertContainer.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm d-flex align-items-center justify-content-between" role="alert">
            <div>
                <i class="bi ${type === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger'} me-2 fs-5 align-middle"></i>
                <strong class="me-1">${type === 'success' ? 'Success:' : 'Error:'}</strong> ${message}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateHeaderStates() {
    document.querySelectorAll('.toggle-col').forEach(btn => {
        const permId = btn.dataset.perm;
        const boxes = document.querySelectorAll(`.matrix-cb[data-perm="${permId}"]`);
        if (boxes.length === 0) return;
        const allChecked = [...boxes].every(b => b.checked);
        const someChecked = [...boxes].some(b => b.checked);

        if (allChecked) {
            btn.classList.remove('btn-light', 'text-muted', 'btn-outline-primary');
            btn.classList.add('btn-primary', 'text-white');
            btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>✓ All';
        } else if (someChecked) {
            btn.classList.remove('btn-light', 'text-muted', 'btn-primary', 'text-white');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = '<i class="bi bi-dash-lg me-1"></i>Some';
        } else {
            btn.classList.remove('btn-primary', 'text-white', 'btn-outline-primary');
            btn.classList.add('btn-light', 'text-muted');
            btn.innerHTML = 'All';
        }
    });
}

function updateRowStates() {
    document.querySelectorAll('.toggle-row').forEach(btn => {
        const pageId = btn.dataset.page;
        const boxes = document.querySelectorAll(`.matrix-cb[data-page="${pageId}"]`);
        if (boxes.length === 0) return;
        const allChecked = [...boxes].every(b => b.checked);
        const someChecked = [...boxes].some(b => b.checked);

        if (allChecked) {
            btn.classList.remove('btn-outline-secondary', 'btn-outline-primary');
            btn.classList.add('btn-primary', 'text-white');
            btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>✓ All';
        } else if (someChecked) {
            btn.classList.remove('btn-outline-secondary', 'btn-primary', 'text-white');
            btn.classList.add('btn-outline-primary');
            btn.innerHTML = '<i class="bi bi-dash-lg me-1"></i>Some';
        } else {
            btn.classList.remove('btn-primary', 'text-white', 'btn-outline-primary');
            btn.classList.add('btn-outline-secondary');
            btn.innerHTML = 'All';
        }
    });
}

// Collapsible group rows
document.querySelectorAll('.group-header-row').forEach(header => {
    header.addEventListener('click', function() {
        const group = this.dataset.group;
        const isCollapsed = this.classList.toggle('collapsed');
        document.querySelectorAll(`.page-matrix-row[data-group="${group}"]`).forEach(row => {
            if (isCollapsed) {
                row.classList.add('row-hidden');
            } else {
                row.classList.remove('row-hidden');
            }
        });
    });
});

// Live search filter
document.getElementById('searchMatrixInput')?.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();

    // First expand all groups when searching
    if (term) {
        document.querySelectorAll('.group-header-row').forEach(h => {
            h.classList.remove('collapsed');
        });
        document.querySelectorAll('.page-matrix-row').forEach(r => r.classList.remove('row-hidden'));
    }

    document.querySelectorAll('.page-matrix-row').forEach(row => {
        const name = row.dataset.name;
        if (!term || name.includes(term)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });

    // Update group header rows visibility
    document.querySelectorAll('.group-header-row').forEach(header => {
        const group = header.dataset.group;
        const rows = document.querySelectorAll(`.page-matrix-row[data-group="${group}"]`);
        let hasVisibleChild = [...rows].some(r => !r.classList.contains('d-none'));
        if (hasVisibleChild) {
            header.classList.remove('d-none');
        } else {
            header.classList.add('d-none');
        }
    });
});

// Toggle whole row
document.querySelectorAll('.toggle-row').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const pageId = this.dataset.page;
        const boxes = document.querySelectorAll(`.matrix-cb[data-page="${pageId}"]`);
        const allChecked = [...boxes].every(b => b.checked);
        boxes.forEach(b => b.checked = !allChecked);
        updateHeaderStates();
        updateRowStates();
    });
});

// Toggle column by header cell click (but not when clicking the toggle-col button itself)
document.querySelectorAll('.col-header-cell').forEach(th => {
    th.addEventListener('click', function(e) {
        // Prevent double-firing when the inner button is clicked
        if (e.target.closest('.toggle-col')) return;
        const permId = this.dataset.perm;
        const boxes = document.querySelectorAll(`.matrix-cb[data-perm="${permId}"]:not(.row-hidden .matrix-cb)`);
        if (boxes.length === 0) return;
        const allChecked = [...boxes].every(b => b.checked);
        boxes.forEach(b => b.checked = !allChecked);
        updateHeaderStates();
        updateRowStates();
    });
});

// Toggle column via the inner "All/Some" button
document.querySelectorAll('.toggle-col').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const permId = this.dataset.perm;
        const boxes = document.querySelectorAll(`.matrix-cb[data-perm="${permId}"]`);
        if (boxes.length === 0) return;
        const allChecked = [...boxes].every(b => b.checked);
        boxes.forEach(b => b.checked = !allChecked);
        updateHeaderStates();
        updateRowStates();
    });
});

document.querySelectorAll('.matrix-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        updateHeaderStates();
        updateRowStates();
    });
});

// Quick action buttons
document.getElementById('selectAllMatrixBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.page-matrix-row:not(.d-none) .matrix-cb').forEach(cb => cb.checked = true);
    updateHeaderStates();
    updateRowStates();
});

document.getElementById('deselectAllMatrixBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.page-matrix-row:not(.d-none) .matrix-cb').forEach(cb => cb.checked = false);
    updateHeaderStates();
    updateRowStates();
});

// Initialize header & row states on page load
updateHeaderStates();
updateRowStates();

// Save permissions matrix
document.getElementById('saveMatrixBtn')?.addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...`;

    const grants = [];
    document.querySelectorAll('.matrix-cb:checked').forEach(cb => {
        grants.push({ page_id: cb.dataset.page, permission_id: cb.dataset.perm });
    });

    fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ role_id: roleId, grants })
    })
    .then(r => r.json())
    .then(data => {
        showAlert(data.message || 'Permissions matrix saved successfully!', 'success');
        setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => showAlert('Error saving permissions matrix. Please try again.', 'danger'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
@endpush
@endsection
