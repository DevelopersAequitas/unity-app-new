@extends('emails.layouts.email')

@section('title', 'Coin Claim Submitted')

@section('content')
<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Your coin claim has been submitted and is currently pending review.</p>
<p>Activity: {{ $claim->activity_code }}</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
