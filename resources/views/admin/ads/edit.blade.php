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
@extends('admin.layouts.app')

@section('title', 'Edit Ad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Edit Ad</h1>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.ads.update', $ad) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.ads._form')
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
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
