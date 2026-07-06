@extends('admin.layouts.app')

@section('title', 'Edit Ad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Ad</h4>
        <p class="text-muted small mb-0">Modify advertisement details and placements</p>
    </div>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Ads
    </a>
</div>

<form method="POST" action="{{ route('admin.ads.update', $ad) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.ads._form')
</form>
@endsection
