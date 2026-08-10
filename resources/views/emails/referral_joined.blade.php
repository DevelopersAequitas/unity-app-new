@extends('emails.layouts.email')

@section('title', 'Referral Joined')

@section('content')
<p>Dear {{ $referrerName }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">A new peer has joined using your referral code.</p>

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Referral details:</strong></p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Peer Name: {{ $peerName }}</p>
<p style="margin: 0 0 16px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Referral Code: {{ $referralCode }}</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for helping grow the Peers Global community.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
