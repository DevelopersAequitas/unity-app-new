@extends('emails.layouts.email')

@section('title', 'Referral Sent')

@section('content')
<p>Dear {{ $actorName ?? '' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We’ve received your referral for {{ $otherName ?? '' }}. Every referral strengthens our network and creates opportunities for meaningful collaborations.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">You can track the status of your referral anytime on Unity.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for being a proactive connector in our community.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Kind regards,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
