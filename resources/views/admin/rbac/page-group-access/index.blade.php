@extends('admin.layouts.app')
@section('title', 'Page Group Access — RBAC')

@section('content')
<div id="alertContainer" class="mb-3"></div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Page Group Access</h1>
        <p class="text-muted small mb-0">Assign page bundles to a role in one click.</p>
    </div>
    <button class="btn btn-success" id="saveGroupAccessBtn">
        <i class="bi bi-check-lg me-1"></i> Save Changes
    </button>
</div>

<div class="mb-3">
    <form method="GET" action="{{ route('admin.rbac.page-group-access.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold">Role:</label>
        <select name="role_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ $selectedRole?->id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px">
    <div class="card-body">
        @forelse($groups as $group)
        <div class="d-flex align-items-start justify-content-between border-bottom py-2">
            <div>
                <div class="fw-semibold">{{ $group->name }}</div>
                <div class="text-muted small"><i class="bi bi-file-earmark me-1"></i>{{ $group->pages_count }} pages</div>
            </div>
            <div class="form-check form-switch mb-0 mt-1">
                <input type="checkbox" class="form-check-input group-toggle" role="switch"
                    id="grp_{{ $group->id }}" data-group="{{ $group->id }}"
                    {{ in_array($group->id, $assignedGroupIds) ? 'checked' : '' }}>
            </div>
        </div>
        @empty
        <p class="text-muted text-center py-3">No page groups found. <a href="{{ route('admin.rbac.page-groups.create') }}">Create one first</a>.</p>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
const roleId   = "{{ $selectedRole?->id }}";
const saveUrl  = "{{ route('admin.rbac.page-group-access.save') }}";
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

document.getElementById('saveGroupAccessBtn')?.addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...`;

    const groupIds = [...document.querySelectorAll('.group-toggle:checked')].map(cb => cb.dataset.group);
    fetch(saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ role_id: roleId, group_ids: groupIds })
    })
    .then(r => r.json())
    .then(data => {
        showAlert(data.message || 'Page group access saved successfully!', 'success');
        setTimeout(() => window.location.reload(), 1200);
    })
    .catch(() => {
        showAlert('Error saving page group access. Please try again.', 'danger');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
@endpush
@endsection
