@extends('emails.layouts.email')

@section('title', 'Account Deletion Request Received')

@section('content')
@php
    $userName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User';
@endphp
<!-- EDITABLE_START -->
<p style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #ffffff;">Dear {{ $userName }},</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We have received your request to permanently delete your account and all associated personal data from Peers Global Unity.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Our compliance team is currently reviewing your request. Please note that this process may take some time as we ensure all data removal protocols are securely handled in compliance with our data protection policies.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Request Status:</strong> Under Review / Pending</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">If you did not initiate this request, or if you wish to cancel the deletion request, please contact our support team immediately at {{ config('membership_welcome.support_email', 'support@peersunity.com') }}.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for your patience.</p>

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Warm Regards,<br>
    <strong>Peers Global Unity Compliance Team</strong>
</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
