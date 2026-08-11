@extends('emails.layouts.email')

@section('title', 'P2P Meeting Scheduled')

@section('content')
<!-- EDITABLE_START -->
<p style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #ffffff;">Dear {{ $otherName ?? '' }},</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">You have a scheduled peer-to-peer meeting with {{ $actorName ?? '' }} on {{ $meetingDate ?? '' }} at {{ $meetingPlace ?? '' }}.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We encourage you to make the most of this meeting — it's an opportunity to build trust, explore synergies, and grow together.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">After your meeting, feel free to share your experience! Add a testimonial or note from your dashboard.</p>

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Wishing you a meaningful conversation,<br>
    <strong>Peers Global Team</strong>
</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
