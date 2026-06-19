@extends('admin.layouts.app')
@section('title','Edit Notification Campaign')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-0">Edit Notification Campaign</h1><div class="text-muted small">{{ $campaign->code }}</div></div><button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#previewModal"><i class="bi bi-eye me-1"></i>Preview</button></div>
<form method="POST" action="{{ route('admin.notifications.campaigns.update', $campaign->id) }}">@csrf @method('PUT') @include('admin.notifications.campaigns._form')</form>
@include('admin.notifications.campaigns._preview-modal', ['campaign' => $campaign])
@endsection
