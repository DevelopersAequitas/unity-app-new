@extends('admin.layouts.app')
@section('title','Create Notification Campaign')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h4 mb-0">Create Notification Campaign</h1><div class="text-muted small">Configure audience, delivery channel, templates and frequency controls.</div></div></div>
<form method="POST" action="{{ route('admin.notifications.campaigns.store') }}">@csrf @include('admin.notifications.campaigns._form')</form>
@endsection
