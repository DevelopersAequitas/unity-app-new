@extends('admin.layouts.app')

@section('title', 'Edit Brand Partner')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1 text-dark fw-bold">Edit Brand Partner</h1>
        <p class="text-muted small mb-0">Update brand partner details and settings</p>
    </div>
    <a href="{{ route('admin.brand-partners.show', $partner) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger shadow-sm">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="editBrandPartnerForm" method="POST" action="{{ route('admin.brand-partners.update', $partner) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.brand-partners._form')
    
    <div class="mt-3 card border-0 shadow-sm p-3">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update Brand Partner</button>
            <a href="{{ route('admin.brand-partners.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
@endsection
