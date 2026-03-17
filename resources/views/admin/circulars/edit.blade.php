@extends('admin.layouts.app')
@section('title', 'Edit Circular')
@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Edit Circular</h1>
    <form method="POST" action="{{ route('admin.circulars.update', $circular) }}">
        @method('PUT')
        @include('admin.circulars._form')
    </form>
</div>
@endsection
