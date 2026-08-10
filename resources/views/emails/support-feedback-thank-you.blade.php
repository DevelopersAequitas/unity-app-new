@extends('emails.layouts.email')

@section('title', 'Support Feedback Thank You')

@section('content')
<p>Hello <strong>{{ $user->display_name ?: $user->first_name ?: 'User' }}</strong>,</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for submitting your question/feedback.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We have received your request with the following details:</p>

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Subject:</strong> {{ $feedback->subject }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Category:</strong> {{ $feedback->category }}</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Question:</strong> {{ $feedback->question }}</p>

<p style="margin: 16px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Our support team will review it and get back to you soon.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Regards,<br>
    <strong>Peers Global Unity Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
