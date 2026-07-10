@extends('admin.layouts.app')

@section('title', 'Edit Ad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Ad</h4>
        <p class="text-muted small mb-0">Modify advertisement details and placements</p>
    </div>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<form id="editAdForm" method="POST" action="{{ route('admin.ads.update', $ad) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.ads._form')
</form>
@endsection
