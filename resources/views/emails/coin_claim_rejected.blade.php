@extends('emails.layouts.email')

@section('title', 'Coin Claim Rejected')

@section('content')
<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Your coin claim has been rejected.</p>
@if($claim->admin_notes)
<p>Reason: {{ $claim->admin_notes }}</p>
@endif
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
