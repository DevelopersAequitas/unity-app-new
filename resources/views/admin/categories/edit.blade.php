@extends('admin.layouts.app')

@section('title', 'Edit Circle Category')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">Edit Circle Category</h1>
        <p class="text-muted small mb-0">Update category details and requirements</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('admin.categories.view', $category) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <button type="submit" form="editCategoryForm" class="btn btn-success d-inline-flex align-items-center gap-2">
            <i class="bi bi-check-circle"></i> Save
        </button>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form id="editCategoryForm" method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')
            @include('admin.categories._form')
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
@endsection
