@extends('admin.layouts.app')
@section('title', 'Sidebar Module Access — Dynamic RBAC')

@php
    if (!function_exists('getModuleDisplayIcon')) {
        function getModuleDisplayIcon($module) {
            $icon = $module->icon;
            if (!empty($icon) && $icon !== 'bi-box' && str_starts_with($icon, 'bi-')) {
                return $icon;
            }

            $slug = strtolower($module->slug ?? '');
            $name = strtolower($module->name ?? '');

            if (str_contains($slug, 'circle') || str_contains($name, 'circle')) return 'bi-diagram-3-fill';
            if (str_contains($slug, 'event') || str_contains($name, 'event')) return 'bi-calendar-event-fill';
            if (str_contains($slug, 'coin') || str_contains($name, 'coin')) return 'bi-coin';
            if (str_contains($slug, 'impact') || str_contains($name, 'impact')) return 'bi-heart-pulse-fill';
            if (str_contains($slug, 'notification') || str_contains($name, 'notification') || str_contains($name, 'email')) return 'bi-bell-fill';
            if (str_contains($slug, 'pending') || str_contains($name, 'pending') || str_contains($slug, 'request')) return 'bi-hourglass-split';
            if (str_contains($slug, 'referral') || str_contains($name, 'referral') || str_contains($name, 'report')) return 'bi-file-earmark-bar-graph-fill';
            if (str_contains($slug, 'content') || str_contains($name, 'content') || str_contains($slug, 'post') || str_contains($name, 'post')) return 'bi-card-text';
            if (str_contains($slug, 'lead') || str_contains($name, 'lead')) return 'bi-inbox-fill';
            if (str_contains($slug, 'industr') || str_contains($name, 'industr')) return 'bi-diagram-2-fill';
            if (str_contains($slug, 'setting') || str_contains($name, 'setting')) return 'bi-gear-fill';
            if (str_contains($slug, 'role') || str_contains($name, 'role') || str_contains($slug, 'rbac')) return 'bi-shield-lock-fill';
            if (str_contains($slug, 'brand') || str_contains($name, 'partner')) return 'bi-briefcase-fill';
            if (str_contains($slug, 'tutorial') || str_contains($name, 'tutorial')) return 'bi-journal-bookmark-fill';
            if (str_contains($slug, 'member') || str_contains($slug, 'peer') || str_contains($name, 'peer')) return 'bi-people-fill';
            if (str_contains($slug, 'dashboard') || str_contains($name, 'dashboard')) return 'bi-speedometer2';
            if (str_contains($slug, 'activity') || str_contains($name, 'activity')) return 'bi-activity';

            return 'bi-grid-fill';
        }
    }
@endphp

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-eye-fill text-primary me-2"></i>Sidebar Module Visibility</h4>
        <p class="text-muted small mb-0">Control which main modules appear in the admin navigation sidebar for each role.</p>
    </div>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div id="ajaxToastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

{{-- Role Selector & Live Filter Bar --}}
<div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row align-items-center g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold small text-muted mb-1">SELECT ROLE TO EDIT</label>
                <select name="role_id" class="form-select rounded-3 shadow-xs" onchange="this.form.submit()">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $selectedRole?->id === $role->id ? 'selected' : '' }}>
                            {{ $role->name }} ({{ $role->key }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small text-muted mb-1">LIVE FILTER MODULES</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="moduleSearch" class="form-control border-start-0" placeholder="Type module name..." onkeyup="filterModules()">
                </div>
            </div>
            <div class="col-md-3 text-end">
                <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">
                    <i class="bi bi-shield-lock me-1"></i> {{ $selectedRole?->name ?? 'None' }}
                </span>
            </div>
        </form>
    </div>
</div>

@if($selectedRole)
<form id="moduleAccessForm" method="POST" action="{{ route('admin.rbac.module-access.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 border-bottom gap-2">
            <h6 class="mb-0 text-dark fw-bold">
                <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>
                Modules for <span class="text-primary">{{ $selectedRole->name }}</span>
            </h6>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="enableAllModules">
                    <i class="bi bi-check-all me-1"></i>Visible All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="disableAllModules">
                    <i class="bi bi-x-lg me-1"></i>Hide All
                </button>
                <button type="submit" class="btn btn-sm btn-primary px-3 btn-save-modules">
                    <i class="bi bi-save me-1"></i>Save Visibility
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-3" id="moduleGrid">
                @foreach($modules as $module)
                    @php
                        $isVisible = ($currentAccess[$module->id] ?? false);
                        $displayIcon = getModuleDisplayIcon($module);
                    @endphp
                    <div class="col-md-6 col-lg-4 module-card-col">
                        <div class="card h-100 border rounded-3 p-3 transition-all module-card {{ $isVisible ? 'border-primary bg-primary-subtle bg-opacity-10' : 'bg-light' }}">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="p-3 rounded-3 {{ $isVisible ? 'bg-primary text-white' : 'bg-secondary-subtle text-secondary' }} fs-4">
                                        <i class="{{ $displayIcon }}"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-1 module-title text-dark">{{ $module->name }}</h6>
                                        <small class="text-muted font-monospace d-block mb-1" style="font-size: 0.75rem;">/{{ $module->slug }}</small>
                                        <span class="badge {{ $isVisible ? 'bg-success' : 'bg-secondary' }} module-status-badge">
                                            {{ $isVisible ? 'Visible in Sidebar' : 'Hidden from Sidebar' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="form-check form-switch fs-4 mb-0 ms-2">
                                    <input type="hidden" name="modules[{{ $module->id }}]" value="0">
                                    <input class="form-check-input module-switch"
                                           type="checkbox"
                                           name="modules[{{ $module->id }}]"
                                           value="1"
                                           id="module_{{ $module->id }}"
                                           style="cursor: pointer;"
                                           {{ $isVisible ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
function filterModules() {
    const q = (document.getElementById('moduleSearch')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.module-card-col').forEach(col => {
        const title = col.querySelector('.module-title')?.textContent.toLowerCase() || '';
        if (!q || title.includes(q)) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Dynamic switch UI card styling update
    document.querySelectorAll('.module-switch').forEach(sw => {
        sw.addEventListener('change', function() {
            const card = this.closest('.module-card');
            const badge = card.querySelector('.module-status-badge');
            const iconWrapper = card.querySelector('.fs-4');
            if (this.checked) {
                card.classList.add('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
                card.classList.remove('bg-light');
                badge.className = 'badge bg-success module-status-badge';
                badge.textContent = 'Visible in Sidebar';
                if (iconWrapper) iconWrapper.className = 'p-3 rounded-3 bg-primary text-white fs-4';
            } else {
                card.classList.remove('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
                card.classList.add('bg-light');
                badge.className = 'badge bg-secondary module-status-badge';
                badge.textContent = 'Hidden from Sidebar';
                if (iconWrapper) iconWrapper.className = 'p-3 rounded-3 bg-secondary-subtle text-secondary fs-4';
            }
        });
    });

    document.getElementById('enableAllModules')?.addEventListener('click', function() {
        document.querySelectorAll('.module-switch').forEach(sw => {
            sw.checked = true;
            sw.dispatchEvent(new Event('change'));
        });
    });

    document.getElementById('disableAllModules')?.addEventListener('click', function() {
        document.querySelectorAll('.module-switch').forEach(sw => {
            sw.checked = false;
            sw.dispatchEvent(new Event('change'));
        });
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

    // AJAX form submission
    const form = document.getElementById('moduleAccessForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtns = form.querySelectorAll('.btn-save-modules');
            submitBtns.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Saving...`;
            });

            const formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Module access updated successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to update module access.', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error saving module access.', 'danger');
            })
            .finally(() => {
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-save me-1"></i>Save Visibility`;
                });
            });
        });
    }
});
</script>
@endpush
