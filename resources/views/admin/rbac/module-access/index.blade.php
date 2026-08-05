@extends('admin.layouts.app')
@section('title', 'Module Access — RBAC')

@push('styles')
<style>
.select2-container { min-width: 240px !important; width: 240px !important; }
.select2-dropdown { min-width: 240px !important; width: auto !important; }
.select2-results__option { white-space: nowrap !important; }
</style>
@endpush

@section('content')
<div id="alertContainer" class="mb-3"></div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Module Access (Sidebar Visibility)</h1>
        <p class="text-muted small mb-0">Control which sidebar menus are visible for each role.</p>
    </div>
    <button class="btn btn-success" id="saveModuleAccessBtn">
        <i class="bi bi-check-lg me-1"></i> Save Changes
    </button>
</div>

<div class="mb-3">
    <form method="GET" action="{{ route('admin.rbac.module-access.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold">Role:</label>
        <select name="role_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ $selectedRole?->id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 border-bottom pb-2 gap-2">
            <p class="text-muted small mb-0">Check the modules this role can see in the sidebar. Unchecked = hidden.</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Search input -->
                <div class="input-group input-group-sm" style="width: 190px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchModulesInput" class="form-control border-start-0 ps-0" placeholder="Search modules...">
                </div>

                <!-- Sort select -->
                <div class="d-flex align-items-center gap-1">
                    <i class="bi bi-sort-alpha-down text-muted"></i>
                    <select id="sortModulesSelect" class="form-select form-select-sm" style="width: 110px;">
                        <option value="default">Default</option>
                        <option value="asc" selected>A – Z</option>
                        <option value="desc">Z – A</option>
                    </select>
                </div>

                <!-- Quick actions -->
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn"><i class="bi bi-check-all me-1"></i>Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn"><i class="bi bi-x-circle me-1"></i>Deselect All</button>
            </div>
        </div>

        <div class="row g-3" id="modulesGridContainer">
            @foreach($modules as $module)
            <div class="col-md-6 module-card-item" data-name="{{ strtolower($module->name) }}" data-index="{{ $loop->index }}">
                <div class="d-flex align-items-center justify-content-between border rounded p-2 px-3 bg-white h-100 shadow-sm">
                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                        <i class="bi {{ $module->icon }} fs-5 text-primary flex-shrink-0"></i>
                        <span class="fw-semibold text-truncate module-title-name">{{ $module->name }}</span>
                    </div>
                    <div class="form-check form-switch mb-0 flex-shrink-0">
                        <input type="checkbox" class="form-check-input module-toggle" role="switch"
                            id="mod_{{ $module->id }}" data-module="{{ $module->id }}"
                            {{ ($visibility[$module->id] ?? false) ? 'checked' : '' }}>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
const roleId   = "{{ $selectedRole?->id }}";
const saveUrl  = "{{ route('admin.rbac.module-access.save') }}";
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const gridContainer = document.getElementById('modulesGridContainer');
const sortSelect = document.getElementById('sortModulesSelect');
const searchInput = document.getElementById('searchModulesInput');

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

// Sorting helper function
function sortModules(val) {
    if (!gridContainer) return;
    const items = Array.from(gridContainer.querySelectorAll('.module-card-item'));

    items.sort((a, b) => {
        if (val === 'asc') {
            return a.dataset.name.localeCompare(b.dataset.name);
        } else if (val === 'desc') {
            return b.dataset.name.localeCompare(a.dataset.name);
        } else {
            return parseInt(a.dataset.index, 10) - parseInt(b.dataset.index, 10);
        }
    });

    items.forEach(item => gridContainer.appendChild(item));
}

// Sorting handler (Default, A-Z, Z-A)
sortSelect?.addEventListener('change', function() {
    sortModules(this.value);
});

// Trigger default sort on load
if (sortSelect) {
    sortModules(sortSelect.value);
}

// Live search filter handler
searchInput?.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    const items = gridContainer.querySelectorAll('.module-card-item');

    items.forEach(item => {
        const name = item.dataset.name;
        if (!term || name.includes(term)) {
            item.classList.remove('d-none');
        } else {
            item.classList.add('d-none');
        }
    });
});

document.getElementById('selectAllBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.module-card-item:not(.d-none) .module-toggle').forEach(cb => cb.checked = true);
});

document.getElementById('deselectAllBtn')?.addEventListener('click', () => {
    document.querySelectorAll('.module-card-item:not(.d-none) .module-toggle').forEach(cb => cb.checked = false);
});

document.getElementById('saveModuleAccessBtn')?.addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...`;

    const visibleIds = [...document.querySelectorAll('.module-toggle:checked')].map(cb => cb.dataset.module);
    fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ role_id: roleId, visible_module_ids: visibleIds })
    })
    .then(r => r.json())
    .then(data => {
        showAlert(data.message || 'Module visibility saved successfully!', 'success');
        setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
        showAlert('Error saving module access. Please try again.', 'danger');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
@endpush
@endsection
