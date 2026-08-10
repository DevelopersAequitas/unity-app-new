@extends('emails.layouts.email')

@section('title', 'Story Submission Rejected')

@section('content')
<p>Hello {{ $story->user?->display_name ?? $story->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Your story submission "<strong>{{ $story->title }}</strong>" has been rejected.</p>
@if($story->rejected_reason)
<p><strong>Reason for rejection:</strong> {{ $story->rejected_reason }}</p>
@endif
<p>You can update and submit a new story through the app.</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
