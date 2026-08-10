@extends('emails.layouts.email')

@section('title', 'Story Submission Approved')

@section('content')
<p>Hello {{ $story->user?->display_name ?? $story->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Congratulations! Your story has been published in vyaparjagat.</p>
@if($story->story_link)
<p>You can view your story here: <a href="{{ $story->story_link }}" target="_blank">{{ $story->story_link }}</a></p>
@endif
<p>Thank you for sharing your story with the community!</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
