@extends('emails.layouts.email')

@section('title', 'Testimonial Sent')

@section('content')
<p>Dear {{ $actorName ?? '' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for sharing your valuable testimonial on Unity. Your words reflect the true spirit of collaboration and will inspire peers across our community.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Warm regards,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
