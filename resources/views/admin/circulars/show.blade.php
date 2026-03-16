@extends('admin.layouts.app')

@section('title', 'Circular Details')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>{{ $circular->title }}</strong>
        <a href="{{ route('admin.circulars.edit', $circular->getKey()) }}" class="btn btn-sm btn-primary">Edit</a>
    </div>
    <div class="card-body">
        <p><strong>Summary:</strong> {{ $circular->summary }}</p>
        <p><strong>Category:</strong> {{ ucfirst($circular->category) }}</p>
        <p><strong>Priority:</strong> {{ ucfirst($circular->priority) }}</p>
        <p><strong>Status:</strong> {{ ucfirst($circular->status) }}</p>
        <p><strong>Audience Type:</strong> {{ ucfirst(str_replace('_', ' ', $circular->audience_type)) }}</p>
        <p><strong>Publish Date:</strong> {{ optional($circular->publish_date)->format('d M Y, h:i A') }}</p>
        <p><strong>Expiry Date:</strong> {{ optional($circular->expiry_date)->format('d M Y, h:i A') ?: '—' }}</p>
        <p><strong>City:</strong> {{ $circular->city?->name ?? '—' }}</p>
        <p><strong>Circle:</strong> {{ $circular->circle?->name ?? '—' }}</p>
        <p><strong>Views:</strong> {{ (int) $circular->view_count }}</p>
        @if($circular->featured_image_url)
            <p><strong>Featured Image:</strong><br><img src="{{ $circular->featured_image_url }}" class="img-thumbnail" style="max-height:140px;" alt="featured"></p>
        @endif
        @if($circular->attachment_url)
            <p><strong>Attachment:</strong> <a href="{{ $circular->attachment_url }}" target="_blank">Open</a></p>
        @endif
        <hr>
        <div>{!! $circular->content !!}</div>
    </div>
</div>
@endsection
