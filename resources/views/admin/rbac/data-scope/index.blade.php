@extends('admin.layouts.app')
@section('title', 'Data Scope — RBAC')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Data Scope</h1>
        <p class="text-muted small mb-0">Restrict which circles / districts / industries a role can see.</p>
    </div>
</div>

<div class="mb-3">
    <form method="GET" action="{{ route('admin.rbac.data-scope.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold">Role:</label>
        <select name="role_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" {{ $selectedRole?->id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </form>
</div>

@if($selectedRole)
<div class="row g-4">
    {{-- Existing scopes --}}
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Current Scopes for {{ $selectedRole->name }}</div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Type</th><th>Value</th><th class="text-end">Remove</th></tr>
                    </thead>
                    <tbody>
                        @forelse($scopes as $scope)
                        <tr id="scope-row-{{ $scope->id }}">
                            <td><span class="badge bg-primary">{{ $scope->scope_type }}</span></td>
                            <td>{!! $scope->scope_value ? e($scope->scope_value) : '<span class="text-success fw-semibold">All of this type</span>' !!}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger delete-scope"
                                    data-id="{{ $scope->id }}"
                                    data-url="{{ route('admin.rbac.data-scope.destroy', $scope) }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No scopes assigned. Role sees nothing by default.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add new scope --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Add New Scope</div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                @endif
                <form action="{{ route('admin.rbac.data-scope.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope Type</label>
                        <select name="scope_type" class="form-select">
                            @foreach($scopeTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scope Value</label>
                        <input type="text" name="scope_value" class="form-control" placeholder="UUID — leave blank for ALL">
                        <div class="form-text">Leave blank = access to ALL records of that type.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Add Scope</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

document.querySelectorAll('.delete-scope').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('Remove this scope?')) return;

        const url  = this.dataset.url;
        const rowId = 'scope-row-' + this.dataset.id;
        const btn  = this;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error(text || 'Server error'); });
            }
            return response.json();
        })
        .then(() => {
            // Remove the row from the table without a full reload
            const row = document.getElementById(rowId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('Failed to remove scope. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash"></i>';
        });
    });
});
</script>
@endpush
@endsection
