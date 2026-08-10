@extends('emails.layouts.email')

@section('title', 'Account Successfully Deleted')

@section('content')
@php
    $userName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User';
@endphp
<!-- EDITABLE_START -->
<p style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #ffffff;">Dear {{ $userName }},</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">This email is to notify you that your account with Peers Global Unity and all associated personal data have been successfully and permanently deleted from our systems, in accordance with your request and our data retention policies.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Please note that any active subscriptions have been cancelled, and you will no longer receive communications or notifications from us.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">If you have any questions or require further assistance, please contact our support team at {{ config('membership_welcome.support_email', 'support@peersunity.com') }}.</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for having been part of the Peers Global Unity community.</p>

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Warm Regards,<br>
    <strong>Peers Global Unity Compliance Team</strong>
</p>
<!-- EDITABLE_END -->
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
