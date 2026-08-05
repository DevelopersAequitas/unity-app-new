@extends('admin.layouts.app')
@section('title', 'Module Access — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i>Role Module Access (Sidebar Visibility)</h4>
    @include('admin.rbac.partials.header_nav')
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

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
        </form>
    </div>
</div>

@if($selectedRole)
<form method="POST" action="{{ route('admin.rbac.module-access.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $selectedRole->id }}">

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Sidebar Modules for <strong>{{ $selectedRole->name }}</strong></h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($modules as $module)
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="modules[{{ $module->id }}]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   name="modules[{{ $module->id }}]" value="1"
                                   id="module_{{ $module->id }}"
                                   {{ ($currentAccess[$module->id] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="module_{{ $module->id }}">
                                <i class="{{ $module->icon }} me-1"></i>{{ $module->name }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Module Access
            </button>
        </div>
    </div>
</form>
@endif
@endsection
