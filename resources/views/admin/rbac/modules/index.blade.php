@extends('admin.layouts.app')
@section('title', 'Modules — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-box me-2"></i>Admin Modules</h4>
    <div class="d-flex gap-2 align-items-center">
        @include('admin.rbac.partials.header_nav')
        <a href="{{ route('admin.rbac.modules.create') }}" class="btn btn-primary btn-sm ms-2"><i class="bi bi-plus-lg me-1"></i>Add Module</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="50">#</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Icon</th>
                    <th class="text-center">Pages</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($modules as $module)
                <tr>
                    <td>{{ $module->sort_order }}</td>
                    <td><i class="{{ $module->icon }} me-1"></i><strong>{{ $module->name }}</strong></td>
                    <td><code>{{ $module->slug }}</code></td>
                    <td><code>{{ $module->icon }}</code></td>
                    <td class="text-center"><span class="badge bg-secondary">{{ $module->pages_count }}</span></td>
                    <td class="text-center">
                        @if($module->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.rbac.pages.index', ['module' => $module->id]) }}" class="btn btn-outline-info btn-sm"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.rbac.modules.edit', $module->id) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.rbac.modules.destroy', $module->id) }}" class="d-inline" onsubmit="return confirm('Delete this module?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
