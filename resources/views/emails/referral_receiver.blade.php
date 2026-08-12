@extends('emails.layouts.email')

@section('title', 'Referral Received')

@section('content')
<p>Dear Peer,</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">You’ve just received a referral from {{ $actorName ?? '' }}.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">This is your chance to connect, create value, and explore new opportunities.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Once you take action, don’t forget to update your referral status and share the outcome on your dashboard.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Together, we grow stronger.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Wishing you great success,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection