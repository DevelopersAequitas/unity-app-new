@extends('emails.layouts.email')

@section('title', 'Business Deal Received')

@section('content')
<p>Dear {{ $otherName ?? '' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Congratulations! You’ve just <strong>received a business deal</strong> from {{ $actorName ?? '' }} worth ₹{{ $dealAmountInr ?? '' }}.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">This deal is a testimony to the trust, collaboration, and opportunities created within Peers Global.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Keep engaging, keep sharing, and continue growing stronger with every connection.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Wishing you continued success,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
