@extends('admin.layouts.app')

@section('title', 'Edit Circular')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Edit Circular</h5>
        <small class="text-muted">Update circular details and audience targeting</small>
    </div>
    <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary btn-sm">Back to Circulars</a>
</div>

<form action="{{ route('admin.circulars.update', $circular->getKey()) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.circulars._form')
</form>
@endsection
