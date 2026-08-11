@extends('emails.layouts.email')

@section('title', 'P2P Meeting Requested')

@section('content')
<!-- EDITABLE_START -->
<p style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #ffffff;">Dear {{ $actorName ?? '' }},</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your meeting with {{ $otherName ?? '' }} on {{ $meetingDate ?? '' }} at {{ $meetingPlace ?? '' }} has been successfully logged on Unity.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We believe every conversation opens doors to growth, trust, and new partnerships.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Want to share your experience? Add a testimonial or note from your dashboard.</p>

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Team</strong>
</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
