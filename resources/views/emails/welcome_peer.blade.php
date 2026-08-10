@extends('emails.layouts.email')

@section('title', 'Welcome to Peers Global')

@section('content')
<p>Hello {{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Welcome to Peers Global! We are happy to have you in the community.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Here are great next steps to get started:</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Complete your profile</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Explore circles and opportunities</p>
<p style="margin: 0 0 16px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Start networking and growing with the platform</p>

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Warm regards,<br>
    <strong>Peers Global Team</strong>
</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
