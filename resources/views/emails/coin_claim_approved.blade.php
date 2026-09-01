@extends('emails.layouts.email')

@section('title', 'Coin Claim Approved')

@section('content')
<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Your coin claim has been approved.</p>
<p>Coins awarded: {{ $claim->coins_awarded }}</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
