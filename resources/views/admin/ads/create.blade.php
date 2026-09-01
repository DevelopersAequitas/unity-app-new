@extends('admin.layouts.app')

@section('title', 'Create Ad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="bi bi-megaphone-fill text-primary me-2"></i>Create Ad</h4>
        <p class="text-muted small mb-0">Add a new advertisement or promotional campaign banner</p>
    </div>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Ads
    </a>
</div>

<form method="POST" action="{{ route('admin.ads.store') }}" enctype="multipart/form-data">
    @csrf
    @include('admin.ads._form')
</form>
@endsection
