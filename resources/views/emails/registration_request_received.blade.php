@extends('emails.layouts.email')

@section('title', 'Registration Request Received')

@section('content')
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Thank you for registering with us. Your registration has been received successfully.</p>

<p>We are glad to have you as part of the Peers Global Unity community.</p>

<p>Have a great day!</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
