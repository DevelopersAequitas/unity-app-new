@extends('emails.layouts.email')

@section('title', 'Coin Claim Approved')

@section('content')
<p>Hello {{ $claim->user?->display_name ?? $claim->user?->first_name ?? 'Peer' }},</p>

<!-- EDITABLE_START -->
<p>Your coin claim for <strong>{{ $claim->activity_code === 'peers_global_feedback_video' ? 'Peers Global Feedback Video' : ($claim->activity_label ?? $claim->activity_code) }}</strong> has been approved. 🎉</p>
<p><strong>Coins awarded:</strong> {{ number_format((int) ($claim->coins_awarded ?? 5000)) }}</p>
@if($claim->user && isset($claim->user->coins_balance))
<p><strong>Updated Coin Balance:</strong> {{ number_format((int) $claim->user->coins_balance) }}</p>
@endif
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
