@extends('admin.layouts.app')
@section('title', 'Create Circular')
@section('content')
<div class="container-fluid">
    <h1 class="h4 mb-3">Create Circular</h1>
    <form method="POST" action="{{ route('admin.circulars.store') }}">
        @include('admin.circulars._form')
    </form>
</div>
@endsection
