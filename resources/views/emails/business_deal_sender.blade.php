@extends('emails.layouts.email')

@section('title', 'Business Deal Logged')

@section('content')
<p>Dear {{ $actorName ?? '' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Congratulations on recording your business deal worth ₹<strong>{{ $dealAmountInr ?? '' }}</strong> on Unity!</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your success reflects the power of collaboration and the value of being part of Peers Global.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Keep tracking your achievements and coins on Unity, and continue building momentum.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Cheers,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
