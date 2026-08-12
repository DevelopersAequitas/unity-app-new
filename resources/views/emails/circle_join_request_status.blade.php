@php
    $user = $circleJoinRequest->user;
    $circle = $circleJoinRequest->circle;
@endphp
@extends('emails.layouts.email')

@section('title', 'Circle Join Request Status Update')

@section('content')
<p>Hello {{ $user?->display_name ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>{{ $body }}</p>

<p><strong>Circle:</strong> {{ $circle?->name ?? 'N/A' }}</p>
@if($statusLabel)
<p><strong>Current Status:</strong> {{ $statusLabel }}</p>
@endif
@if($rejectionReason)
<p><strong>Rejection Reason:</strong> {{ $rejectionReason }}</p>
@endif
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
