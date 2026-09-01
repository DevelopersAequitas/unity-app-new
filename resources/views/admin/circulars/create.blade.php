@extends('admin.layouts.app')
@section('title', 'Create Circular')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1 text-dark fw-bold">Create Circular</h1>
            <p class="text-muted small mb-0">Publish a new circular to the platform</p>
        </div>
        <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    <form id="createCircularForm" method="POST" action="{{ route('admin.circulars.store') }}">
        @include('admin.circulars._form')
    </form>
</div>
@endsection
